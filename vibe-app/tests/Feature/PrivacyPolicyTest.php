<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_read_the_privacy_policy_without_signing_in(): void
    {
        $this->get('/privacy')->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('ProcMS')
            ->assertSee('Google Drive')
            ->assertSee('only the folders and files it creates')
            ->assertSee('bonie.office@gmail.com');
    }

    public function test_a_signed_in_user_can_read_it_too_and_the_route_is_named(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'master_user']))->get(route('privacy'))->assertOk()->assertSee('Privacy Policy');
        $this->assertSame('/privacy', route('privacy', [], false));
    }

    public function test_the_policy_says_nobody_else_can_see_a_users_files_and_how_to_revoke_access(): void
    {
        $this->get('/privacy')->assertOk()
            ->assertSee('No other user')->assertSee('revoke')->assertSee('not sold or shared');
    }
}
