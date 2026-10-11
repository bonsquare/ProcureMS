<?php

namespace Tests\Feature;

use App\Models\SobItem;
use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** Several particulars are added under one activity and account in one go; the AIP amount is only compared where it exists. */
class SobMultiParticularsTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SobService
    {
        return app(SobService::class);
    }

    /** @return array<string, mixed> */
    private function lines(): array
    {
        return [
            ['particulars' => 'Bond paper', 'frequency' => 1, 'quantity' => 10, 'unit' => 'ream', 'unit_cost' => 100],
            ['particulars' => 'Ink', 'frequency' => '', 'quantity' => 2, 'unit' => 'bottle', 'unit_cost' => 250.5],
        ];
    }

    public function test_add_items_saves_every_particulars_under_the_chosen_activity_and_account(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);

        $items = $this->service()->addItems($plan, $fx['activities'][0]->id, $fx['account']->id, $this->lines());

        $this->assertCount(2, $items);
        $this->assertSame(['Bond paper', 'Ink'], SobItem::orderBy('id')->pluck('particulars')->all());
        $this->assertSame([1000.0, 501.0], SobItem::orderBy('id')->pluck('amount')->map(fn ($a) => (float) $a)->all());
        $this->assertSame([$fx['activities'][0]->id], SobItem::pluck('aip_activity_id')->unique()->values()->all());
        $this->assertSame([$fx['account']->id], SobItem::pluck('chart_of_account_id')->unique()->values()->all());
    }

    public function test_one_invalid_particulars_saves_nothing(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $lines = $this->lines();
        $lines[1]['quantity'] = 0;

        try {
            $this->service()->addItems($plan, $fx['activities'][0]->id, $fx['account']->id, $lines);
            $this->fail('A row with quantity 0 must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }
        $this->assertSame(0, SobItem::count(), 'all or nothing');
        $this->expectException(ValidationException::class);
        $this->service()->addItems($plan, $fx['activities'][0]->id, $fx['account']->id, []);
    }

    public function test_the_form_posts_many_particulars_and_the_page_offers_the_add_button(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);

        $html = $this->get(route('planning.sob.show', $plan))->assertOk()->getContent();
        $this->assertStringContainsString('data-add-particulars', $html);
        $this->assertStringContainsString('name="lines[0][particulars]"', $html);

        $this->post(route('planning.sob.items.store', $plan), ['aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'lines' => $this->lines()])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, SobItem::count());
        $this->post(route('planning.sob.items.store', $plan), ['aip_activity_id' => $fx['activities'][0]->id, 'chart_of_account_id' => $fx['account']->id, 'lines' => [['particulars' => 'x']]])
            ->assertSessionHasErrors();
        $this->assertSame(2, SobItem::count());
    }

    public function test_the_activity_choices_show_no_amount_and_every_activity_is_listed(): void
    {
        $fx = SobFixture::make(['quarterAmount' => 0]);
        $this->actingAs($fx['user']);
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);

        $html = $this->get(route('planning.sob.show', $plan))->assertOk()->getContent();

        foreach ($fx['activities'] as $activity) {
            $this->assertStringContainsString('<option value="'.$activity->id.'"', $html, 'an activity without an amount is still chosen');
        }
        $this->assertStringNotContainsString('(AIP 1ST QUARTER', $html);
        $this->assertStringNotContainsString('that has an actual budget', $html);
    }

    public function test_over_aip_is_only_checked_where_the_aip_has_an_amount(): void
    {
        $fx = SobFixture::make(['quarterAmount' => 0]);
        $this->actingAs($fx['user']);
        $plan = $this->service()->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $this->service()->addItems($plan, $fx['activities'][0]->id, $fx['account']->id, $this->lines());

        $this->assertSame([], $this->service()->overAip($plan->fresh()), 'no amount determined yet, so nothing to be over');
        $html = $this->get(route('planning.sob.show', $plan))->assertOk()->getContent();
        $this->assertStringNotContainsString('Over the AIP amount', $html);
        $this->assertStringNotContainsString('AIP 1ST QUARTER: ₱0.00', $html);
    }
}
