<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserActionsAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_what_a_user_does_is_recorded_and_only_looking_is_not(): void
    {
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);

        $this->actingAs($master)->get(route('home'))->assertOk();
        $this->assertSame(0, AuditLog::withoutGlobalScopes()->count(), 'opening a page is not recorded');

        $this->actingAs($master)->post(route('logout'));
        $log = AuditLog::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame($master->id, $log->user_id);
        $this->assertSame('logout', $log->action);
        $this->assertSame('POST', $log->metadata['method']);
        $this->assertSame('done', $log->metadata['result']);
        $this->assertArrayNotHasKey('password', $log->metadata);
    }
}
