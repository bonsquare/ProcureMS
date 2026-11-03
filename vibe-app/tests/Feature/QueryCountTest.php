<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** The busiest pages keep a fixed number of queries however many rows they show, and no schema lookups (the speed fix of 2026-11-03). */
class QueryCountTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['home', 'planning', 'budget', 'budget.allocation', 'liquidation'];

    /** A little above what each page runs today (dashboard 55, planning 32, budget 26, allotment registry 21, liquidation 18). */
    private const LIMITS = ['home' => 62, 'planning' => 38, 'budget' => 32, 'budget.allocation' => 26, 'liquidation' => 24];

    /** @return array<string, int> the queries each page ran */
    private function countFor(array $fx): array
    {
        $this->actingAs($fx['user']);
        $counts = [];
        foreach (self::PAGES as $name) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route($name))->assertOk();
            $counts[$name] = count(DB::getQueryLog());
            $counts[$name.':schema'] = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'sqlite_master') || str_contains($q['query'], 'not "notnull"'))->count();
        }

        return $counts;
    }

    public function test_the_queries_stay_fixed_when_the_rows_grow_and_stay_under_the_limits(): void
    {
        $small = SobFixture::make(['activityCount' => 2]);
        $large = SobFixture::make(['activityCount' => 30]);
        $this->countFor($small); // the first visit asks the database for each table's columns once; the page counts below are the steady state

        $few = $this->countFor($small);
        $many = $this->countFor($large);

        foreach (self::PAGES as $name) {
            $this->assertLessThanOrEqual($few[$name] + 2, $many[$name], $name.' runs more queries when it shows more rows (an N+1)');
            $this->assertLessThanOrEqual(self::LIMITS[$name], $few[$name], $name.' runs more queries than before');
            $this->assertLessThanOrEqual(3, $few[$name.':schema'], $name.' asks the database for column lists again');
        }
    }
}
