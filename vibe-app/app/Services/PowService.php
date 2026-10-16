<?php

namespace App\Services;

use App\Models\AipActivity;
use App\Models\PowPlan;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\SobItem;
use App\Models\SobPlan;
use App\Models\User;
use App\Support\SignatorySlots;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** The Program of Works: its lines are made from the SOB items of one program and activity, then saved as the POW's own copy. */
class PowService
{
    public const SIGNATURE_SLOTS = ['prepared_by', 'recommended_by', 'approved_by', 'division_superintendent'];

    /** @return Collection<int, SobItem> the SOB items with their activity and program loaded, in the order entered */
    private function items(SobPlan $plan): Collection
    {
        return $plan->items()->with('activity.kra')->orderBy('id')->get();
    }

    private function programOf(SobItem $item): string
    {
        return (string) ($item->activity?->kra?->program ?? '');
    }

    /** @return Collection<int, SobItem> */
    private function itemsOf(SobPlan $plan, string $program, ?int $activityId): Collection
    {
        return $this->items($plan)->filter(fn (SobItem $item) => $this->programOf($item) === $program && ($activityId === null || (int) $item->aip_activity_id === $activityId))->values();
    }

    /** @return Collection<int, string> the programs that have items in the SOB */
    public function programs(SobPlan $plan): Collection
    {
        return $this->items($plan)->map(fn (SobItem $item) => $this->programOf($item))->filter(fn (string $program) => $program !== '')->unique()->values();
    }

    /** @return Collection<int, AipActivity> the activities of the program that have items in the SOB */
    public function activities(SobPlan $plan, string $program): Collection
    {
        return $this->items($plan)->filter(fn (SobItem $item) => $this->programOf($item) === $program && $item->activity)->map->activity->unique('id')->values();
    }

    /**
     * The material lines of a program and activity (every activity of the program when $activityId is null); the other lines are not materials.
     *
     * @return Collection<int, array{sob_item_id: int, description: string, unit: ?string, quantity: float, unit_cost: float, amount: float}>
     */
    public function materials(SobPlan $plan, string $program, ?int $activityId): Collection
    {
        return $this->itemsOf($plan, $program, $activityId)->reject->is_extra->map(function (SobItem $item) {
            $quantity = round((float) $item->frequency * (float) $item->quantity, 2);

            return [
                'sob_item_id' => $item->id, 'description' => $item->particulars, 'unit' => $item->unit, 'quantity' => $quantity,
                'unit_cost' => (float) $item->unit_cost, 'amount' => round($quantity * (float) $item->unit_cost, 2),
            ];
        })->values();
    }

    /** The sum of the other lines (for example 35% Labor) of the program and activity: the starting value of the POW's labor cost. */
    public function labor(SobPlan $plan, string $program, ?int $activityId): float
    {
        return round((float) $this->itemsOf($plan, $program, $activityId)->filter->is_extra->sum('amount'), 2);
    }

    /** @param  array<string, mixed>  $data program, activity_id, project_title, lines, labor_cost, dates, manpower, equipment, school_address, signatories */
    public function create(SobPlan $plan, array $data, User $user): PowPlan
    {
        $data = $this->validated($data, $plan);
        if ($data['activity_id'] !== null && ! $this->activities($plan, $data['program'])->contains('id', $data['activity_id'])) {
            throw ValidationException::withMessages(['activity_id' => 'That activity has no items in this SOB.']);
        }

        return DB::transaction(function () use ($plan, $data, $user) {
            $pow = PowPlan::create([
                'organization_id' => $plan->organization_id, 'school_id' => $plan->school_id, 'sob_plan_id' => $plan->id, 'aip_activity_id' => $data['activity_id'],
                'program' => $data['program'], 'created_by' => $user->id,
            ] + $this->fields($data, $plan->school));
            $this->writeLines($pow, $data['lines'], $plan);

            return $pow->fresh('items');
        });
    }

    /** @param  array<string, mixed>  $data the same keys as create(); the program and activity of the POW stay as they were */
    public function update(PowPlan $pow, array $data, User $user): PowPlan
    {
        $data = $this->validated($data + ['program' => $pow->program], $pow->sobPlan);

        return DB::transaction(function () use ($pow, $data) {
            $pow->update($this->fields($data, $pow->school));
            $pow->items()->delete();
            $this->writeLines($pow, $data['lines'], $pow->sobPlan);

            return $pow->fresh('items');
        });
    }

    /** @return array{materials: float, labor: float, total: float} */
    public function totals(PowPlan $pow): array
    {
        $materials = round((float) $pow->items()->sum('amount'), 2);
        $labor = round((float) $pow->labor_cost, 2);

        return ['materials' => $materials, 'labor' => $labor, 'total' => round($materials + $labor, 2)];
    }

