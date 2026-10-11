<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtraBlankRowsTest extends TestCase
{
    use RefreshDatabase;

    private const HINT = 'Empty bordered rows added at the end of the printed table (up to %d).';

    private function userWithRequest(): array
    {
        $organization = Organization::create(['name' => 'Blank', 'slug' => 'blank', 'status' => 'active']);
        $school = School::create(['organization_id' => $organization->id, 'code' => 'BLANK', 'name' => 'Blank School', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'school_admin', 'organization_id' => $organization->id, 'school_id' => $school->id]);
        Subscription::create([
            'organization_id' => $organization->id, 'school_id' => $school->id, 'user_id' => $user->id, 'plan' => 'professional', 'billing_cycle' => 'monthly',
            'amount' => 0, 'payment_status' => 'paid', 'status' => 'active', 'starts_at' => now()->subMonth(), 'subscription_end' => now()->addMonth(),
        ]);
        $request = ProcurementRequest::create(['organization_id' => $organization->id, 'school_id' => $school->id, 'requested_by' => $user->id, 'request_number' => 'PR-2026-BLK', 'title' => 'Rice', 'amount' => 1500, 'status' => 'approved']);

        return [$user, $request];
    }

    public function test_the_purchase_request_form_has_the_standard_extra_blank_rows_control(): void
    {
        [$user] = $this->userWithRequest();

        $html = $this->actingAs($user)->get(route('procurement.create'))->assertOk()->getContent();

        $this->assertStringContainsString('Extra Blank Rows', $html);
        $this->assertStringContainsString(sprintf(self::HINT, 100), $html);
        $this->assertSame(1, preg_match('/<input[^>]*name="extra_blank_rows"[^>]*>/', $html, $box), 'No Extra Blank Rows box');
        $this->assertStringContainsString('type="number"', $box[0]);
        $this->assertStringContainsString('max="100"', $box[0]);
        $this->assertStringNotContainsString('blank-rows-stepper', $html);
        $this->assertStringNotContainsString('Extra blank print rows', $html);
    }

    public function test_the_editing_form_shows_the_saved_number_of_blank_rows(): void
    {
        [$user, $request] = $this->userWithRequest();
        $request->update(['extra_blank_rows' => 7]);

        $this->actingAs($user)->get(route('procurement.edit', $request))->assertOk()
            ->assertSee('name="extra_blank_rows"', false)->assertSee('value="7"', false);
    }

    public function test_the_document_form_has_the_same_control_for_the_po_and_for_the_iar_and_ris(): void
    {
        [$user, $request] = $this->userWithRequest();

        $html = $this->actingAs($user)->get(route('procurement.documents', $request))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'name="extra_blank_rows"'));
        $this->assertStringContainsString(sprintf(self::HINT, 20), $html);
        $this->assertStringContainsString(sprintf(self::HINT, 6), $html);
        $this->assertStringNotContainsString('Manual blank item rows', $html);
        // The existing show/hide hooks of the document form stay on each control.
        $this->assertMatchesRegularExpression('/<label[^>]*data-po-only[^>]*>\s*Extra Blank Rows/', $html);
        $this->assertMatchesRegularExpression('/<label[^>]*data-extra-rows-only[^>]*>\s*Extra Blank Rows/', $html);
    }
}
