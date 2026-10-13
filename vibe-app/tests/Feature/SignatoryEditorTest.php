<?php

namespace Tests\Feature;

use App\Services\SobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SobFixture;
use Tests\TestCase;

/** One signatory editor for every planning document: who signs first, then the editor with the title above the signature, the full name and the position. */
class SignatoryEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_aip_shows_who_signs_and_says_when_a_name_is_only_a_suggestion(): void
    {
        $fx = SobFixture::make();
        $this->actingAs($fx['user']);

        $html = $this->get(route('aip.show', $fx['aip']))->assertOk()->getContent();
        $this->assertStringContainsString('Edit signatories', $html);
        $this->assertStringContainsString('Title above the signature', $html);
        $this->assertStringContainsString('Not named yet', $html);
        $this->assertStringNotContainsString('Suggested, not saved yet', $html);

        $this->put(route('aip.update', $fx['aip']), ['prepared_by_name' => 'Ana Reyes', 'prepared_by_position' => 'School Head', 'prepared_by_role' => 'Prepared by'])->assertSessionHasNoErrors();
        $saved = $this->get(route('aip.show', $fx['aip']))->getContent();
        $this->assertStringContainsString('Ana Reyes', $saved);
        $this->assertStringContainsString('Saved', $saved);

        // another document of the school has no names yet, so it is offered the ones just saved, marked as not saved
        $sob = app(SobService::class)->create($fx['school'], 2026, 1, 'MOOE', $fx['user']);
        $page = $this->get(route('planning.sob.show', $sob))->assertOk()->getContent();
        $this->assertStringContainsString('Ana Reyes', $page);
        $this->assertStringContainsString('Suggested, not saved yet', $page);
        $this->assertStringContainsString("filled in from the school's last saved planning documents", $page);
        $this->assertNull($sob->fresh()->prepared_by_name, 'a suggestion is not saved by showing it');
    }
}
