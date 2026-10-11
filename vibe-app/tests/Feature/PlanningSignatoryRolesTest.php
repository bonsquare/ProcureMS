<?php

namespace Tests\Feature;

use App\Models\SchoolStaff;
use App\Services\AppCseService;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** Every signature of the SIP, AIP, SOB and APP-CSE takes a typed Role, Name and Position, so people from the division or district office can sign. */
class PlanningSignatoryRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sip_prints_the_typed_role_name_and_position_and_the_standard_caption_when_blank(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);

        $this->post(route('planning.sip.signatories'), [
            'school_id' => $fx['school']->id, 'start_year' => 2026,
            'prepared_by_name' => 'Pat School', 'prepared_by_position' => 'Teacher III', 'prepared_by_role' => '',
            'recommended_by_name' => 'Dana District', 'recommended_by_position' => 'Public Schools District Supervisor', 'recommended_by_role' => 'Endorsed by',
            'approved_by_name' => 'Sam Division', 'approved_by_position' => 'Asst. Schools Division Superintendent', 'approved_by_role' => 'Approved',
        ])->assertSessionHasNoErrors();

        $html = $this->get(route('planning.sip.print', ['school_id' => $fx['school']->id, 'start_year' => 2026]))->assertOk()->getContent();
        $this->assertStringContainsString('>Prepared by:<', $html, 'a blank role keeps the standard caption');
        $this->assertStringContainsString('>Endorsed by:<', $html);
        $this->assertStringContainsString('>Approved:<', $html);
        $this->assertStringContainsString('Dana District', $html);
        $this->assertStringContainsString('Asst. Schools Division Superintendent', $html);
        $this->assertStringNotContainsString('>Recommending Approval:<', $html);
    }

    public function test_the_aip_prints_the_typed_role_name_and_position(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);

        $this->put(route('aip.update', $fx['aip']), [
            'noted_by_name' => 'Dana District', 'noted_by_position' => 'District Supervisor', 'noted_by_role' => 'Reviewed by',
            'prepared_by_name' => 'Pat School', 'prepared_by_position' => 'School Head',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Reviewed by', $fx['aip']->fresh()->noted_by_role);

        $html = $this->get(route('aip.print', $fx['aip']))->assertOk()->getContent();
        $this->assertStringContainsString('>Reviewed by:<', $html);
        $this->assertStringContainsString('>Prepared by:<', $html);
        $this->assertStringContainsString('>Approved by:<', $html);
        $this->assertStringNotContainsString('>Noted by:<', $html);
        $this->assertStringContainsString('District Supervisor', $html);
    }

    public function test_the_sob_prints_the_typed_role_name_and_position(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = app(SobService::class)->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);

        $this->put(route('planning.sob.update', $plan), [
            'recommended_by_role' => 'Endorsed by', 'recommended_by_name' => 'Dana District', 'recommended_by_position' => 'PSDS',
            'approved_by_role' => 'Approved', 'approved_by_name' => 'Sam Division', 'approved_by_position' => 'SDS',
        ])->assertSessionHasNoErrors();

        $page = $this->get(route('planning.sob.show', $plan))->assertOk()->getContent();
        foreach (['prepared_by', 'recommended_by', 'approved_by'] as $slot) {
            $this->assertStringContainsString('name="'.$slot.'_role"', $page, 'a Role field for '.$slot);
        }
        $html = $this->get(route('planning.sob.print', $plan))->assertOk()->getContent();
        $this->assertStringContainsString('>Endorsed by:<', $html);
        $this->assertStringContainsString('>Approved:<', $html);
        $this->assertStringContainsString('>Prepared by:<', $html, 'the blank role keeps its caption');
        $this->assertStringContainsString('DANA DISTRICT', $html, 'the SOB prints names in capitals');
    }

    public function test_the_app_cse_prints_typed_signatories_over_the_school_staff_and_keeps_the_defaults_when_blank(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        SchoolStaff::create(['organization_id' => $fx['organization']->id, 'school_id' => $fx['school']->id, 'name' => 'Pat Supply', 'position' => 'Teacher III', 'document_role' => 'Property Custodian']);
        $plan = app(AppCseService::class)->create($fx['school'], 2026, $fx['user']);

        $default = $this->get(route('planning.app-cse.print', $plan))->assertOk()->getContent();
        $this->assertStringContainsString('Property/Supply Officer', $default);
        $this->assertStringContainsString('Prepared by:', $default);

        $this->put(route('planning.app-cse.update', $plan), [
            'prepared_by_role' => 'Prepared by', 'prepared_by_name' => 'Dana District', 'prepared_by_position' => 'Division Supply Officer',
            'noted_by_role' => 'Noted', 'noted_by_name' => 'Sam Head', 'noted_by_position' => 'District Supervisor',
            'certified_by_role' => 'Funds certified by', 'certified_by_name' => 'Ann Accountant', 'certified_by_position' => 'Budget Officer II',
            'agency_head_role' => 'Approved', 'agency_head_name' => 'Lee Division', 'agency_head_position' => 'Schools Division Superintendent',
        ])->assertSessionHasNoErrors();

        $page = $this->get(route('planning.app-cse.show', $plan))->assertOk()->getContent();
        foreach (['prepared_by', 'noted_by', 'certified_by', 'agency_head'] as $slot) {
            $this->assertStringContainsString('name="'.$slot.'_role"', $page);
        }
        $html = $this->get(route('planning.app-cse.print', $plan))->assertOk()->getContent();
        foreach (['Dana District', 'Division Supply Officer', 'District Supervisor', 'Budget Officer II', '>Noted:<', '>Funds certified by:<', '>Approved:<'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('Pat Supply', $html, 'the typed name wins over the staff default');
        $this->assertStringNotContainsString('>Property/Supply Officer<', $html);
    }
}
