<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SupportMessage;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_user_sees_the_support_page_and_only_the_master_keeps_the_contact_details(): void
    {
        $organization = Organization::create(['name' => 'sp', 'slug' => 'sp', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'SP', 'name' => 'Support School', 'status' => 'active']);
        $schoolUser = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);

        $this->actingAs($schoolUser)->get(route('support'))->assertOk()->assertSee('Contact Customer Services')->assertSee('have not been set yet')->assertDontSee('Customer Services details');
        $this->actingAs($schoolUser)->put(route('support.update'), ['support_email' => 'x@example.test'])->assertForbidden();

        $this->actingAs($master)->get(route('support'))->assertOk()->assertSee('Customer Services details');
        $this->actingAs($master)->put(route('support.update'), ['support_email' => 'help@example.test', 'support_phone' => '0917 000 0000', 'support_hours' => 'Mon to Fri, 8 to 5'])->assertRedirect(route('support'));
        $this->assertSame('help@example.test', SystemSetting::get('support_email'));

        $this->actingAs($schoolUser)->get(route('support'))->assertOk()->assertSee('help@example.test')->assertSee('Mon to Fri, 8 to 5')->assertSee('Support', false);
        $this->actingAs($schoolUser)->get(route('home'))->assertOk()->assertSee('href="'.route('support').'"', false);
    }

    private function schoolUser(string $slug = 'rq'): User
    {
        $organization = Organization::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => strtoupper($slug), 'name' => 'School '.$slug, 'status' => 'active']);

        return User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
    }

    public function test_a_request_needs_a_subject_and_a_known_type(): void
    {
        $user = $this->schoolUser('rq1');

        $this->actingAs($user)->post(route('support.send'), ['type' => 'general', 'subject' => '', 'body' => 'Help me please'])->assertSessionHasErrors('subject');
        $this->post(route('support.send'), ['type' => 'other', 'subject' => 'Hi', 'body' => 'Help me please'])->assertSessionHasErrors('type');
        $this->assertSame(0, SupportMessage::count());

        $this->post(route('support.send'), ['type' => 'subscription', 'subject' => 'Renewal', 'body' => 'I want to renew'])->assertSessionHasNoErrors();
        $request = SupportMessage::first();
        $this->assertSame('subscription', $request->type);
        $this->assertSame('Renewal', $request->subject);
        $this->assertSame('pending', $request->status);
    }

    public function test_a_subscription_request_is_marked_in_telegram(): void
    {
        config(['services.telegram.token' => 'T', 'services.telegram.chat_id' => '555']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 3]])]);

        $this->actingAs($this->schoolUser('rq2'))->post(route('support.send'), ['type' => 'subscription', 'subject' => 'Renewal', 'body' => 'I want to renew'])->assertSessionHasNoErrors();

        Http::assertSent(fn ($r) => str_contains((string) $r['text'], '🧾') && str_contains((string) $r['text'], 'Renewal'));
    }
}
