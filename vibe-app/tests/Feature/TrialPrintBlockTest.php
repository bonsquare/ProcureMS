<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrialPrintBlockTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: array<string, int>}> */
    public static function printRoutes(): array
    {
        return [
            'ORS' => ['liquidation.print', ['liquidationReport' => 1]],
            'DV' => ['accounting.dv.print', ['liquidationReport' => 1]],
            'procurement request' => ['procurement.print', ['procurementRequest' => 1]],
            'procurement document' => ['procurement.documents.print', ['procurementRequest' => 1, 'procurementDocument' => 1]],
            'delivery reconciliation' => ['procurement.delivery-reconciliation', ['procurementRequest' => 1]],
            'SIP' => ['planning.sip.print', []],
            'AIP' => ['aip.print', ['aip' => 1]],
            'SOB' => ['planning.sob.print', ['sobPlan' => 1]],
            'APP' => ['planning.app.print', ['appPlan' => 1]],
            'APP-CSE' => ['planning.app-cse.print', ['appCsePlan' => 1]],
            'POW' => ['planning.pow.print', ['powPlan' => 1]],
            'report of checks issued' => ['cash.report.roci', []],
            'checks and advices' => ['cash.report.checks', []],
            'cash disbursement register' => ['cash.report.register', []],
            'bank reconciliation' => ['cash.report.bank', []],
            'kept report' => ['cash.report.history', ['history' => 1]],
        ];
    }

    private function schoolUser(string $status, string $code): User
    {
        $organization = Organization::create(['name' => $code, 'slug' => strtolower($code), 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => $code, 'name' => $code.' School', 'status' => 'active']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'school_id' => $school->id, 'role' => 'school_admin']);
        Subscription::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'user_id' => $user->id, 'plan' => $status === 'trial' ? 'trial' : 'annual', 'billing_cycle' => 'annual', 'amount' => 1000, 'payment_status' => 'pending', 'status' => $status, 'starts_at' => now(), 'subscription_end' => now()->addDays(3)]);

        return $user;
    }

    /**
     * @dataProvider printRoutes
     *
     * @param  array<string, int>  $parameters
     */
    #[DataProvider('printRoutes')]
    public function test_a_trial_user_gets_the_prompt_on_every_print_route(string $name, array $parameters): void
    {
        $response = $this->actingAs($this->schoolUser('trial', 'TRA'))->get(route($name, $parameters));

        $response->assertStatus(402)->assertSee('Printing is available on a paid subscription')->assertSee('1,000.00')->assertDontSee('data-official-page', false);
    }

    public function test_the_prompt_is_the_answer_to_a_direct_url_whatever_the_record(): void
    {
        $this->actingAs($this->schoolUser('trial', 'TRB'))->get('/liquidation/987654/print')->assertStatus(402);
        $this->get('/liquidation/987654/print', ['Accept' => 'application/json'])->assertStatus(402)->assertJsonStructure(['message']);
    }

    public function test_an_active_free_and_master_user_prints_as_before(): void
    {
        foreach (['active', 'free'] as $status) {
            auth()->logout();
            $this->actingAs($this->schoolUser($status, 'AC'.strtoupper(substr($status, 0, 1))))->get(route('cash.report.roci'))->assertSessionHasErrors('school_id');
        }
        auth()->logout();
        $master = User::factory()->create(['role' => 'master_user', 'organization_id' => null, 'school_id' => null]);
        $this->actingAs($master)->get(route('cash.report.roci'))->assertSessionHasErrors('school_id');
    }

    public function test_the_live_previews_inside_forms_still_work_in_trial(): void
    {
        $user = $this->schoolUser('trial', 'TRC');

        $this->actingAs($user)->post(route('liquidation.preview'))->assertStatus(200);
        $this->post(route('procurement.preview'))->assertStatus(200);
    }
}
