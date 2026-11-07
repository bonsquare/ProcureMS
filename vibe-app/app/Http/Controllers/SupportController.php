<?php

namespace App\Http\Controllers;

use App\Exceptions\DriveNotConnected;
use App\Models\SupportAttachment;
use App\Models\SupportMessage;
use App\Models\SupportReply;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\GoogleDriveService;
use App\Services\SupportAttachments;
use App\Services\TelegramService;
use App\Support\SupportStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

/** The Support tab: how a user reaches Customer Services. The master user keeps the contact details here. */
class SupportController extends Controller
{
    public const FIELDS = ['support_email', 'support_phone', 'support_hours', 'support_note'];

    /** Photos and PDFs: at most 3 files of 5 MB each. */
    private const FILE_RULES = ['files' => ['nullable', 'array', 'max:3'], 'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120']];

    public function show(Request $request)
    {
        return $this->page($request, null);
    }

    /** One request opened as a conversation; only its owner (and the master user) can open it. */
    public function thread(Request $request, SupportMessage $supportMessage)
    {
        abort_unless($request->user()->isMaster() || (int) $supportMessage->user_id === (int) $request->user()->id, 404);

        return $this->page($request, $supportMessage->load(['replies.attachments', 'attachments', 'user', 'school']));
    }

    /** The user answers on a request that is still open; the answer goes to the Telegram chat under the request message. */
    public function reply(Request $request, SupportMessage $supportMessage, TelegramService $telegram, SupportAttachments $files)
    {
        $user = $request->user();
        abort_unless((int) $supportMessage->user_id === (int) $user->id, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']] + self::FILE_RULES);
        if (! SupportStatus::isOpen($supportMessage->status)) {
            return redirect()->route('support.show', $supportMessage)->with('error', 'This request is resolved. Create a new request if you still need help.');
        }

        $reply = $supportMessage->replies()->create(['body' => trim($data['body']), 'sender' => 'user', 'user_id' => $user->id]);
        $supportMessage->touch();
        [$attached, $problem] = $this->attach($files, $user, $request->file('files', []), $supportMessage, $reply);
        $telegram->send("💬 Support #{$supportMessage->id} · {$user->name}\n\n".trim($data['body']).$this->fileLines($attached), $supportMessage->telegram_message_id ? (int) $supportMessage->telegram_message_id : null);

        return redirect()->route('support.show', $supportMessage)->with('success', 'Your answer was sent to Customer Services.')->with('error', $problem);
    }

    /** Puts the files in the user's own Drive. The text is already saved, so a problem with a file only adds a notice. @return array{0: list<SupportAttachment>, 1: ?string} */
    private function attach(SupportAttachments $files, User $user, array $uploads, SupportMessage $request, ?SupportReply $reply = null): array
    {
        if ($uploads === []) {
            return [[], null];
        }
        try {
            return [$files->store($user, $uploads, $request, $reply), null];
        } catch (DriveNotConnected $exception) {
            return [[], $exception->getMessage().' Your message was saved without the file: connect your Google Drive on the Google Drive page and send the file again.'];
        } catch (RuntimeException $exception) {
            report($exception);

            return [[], 'Your message was saved, but the file could not be shared with Customer Services. Please try again.'];
        }
    }

    /** @param  list<SupportAttachment>  $attached */
    private function fileLines(array $attached): string
    {
        return collect($attached)->map(fn (SupportAttachment $file) => "\n📎 {$file->name} — ".app(GoogleDriveService::class)->webLink($file->drive_file_id))->implode('');
    }

    private function page(Request $request, ?SupportMessage $current)
    {
        $user = $request->user();
        // A user sees only their own conversation with Customer Services; the master sees all of them.
        $messages = SupportMessage::query()->with(['user', 'school'])
            ->when(! $user->isMaster(), fn ($query) => $query->where('user_id', $user->id))
            ->latest('updated_at')->latest('id')->limit(50)->get();

        return view('support', [
            'contact' => collect(self::FIELDS)->mapWithKeys(fn (string $key) => [$key => (string) SystemSetting::get($key, '')])->all(),
            'canEdit' => $user->isMaster(),
            'messages' => $messages,
            'current' => $current,
            'mailOn' => (string) SystemSetting::get('support_email', '') !== '',
        ]);
    }

    /** Saves the message and emails it to the Customer Services address; Customer Services answers the user by email (Reply-To is the user). */
    public function send(Request $request, TelegramService $telegram, SupportAttachments $files)
    {
        $data = $request->validate(['type' => ['required', Rule::in(['general', 'subscription'])], 'subject' => ['required', 'string', 'max:120'], 'body' => ['required', 'string', 'min:3', 'max:2000']] + self::FILE_RULES);
        $user = $request->user();
        $message = SupportMessage::create(['organization_id' => $user->organization_id, 'school_id' => $user->school_id, 'user_id' => $user->id, 'type' => $data['type'], 'subject' => trim($data['subject']), 'body' => trim($data['body'])]);
        [$attached, $problem] = $this->attach($files, $user, $request->file('files', []), $message);

        $text = "Support #{$message->id}\nSchool: ".($user->school?->name ?? '—')."\nFrom: {$user->name} ({$user->email})\n\n{$message->body}\n\nReply to this email to answer.";
        $to = (string) SystemSetting::get('support_email', '');
        $status = 'not_configured';
        if ($to !== '') {
            try {
                Mail::raw($text, fn ($mail) => $mail->to($to)->replyTo($user->email, $user->name)->subject("Support #{$message->id}: {$user->name}"));
                $status = 'sent';
            } catch (Throwable $exception) {
                report($exception);
                $status = 'failed';
            }
        }
        // The same message goes to the Customer Services Telegram chat; a reply there comes back to this page.
        // The sender's email stays out of the chat; staff answer here and the reply reaches the user on this page.
        $telegramId = $telegram->send(($message->type === 'subscription' ? '🧾 ' : '')."Support #{$message->id} · {$message->subject}\nSchool: ".($user->school?->name ?? '—')."\nFrom: {$user->name}\n\n{$message->body}".$this->fileLines($attached)."\n\nReply to this message to answer, or send /close to close it.");
        // The telegram_status column records the delivery: email, or Telegram when that is the only way in.
        $message->update(['telegram_message_id' => $telegramId, 'telegram_status' => $telegramId && $status !== 'sent' ? 'sent' : $status]);

        return redirect()->route('support')->with('success', $telegramId || $status === 'sent'
            ? 'Your message was sent to Customer Services. '.($telegramId ? 'Their reply will appear below.' : 'They will reply to your email.')
            : 'Your message was saved, but it could not be delivered to Customer Services right now. Please use the contact details above.')->with('error', $problem);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->isMaster(), 403);
        $data = $request->validate([
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:100'],
            'support_hours' => ['nullable', 'string', 'max:255'],
            'support_note' => ['nullable', 'string', 'max:1000'],
        ]);
        foreach (self::FIELDS as $key) {
            SystemSetting::put($key, trim((string) ($data[$key] ?? '')) ?: null);
        }

        return redirect()->route('support')->with('success', 'Support details saved.');
    }
}
