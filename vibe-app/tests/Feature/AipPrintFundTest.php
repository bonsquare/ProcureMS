<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The AIP prints without a Remarks column, and the Source of Fund choice always offers MOOE, SBFP, ARAL and Other (specify). */
class AipPrintFundTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_aip_print_has_no_remarks_column_or_remark_text(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $fx['activities'][0]->update(['remarks_list' => ['Prioritized by the school head', 'Second remark'], 'responsible_persons' => ['Teachers']]);

        $html = $this->get(route('aip.print', $fx['aip']))->assertOk()->getContent();

        $this->assertStringNotContainsString('Remarks', $html);
        $this->assertStringNotContainsString('Prioritized by the school head', $html);
        $this->assertStringContainsString('Teachers', $html, 'the Responsible Person column stays');
        $table = substr($html, strpos($html, '<table'), strpos($html, '</table>') - strpos($html, '<table'));
        preg_match_all('/<col /', $table, $cols);
        preg_match('/<tbody>.*?<tr>(.*?)<\/tr>/s', $table, $firstRow);
        $this->assertSame(count($cols[0]), preg_match_all('/<td/', $firstRow[1]), 'every body row has one cell for each column');
    }

    public function test_the_activity_form_always_offers_the_standard_funds_with_other_to_specify(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);

        $html = $this->get(route('aip.kras.edit', [$fx['aip'], $fx['kra']]))->assertOk()->getContent();

        preg_match('/<select data-fund-select[^>]*>(.*?)<\/select>/s', $html, $select);
        preg_match_all('/<option[^>]*>([^<]*)<\/option>/', $select[1], $options);
        $this->assertSame(['Select source of fund', 'MOOE', 'SBFP', 'ARAL', 'Others'], array_map('trim', $options[1]));
        $this->assertStringContainsString('placeholder="Specify source of fund"', $html);
    }

    public function test_an_other_fund_typed_in_specify_is_saved_instead_of_refused(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $activity = $fx['activities'][0];
        $form = fn (string $fund) => ['pillar' => $fx['kra']->pillar, 'kra' => $fx['kra']->kra, 'program' => $fx['kra']->program, 'activities' => [
            ['id' => $activity->id, 'activity' => $activity->activity, 'physical_target' => 1, 'q1_amount' => 100, 'source_of_fund' => $fund],
        ]];

        $this->put(route('aip.kras.update', [$fx['aip'], $fx['kra']]), $form('PTA Fund'))->assertSessionHasNoErrors();
        $this->assertSame('PTA Fund', AipActivity::find($activity->id)->source_of_fund);

        foreach (['SBFP', 'ARAL', 'MOOE'] as $standard) {
            $this->put(route('aip.kras.update', [$fx['aip'], $fx['kra']]), $form($standard))->assertSessionHasNoErrors();
            $this->assertSame($standard, AipActivity::find($activity->id)->source_of_fund);
        }
    }

    public function test_a_fund_saved_before_the_list_changed_shows_as_other_with_its_name_to_specify(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $fx['activities'][0]->update(['source_of_fund' => 'SEF']);

        $html = $this->get(route('aip.kras.edit', [$fx['aip'], $fx['kra']]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<option selected>Others<\/option>/', $html);
        $this->assertMatchesRegularExpression('/<input data-fund-other value="SEF"/', $html);
    }
}
