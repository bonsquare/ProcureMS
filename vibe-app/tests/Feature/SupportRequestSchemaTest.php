<?php

namespace Tests\Feature;

use App\Support\SupportStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** The statuses of a support request and the columns that carry its type, assignee and conversation. */
class SupportRequestSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_five_statuses_have_their_labels_and_only_resolved_is_closed(): void
    {
        $this->assertSame(['pending', 'for_process', 'on_hold', 'approved', 'resolved'], SupportStatus::ALL);
        $this->assertSame('On hold', SupportStatus::label('on_hold'));
        $this->assertSame('On process', SupportStatus::label('for_process'));
        $this->assertTrue(SupportStatus::isOpen('approved'));
        $this->assertFalse(SupportStatus::isOpen('resolved'));
    }

    public function test_the_old_statuses_are_mapped_to_the_new_ones(): void
    {
        $this->assertSame('pending', SupportStatus::fromLegacy('open'));
        $this->assertSame('for_process', SupportStatus::fromLegacy('answered'));
        $this->assertSame('resolved', SupportStatus::fromLegacy('closed'));
        $this->assertSame('on_hold', SupportStatus::fromLegacy('on_hold'));
    }

    public function test_the_new_columns_and_the_attachment_table_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('support_messages', ['type', 'subject', 'assigned_name', 'assigned_telegram_id', 'resolved_at']));
        $this->assertTrue(Schema::hasColumns('support_replies', ['sender', 'user_id']));
        $this->assertTrue(Schema::hasColumns('support_attachments', ['support_message_id', 'support_reply_id', 'user_id', 'drive_file_id', 'name', 'mime', 'size']));
    }
}