    /**
     * The signatures a new POW starts with: Prepared by is the school's School Head; the other slots are what the school's latest POW had.
     *
     * @return array<string, array{name: string, position: string}>
     */
    public function defaultSignatories(School $school): array
    {
        $blank = ['name' => '', 'position' => ''];
        $found = array_fill_keys(self::SIGNATURE_SLOTS, $blank);
        $head = SignatorySlots::defaults('notice_to_award', SchoolStaff::active()->where('school_id', $school->id)->get())['schoolHead'] ?? null;
        $name = $head?->name ?: (string) $school->school_head;
        if ($name !== '') {
            $found['prepared_by'] = ['name' => $name, 'position' => $head?->position ?: 'School Head'];
        }
        foreach (PowPlan::query()->where('school_id', $school->id)->latest('updated_at')->latest('id')->get(['signatories']) as $pow) {
            foreach (['recommended_by', 'approved_by', 'division_superintendent'] as $slot) {
                if ($found[$slot]['name'] === '' && filled($pow->signatories[$slot]['name'] ?? null)) {
                    $found[$slot] = ['name' => (string) $pow->signatories[$slot]['name'], 'position' => (string) ($pow->signatories[$slot]['position'] ?? '')];
                }
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validated(array $data, SobPlan $plan): array
    {
        $data['lines'] = array_values(array_filter((array) ($data['lines'] ?? []), 'is_array'));
        $validator = Validator::make($data, [
            'program' => ['required', 'string', 'max:255'], 'activity_id' => ['nullable', 'integer'], 'project_title' => ['required', 'string', 'max:255'],
            'lines.*.description' => ['required', 'string', 'max:255'], 'lines.*.unit' => ['nullable', 'string', 'max:50'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'], 'lines.*.unit_cost' => ['required', 'numeric', 'min:0'], 'lines.*.sob_item_id' => ['nullable', 'integer'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'], 'school_address' => ['nullable', 'string', 'max:255'],
            'pow_date' => ['nullable', 'date'], 'date_started' => ['nullable', 'date'], 'date_completed' => ['nullable', 'date'],
            'manpower' => ['nullable', 'string', 'max:2000'], 'equipment' => ['nullable', 'string', 'max:2000'], 'signatories' => ['nullable', 'array'],
        ]);
        $validator->after(function ($validator) use ($data) {
            if (filled($data['date_started'] ?? null) && filled($data['date_completed'] ?? null) && strtotime((string) $data['date_completed']) < strtotime((string) $data['date_started'])) {
                $validator->errors()->add('date_completed', 'Date of Completion cannot be before Date Started.');
            }
            if ($data['lines'] === [] && (float) ($data['labor_cost'] ?? 0) <= 0) {
                $validator->errors()->add('lines', 'Nothing to put in the POW: add at least one material line or a labor cost.');
            }
        });
        $validator->validate();

        $data['activity_id'] = filled($data['activity_id'] ?? null) ? (int) $data['activity_id'] : null;
        $data['labor_cost'] = round((float) ($data['labor_cost'] ?? 0), 2);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> the fields of the POW itself
     */
    private function fields(array $data, ?School $school): array
    {
        $signatories = [];
        foreach (self::SIGNATURE_SLOTS as $slot) {
            $signatories[$slot] = ['name' => trim((string) ($data['signatories'][$slot]['name'] ?? '')), 'position' => trim((string) ($data['signatories'][$slot]['position'] ?? ''))];
        }

        return [
            'project_title' => trim($data['project_title']), 'school_address' => filled($data['school_address'] ?? null) ? trim($data['school_address']) : $school?->address,
            'pow_date' => $data['pow_date'] ?? null, 'date_started' => $data['date_started'] ?? null, 'date_completed' => $data['date_completed'] ?? null,
            'manpower' => $data['manpower'] ?? null, 'equipment' => $data['equipment'] ?? null, 'labor_cost' => $data['labor_cost'], 'signatories' => $signatories,
        ];
    }

    /** @param  array<int, array<string, mixed>>  $lines */
    private function writeLines(PowPlan $pow, array $lines, SobPlan $plan): void
    {
        $known = $plan->items()->pluck('id')->all();
        foreach ($lines as $index => $line) {
            $quantity = round((float) $line['quantity'], 2);
            $cost = round((float) $line['unit_cost'], 2);
            $pow->items()->create([
                'organization_id' => $pow->organization_id, 'description' => trim($line['description']), 'unit' => filled($line['unit'] ?? null) ? trim($line['unit']) : null,
                'quantity' => $quantity, 'unit_cost' => $cost, 'amount' => round($quantity * $cost, 2), 'sort' => $index + 1,
                'sob_item_id' => in_array((int) ($line['sob_item_id'] ?? 0), $known, true) ? (int) $line['sob_item_id'] : null,
            ]);
        }
    }
}
