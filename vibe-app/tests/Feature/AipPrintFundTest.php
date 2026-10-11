<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use App\Models\AipKra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The AIP print keeps its template; the Source of Fund choice of the form always offers MOOE, SBFP, ARAL and Other (specify). */
class AipPrintFundTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_aip_print_keeps_the_template_columns_including_remarks_and_the_template_fund_note(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $fx['activities'][0]->update(['remarks_list' => ['Prioritized by the school head', 'Second remark'], 'responsible_persons' => ['Teachers']]);

        $html = $this->get(route('aip.print', $fx['aip']))->assertOk()->getContent();

        $this->assertStringContainsString('<th rowspan="2">Remarks</th>', $html, 'the template has a Remarks column');
        $this->assertStringContainsString('Prioritized by the school head', $html);
        $this->assertStringContainsString('Teachers', $html);
        $this->assertStringContainsString('(School: MOOE, SEF, IGP, others; Division Office Proper:', $html, 'the template note is printed as in the AIP template');
        $table = substr($html, strpos($html, '<table'), strpos($html, '</table>') - strpos($html, '<table'));
        preg_match_all('/<col /', $table, $cols);
        preg_match('/<tbody>.*?<tr>(.*?)<\/tr>/s', $table, $firstRow);
        $this->assertSame(count($cols[0]), preg_match_all('/<td/', $firstRow[1]), 'every body row has one cell for each column');
    }

    public function test_the_pillar_cell_merges_down_its_rows_and_every_other_column_repeats(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $rows = count($fx['activities']);
        $second = AipKra::create(['organization_id' => $fx['organization']->id, 'aip_id' => $fx['aip']->id, 'pillar' => 'Quality', 'kra' => 'KRA 9', 'program' => 'Program Two']);
        AipActivity::create(['organization_id' => $fx['organization']->id, 'aip_id' => $fx['aip']->id, 'aip_kra_id' => $second->id, 'activity' => 'Another', 'physical_target' => 1]);

        $html = $this->get(route('aip.print', $fx['aip']))->assertOk()->getContent();

        $this->assertSame(2, preg_match_all('/<td rowspan="\d+" class="pillar"/', $html), 'one pillar cell for each pillar');
        $this->assertStringContainsString('<td rowspan="'.$rows.'" class="pillar"', $html, 'Access spans all of its rows');
        $this->assertSame($rows + 1, substr_count($html, 'KRA 3') + substr_count($html, 'KRA 9'), 'the KRA still repeats on every row');
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
