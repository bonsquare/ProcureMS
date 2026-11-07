<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\SupportAttachment;
use App\Models\SupportMessage;
use App\Models\SupportReply;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeSupport extends Command
{
    public const KEEP_DAYS = 5;

    protected $signature = 'support:purge';

    protected $description = 'Delete support requests that were resolved more than five days ago, with their replies and file links (the files stay in the users\' own Google Drive)';

    public function handle(): int
    {
        $ids = SupportMessage::query()->where('status', 'resolved')->where('resolved_at', '<=', now()->subDays(self::KEEP_DAYS))->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::transaction(function () use ($ids) {
                foreach ($ids->chunk(200) as $chunk) {
                    SupportAttachment::query()->whereIn('support_message_id', $chunk)->delete();
                    SupportReply::query()->whereIn('support_message_id', $chunk)->delete();
                    SupportMessage::query()->whereIn('id', $chunk)->delete();
                }
                AuditLog::create(['action' => 'support_requests_purged', 'metadata' => ['count' => $ids->count()]]);
            });
        }
        $this->info("Removed {$ids->count()} resolved support request(s).");

        return self::SUCCESS;
    }
}
