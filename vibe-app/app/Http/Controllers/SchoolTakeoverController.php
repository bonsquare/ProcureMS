<?php

namespace App\Http\Controllers;

use App\Models\SchoolTakeoverRequest;
use App\Services\SchoolTakeoverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The master user's decision on a new person who asked to take over a vacant school. */
class SchoolTakeoverController extends Controller
{
    public function __construct(private SchoolTakeoverService $takeovers) {}

    public function approve(Request $request, SchoolTakeoverRequest $takeover): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('transfers'), 403);
        $data = $request->validate(['decision_note' => ['nullable', 'string', 'max:500'], 'school_id' => ['nullable', 'integer']]);
        $this->takeovers->approve($takeover, $request->user(), $data['decision_note'] ?? null, isset($data['school_id']) ? (int) $data['school_id'] : null);
        $takeover->refresh();

        return $this->back($takeover, $takeover->user?->name.' now manages '.$takeover->school?->name.'.');
    }

    public function decline(Request $request, SchoolTakeoverRequest $takeover): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('transfers'), 403);
        $this->takeovers->decline($takeover, $request->user(), $this->note($request));

        return $this->back($takeover, 'Takeover request declined.');
    }

    private function note(Request $request): ?string
    {
        return $request->validate(['decision_note' => ['nullable', 'string', 'max:500']])['decision_note'] ?? null;
    }

    private function back(SchoolTakeoverRequest $takeover, string $message): RedirectResponse
    {
        return redirect()->route('transfer-requests')->with('success', $message);
    }
}
