<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use App\Models\SystemSetting;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** The Support tab: how a user reaches Customer Services. The master user keeps the contact details here. */
class SupportController extends Controller
{
    public const FIELDS = ['support_email', 'support_phone', 'support_hours', 'support_note'];

    public function show(Request $request)
    {
        $user = $request->user();
        // A user sees only their own conversation with Customer Services; the master sees all of them.
        $messages = SupportMessage::query()->with(['replies', 'user', 'school'])
            ->when(! $user->isMaster(), fn ($query) => $query->where('user_id', $user->id))
            ->latest('id')->limit(50)->get();

        return view('support', [
            'contact' => collect(self::FIELDS)->mapWithKeys(fn (string $key) => [$key => (string) SystemSetting::get($key, '')])->all(),
            'canEdit' => $user->isMaster(),
            'messages' => $messages,
            'mailOn' => (string) SystemSetting::get('support_email', '') !== '',
        ]);
    }

    /** Saves the message and emails it to the Customer Services address; Customer Services answers the user by email (Reply-To is the user). */
    public function send(Request $request, TelegramService $telegram)
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:3', 'max:2000']]);
        $user = $request->user();
        $message = SupportMessage::create(['organization_id' => $user->organization_id, 'school_id' => $user->school_id, 'user_id' => $user->id, 'body' => trim($data['body'])]);

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
        $telegramId = $telegram->send("Support #{$message->id}\nSchool: ".($user->school?->name ?? '—')."\nFrom: {$user->name}\n\n{$message->body}\n\nReply to this message to answer, or send /close to close it.");
        // The telegram_status column records the delivery: email, or Telegram when that is the only way in.
        $message->update(['telegram_message_id' => $telegramId, 'telegram_status' => $telegramId && $status !== 'sent' ? 'sent' : $status]);

        return redirect()->route('support')->with('success', $telegramId || $status === 'sent'
            ? 'Your message was sent to Customer Services. '.($telegramId ? 'Their reply will appear below.' : 'They will reply to your email.')
            : 'Your message was saved, but it could not be delivered to Customer Services right now. Please use the contact details above.');
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
