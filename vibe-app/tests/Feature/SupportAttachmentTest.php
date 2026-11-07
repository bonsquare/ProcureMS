<?php

namespace Tests\Feature;

use App\Models\SupportAttachment;
use App\Models\SupportMessage;
use App\Models\User;
use App\Support\PaymentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesGoogleDrive;
use Tests\TestCase;

/** Photos and PDFs on a support request are kept in the sender's own Google Drive; Railway keeps only the link. */
class SupportAttachmentTest extends TestCase
{
    use FakesGoogleDrive, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google' => ['client_id' => 'cid', 'client_secret' => 'sec', 'redirect' => 'https://procms.example/google-drive/callback']]);
        $this->fakeGoogle();
        PaymentSettings::put(['master_email' => 'master@example.com']);
        $this->user = $this->user('S1');
    }

    private function png(string $name = 'screen.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    }

    private function form(array $extra = []): array
    {
        return $extra + ['type' => 'general', 'subject' => 'Blank print', 'body' => 'Here is a screenshot'];
    }

    public function test_an_image_goes_to_the_users_drive_and_only_a_link_is_stored(): void
    {
        $this->connection($this->user);

        $this->actingAs($this->user)->post(route('support.send'), $this->form(['files' => [$this->png()]]))->assertSessionHasNoErrors();

        $attachment = SupportAttachment::first();
        $this->assertSame('file-1', $attachment->drive_file_id);
        $this->assertSame('screen.png', $attachment->name);
        $this->assertSame($this->user->id, (int) $attachment->user_id);
        $this->assertSame(SupportMessage::first()->id, (int) $attachment->support_message_id);
        $this->assertNull($attachment->support_reply_id);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/upload/drive/v3/files'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/drive/v3/files/file-1/permissions') && $r['emailAddress'] === 'master@example.com');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/permissions') && ($r['type'] ?? '') === 'anyone');
    }

    public function test_a_wrong_type_an_oversized_file_and_a_fourth_file_are_refused_and_nothing_is_saved(): void
    {
        $this->connection($this->user);
        $this->actingAs($this->user);

        $this->post(route('support.send'), $this->form(['files' => [UploadedFile::fake()->create('virus.exe', 10)]]))->assertSessionHasErrors('files.0');
        $this->post(route('support.send'), $this->form(['files' => [UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')]]))->assertSessionHasErrors('files.0');
        $this->post(route('support.send'), $this->form(['files' => [$this->png('a.png'), $this->png('b.png'), $this->png('c.png'), $this->png('d.png')]]))->assertSessionHasErrors('files');

        $this->assertSame(0, SupportMessage::count());
        $this->assertSame(0, SupportAttachment::count());
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/upload/drive/v3/files'));
    }

    public function test_without_drive_the_text_is_saved_and_the_user_is_told_to_connect_drive(): void
    {
        $this->actingAs($this->user)->post(route('support.send'), $this->form(['files' => [$this->png()]]))->assertRedirect()->assertSessionHas('error');

        $this->assertSame(1, SupportMessage::count());
        $this->assertSame(0, SupportAttachment::count());
        $this->assertStringContainsString('Google Drive', session('error'));
    }

    public function test_a_failed_share_removes_the_file_from_drive_and_keeps_only_the_text(): void
    {
        $this->connection($this->user);
        PaymentSettings::put(['master_email' => '']);

        $this->actingAs($this->user)->post(route('support.send'), $this->form(['files' => [$this->png()]]))->assertSessionHas('error');

        $this->assertSame(1, SupportMessage::count());
        $this->assertSame(0, SupportAttachment::count());
    }

    public function test_an_answer_can_carry_a_file_and_the_conversation_links_to_it(): void
    {
        $this->connection($this->user);
        $request = SupportMessage::create(['user_id' => $this->user->id, 'school_id' => $this->user->school_id, 'type' => 'general', 'subject' => 'S', 'body' => 'B']);

        $this->actingAs($this->user)->post(route('support.reply', $request), ['body' => 'More proof', 'files' => [$this->png('more.png')]])->assertSessionHasNoErrors();

        $attachment = SupportAttachment::first();
        $this->assertNotNull($attachment->support_reply_id);
        $this->get(route('support.show', $request))->assertOk()->assertSee('more.png')->assertSee('https://drive.google.com/file/d/file-1/view', false);
    }
}
