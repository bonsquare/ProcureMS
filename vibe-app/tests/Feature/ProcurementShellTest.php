<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ProcurementShellTest extends TestCase
{
    public function test_shared_shell_exposes_accessible_navigation_and_active_area(): void
    {
        $html = Blade::render(<<<'BLADE'
            @extends('layouts.procurement')
            @section('title', 'Document Center')
            @section('page-title', 'Document Center')
            @section('content')<p>Workspace content</p>@endsection
        BLADE, ['activeProcurementArea' => 'documents']);

        $this->assertSame(1, substr_count($html, 'aria-label="Main navigation"'));
        $this->assertSame(1, substr_count($html, 'aria-label="Procurement areas"'));
        $this->assertStringContainsString('href="#main-content"', $html);
        $this->assertStringContainsString('class="civic-skip-link"', $html);
        $this->assertMatchesRegularExpression('/href="[^\"]*procurement\/documents"[^>]*aria-current="page"/', $html);
        $this->assertStringContainsString('aria-label="Open main navigation"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('aria-controls="civic-navigation"', $html);
        $this->assertStringContainsString('/css/procurement.css', $html);
        $this->assertStringContainsString('id="main-content"', $html);
    }

    public function test_status_badge_always_renders_a_text_label(): void
    {
        $html = Blade::render('<x-procurement.status-badge label="Needs approval" tone="attention" />');

        $this->assertStringContainsString('Needs approval', $html);
        $this->assertStringContainsString('civic-status--attention', $html);
        $this->assertStringContainsString('aria-label="Status: Needs approval"', $html);
    }

    public function test_stage_rail_exposes_each_stage_state_as_text(): void
    {
        $stages = [
            ['key' => 'request', 'label' => 'Request', 'state' => 'complete', 'tone' => 'verified', 'accessible_label' => 'Request completed'],
            ['key' => 'approval', 'label' => 'Approval', 'state' => 'current', 'tone' => 'action', 'accessible_label' => 'Approval current stage'],
            ['key' => 'canvass', 'label' => 'Canvass', 'state' => 'pending', 'tone' => 'neutral', 'accessible_label' => 'Canvass pending'],
        ];

        $html = Blade::render('<x-procurement.stage-rail :stages="$stages" />', compact('stages'));

        $this->assertStringContainsString('aria-label="Procurement progress"', $html);
        $this->assertStringContainsString('Request completed', $html);
        $this->assertStringContainsString('Approval current stage', $html);
        $this->assertStringContainsString('Canvass pending', $html);
    }
}
