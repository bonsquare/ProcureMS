<?php

namespace Tests\Feature;

use App\Models\DriveFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesGoogleDrive;
use Tests\TestCase;

class DriveFilesTest extends TestCase
{
    use FakesGoogleDrive, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google' => ['client_id' => 'cid', 'client_secret' => 'sec', 'redirect' => 'https://procms.example/google-drive/callback']]);
    }

    private function pdf(string $name = 'report.pdf', int $kilobytes = 12): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }

    public function test_upload_goes_to_the_files_folder_and_is_listed(): void
    {
        $this->fakeGoogle();
        $user = $this->user();
        $this->connection($user);

        $this->actingAs($user)->post(route('drive-files.store'), ['file' => $this->pdf()])->assertRedirect(route('drive-files.index'));

        $row = DriveFile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(['report.pdf', 'file-1'], [$row->name, $row->drive_file_id]);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/upload/drive/v3/files') && str_contains($r->body(), '"parents":["f-1"]') && str_contains($r->body(), '"name":"report.pdf"'));
        $this->actingAs($user)->get(route('drive-files.index'))->assertOk()->assertSee('report.pdf')->assertSee('https://drive.google.com/file/d/file-1/view');
    }

    public function test_upload_is_blocked_when_not_connected(): void
    {
        $this->fakeGoogle();
        $user = $this->user();

        $this->actingAs($user)->post(route('drive-files.store'), ['file' => $this->pdf()])->assertRedirect(route('google-drive'));

        $this->assertSame(0, DriveFile::count());
        Http::assertNothingSent();
    }

    public function test_a_file_over_20mb_is_refused_with_a_plain_message(): void
    {
        $this->fakeGoogle();
        $user = $this->user();
        $this->connection($user);

        $this->actingAs($user)->post(route('drive-files.store'), ['file' => $this->pdf('big.pdf', 20481)])
            ->assertSessionHasErrors(['file' => 'The file is too large. The limit is 20 MB.']);

        $this->assertSame(0, DriveFile::count());
    }

    public function test_other_users_cannot_see_or_delete_my_file(): void
    {
        $this->fakeGoogle();
        $owner = $this->user('A');
        $this->connection($owner);
        $file = DriveFile::create(['user_id' => $owner->id, 'name' => 'secret-liquidation.pdf', 'drive_file_id' => 'drive-9', 'mime' => 'application/pdf', 'size' => 10]);
        $sameSchool = User::factory()->create(['role' => 'school_head', 'organization_id' => $owner->organization_id, 'school_id' => $owner->school_id]);
        $master = User::factory()->create(['role' => 'master_user']);

        foreach ([$sameSchool, $master, $this->user('B')] as $other) {
            $this->actingAs($other)->get(route('drive-files.index'))->assertOk()->assertDontSee('secret-liquidation.pdf');
            $this->actingAs($other)->delete(route('drive-files.destroy', $file))->assertNotFound();
        }
        $this->assertSame(1, DriveFile::count());
    }

    public function test_delete_removes_the_drive_file_and_the_row(): void
    {
        $this->fakeGoogle();
        $user = $this->user();
        $this->connection($user);
        $file = DriveFile::create(['user_id' => $user->id, 'name' => 'old.pdf', 'drive_file_id' => 'drive-9', 'mime' => 'application/pdf', 'size' => 10]);

        $this->actingAs($user)->delete(route('drive-files.destroy', $file))->assertRedirect(route('drive-files.index'));

        $this->assertSame(0, DriveFile::count());
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/drive/v3/files/drive-9'));
    }

    public function test_invalid_grant_during_upload_creates_no_row_and_marks_needs_reconnect(): void
    {
        $this->fakeGoogle();
        $this->tokenFailure = ['error' => 'invalid_grant'];
        $user = $this->user();
        $connection = $this->connection($user, ['expires_at' => now()->subMinute()]);

        $this->actingAs($user)->post(route('drive-files.store'), ['file' => $this->pdf()])
            ->assertRedirect(route('google-drive'))->assertSessionHas('error');

        $this->assertSame(0, DriveFile::count());
        $this->assertSame('needs_reconnect', $connection->fresh()->status);
    }
}
