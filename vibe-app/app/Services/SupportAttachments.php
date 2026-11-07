<?php

namespace App\Services;

use App\Exceptions\DriveNotConnected;
use App\Models\SupportAttachment;
use App\Models\SupportMessage;
use App\Models\SupportReply;
use App\Models\User;
use App\Support\PaymentSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Puts the files of a support request in the sender's own Google Drive (shared with the administrator only); Railway keeps the link. */
class SupportAttachments
{
    public function __construct(private GoogleDriveService $drive) {}

    /**
     * @param  list<UploadedFile>  $files
     * @return list<SupportAttachment>
     *
     * @throws DriveNotConnected when the sender has no working Drive connection
     * @throws RuntimeException when a file could not be shared with the administrator (every file of this call is then removed)
     */
    public function store(User $user, array $files, SupportMessage $request, ?SupportReply $reply = null): array
    {
        $connection = $user->driveConnection;
        if (! $connection?->isConnected()) {
            throw new DriveNotConnected('Connect your Google Drive to attach files.');
        }
        $email = (string) (PaymentSettings::get()['master_email'] ?? '');
        $made = [];
        try {
            foreach ($files as $file) {
                if ($email === '') {
                    throw new RuntimeException('No administrator email is set to share the file with.');
                }
                $name = Str::limit(basename($file->getClientOriginalName()), 150, '');
                $mime = (string) $file->getMimeType();
                $stored = $this->drive->upload($connection, 'Support', $name, $file->get(), $mime);
                $made[] = ['id' => $stored['id'], 'row' => null];
                $this->drive->share($connection, $stored['id'], $email);
                $made[array_key_last($made)]['row'] = SupportAttachment::create([
                    'support_message_id' => $request->id, 'support_reply_id' => $reply?->id, 'user_id' => $user->id,
                    'drive_file_id' => $stored['id'], 'name' => $name, 'mime' => $mime, 'size' => $stored['size'],
                ]);
            }
        } catch (Throwable $exception) {
            foreach ($made as $item) {
                $item['row']?->delete();
                try {
                    $this->drive->delete($connection, $item['id']);
                } catch (Throwable) {
                    // The file stays in the user's own Drive; nothing is kept here.
                }
            }
            if ($exception instanceof DriveNotConnected) {
                throw $exception;
            }
            throw new RuntimeException('The attachment could not be shared with Customer Services.', 0, $exception);
        }

        return array_map(fn (array $item) => $item['row'], $made);
    }
}
