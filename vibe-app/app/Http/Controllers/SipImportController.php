<?php

namespace App\Http\Controllers;

use App\Models\Aip;
use App\Models\AuditLog;
use App\Models\School;
use App\Services\FiscalYearService;
use App\Services\SipImportService;
use App\Services\SipWorkbookReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Import of a School Improvement Plan from the school's own Excel file: upload, edit in a preview, confirm. */
class SipImportController extends Controller
{
    private const TOKEN_MINUTES = 30;

    public function preview(Request $request, SipWorkbookReader $reader, SipImportService $service): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'school_id' => ['required', 'integer', Rule::in($this->schoolIds($request))],
            'file' => ['required', 'file', 'extensions:xlsx', 'max:5120'],
        ]);

        $path = $request->file('file')->getRealPath();
        $result = $reader->read($path);
        @unlink($path);

        $plan = $result['plan'];
        $issues = $result['issues'];
        if ($plan['projects'] !== []) {
            $issues = array_merge($issues, $service->validate($plan));
        }

        $token = Str::random(40);
        $this->remember($token, [
            'user_id' => $request->user()->id,
            'school_id' => (int) $data['school_id'],
            'plan' => $plan,
            'issues' => $issues,
            'original' => $this->fingerprint($plan),
        ]);

        return redirect()->route('planning.sip.import.show', $token);
    }

    public function show(Request $request, string $token): View|RedirectResponse
    {
        $this->authorizeManage($request);
        $entry = $this->entry($request, $token);
        if ($entry instanceof RedirectResponse) {
            return $entry;
        }
        $school = School::query()->findOrFail($entry['school_id']);

        return view('sip-import-preview', [
            'token' => $token,
            'school' => $school,
            'state' => ['plan' => $entry['plan']['plan'], 'signatories' => $entry['plan']['signatories'], 'projects' => $entry['plan']['projects'], 'issues' => $entry['issues']],
            'schoolNameWarning' => $this->schoolNameWarning($entry['plan']['plan']['school_name'] ?? null, $school),
            'pillars' => Aip::PILLARS,
        ]);
    }

    public function store(Request $request, string $token, SipImportService $service, FiscalYearService $fiscalYears): RedirectResponse
    {
        $this->authorizeManage($request);
        $entry = $this->entry($request, $token);
        if ($entry instanceof RedirectResponse) {
            return $entry;
        }
        $request->validate(['payload' => ['required', 'string', 'max:2000000']]);

        $edited = json_decode((string) $request->input('payload'), true);
        if (! is_array($edited) || ! isset($edited['projects']) || ! is_array($edited['projects'])) {
            throw ValidationException::withMessages(['payload' => 'The edited plan could not be read. Reload the preview and try again.']);
        }

        // The year and period come from the stored preview; everything else is the user's edits, cleaned and then validated again.
        $plan = [
            'plan' => $entry['plan']['plan'],
            'signatories' => $this->cleanSignatories((array) ($edited['signatories'] ?? [])),
            'projects' => $this->cleanProjects($edited['projects']),
        ];

        $issues = $service->validate($plan);
        if (collect($issues)->contains('level', 'error')) {
            $entry['plan'] = $plan;
            $entry['issues'] = $issues;
            $this->remember($token, $entry);

            return redirect()->route('planning.sip.import.show', $token)->with('error', 'Some things still need fixing. They are marked below; nothing was saved.');
        }

        $school = School::query()->findOrFail($entry['school_id']);
        try {
            $fiscalYears->assertOpen((int) $school->organization_id, (int) $plan['plan']['start_year']);
            $result = $service->save($school, $plan, $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('planning.sip.import.show', $token)->with('error', collect($e->errors())->flatten()->implode(' '));
        }

        Cache::forget($this->key($token));
        AuditLog::create([
            'user_id' => $request->user()->id,
            'school_id' => $school->id,
            'action' => 'sip_imported',
            'auditable_type' => School::class,
            'auditable_id' => $school->id,
            'metadata' => $result + ['edited' => $this->fingerprint($plan) !== $entry['original'], 'period' => $plan['plan']['planning_period']],
        ]);

        return redirect(route('planning', ['school_id' => $school->id, 'year' => $plan['plan']['start_year']]).'#sip')
            ->with('success', "SIP {$plan['plan']['planning_period']} imported: {$result['programs']} programs and {$result['activities']} activities.");
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->hasPermission('planning.manage'), 403, 'You do not have permission to manage planning records.');
    }

    /** @return array<int, int> the schools this user may import for (the same rule as the Planning page) */
    private function schoolIds(Request $request): array
    {
        $user = $request->user();

        return ($user->seesAllSchools() || $user->organization_id
            ? School::query()->pluck('id')
            : School::query()->whereKey($user->school_id)->pluck('id'))->all();
    }

    /** The stored preview of this user, or a redirect with a clear message when it has expired or was used. */
    private function entry(Request $request, string $token): array|RedirectResponse
    {
        $entry = Cache::get($this->key($token));
        if ($entry === null) {
            return redirect()->route('planning')->with('error', 'This import preview has expired or was already used. Upload the Excel file again.');
        }
        abort_unless($entry['user_id'] === $request->user()->id && in_array($entry['school_id'], $this->schoolIds($request), true), 404);

        return $entry;
    }

    private function remember(string $token, array $entry): void
    {
        Cache::put($this->key($token), $entry, now()->addMinutes(self::TOKEN_MINUTES));
    }

    private function key(string $token): string
    {
        return 'sip-import:'.$token;
    }

    private function schoolNameWarning(?string $fileName, School $school): ?string
    {
        if ($fileName && mb_strtolower(trim($fileName)) !== mb_strtolower(trim($school->name))) {
            return "The file names \"{$fileName}\" but you are importing into {$school->name}.";
        }

        return null;
    }

    /** Fixed keys, text trimmed; numbers that are not numbers are kept as text so validation can reject them. */
    private function cleanProjects(array $projects): array
    {
        $text = fn ($value) => is_scalar($value) ? trim((string) $value) : '';
        $number = fn ($value) => $value === null || $value === '' ? null : (is_numeric($value) ? (float) $value : (is_scalar($value) ? (string) $value : 'invalid'));

        return array_values(array_map(function ($project) use ($text, $number) {
            $project = (array) $project;

            return [
                'row' => isset($project['row']) ? (int) $project['row'] : null,
                'pillar' => $text($project['pillar'] ?? ''),
                'kra' => $text($project['kra'] ?? ''),
                'organizational_outcome' => $text($project['organizational_outcome'] ?? ''),
                'strategy' => $text($project['strategy'] ?? ''),
                'five_point_agenda' => $text($project['five_point_agenda'] ?? ''),
                'project' => $text($project['project'] ?? ''),
                'source_of_fund' => $text($project['source_of_fund'] ?? ''),
                'activities' => array_values(array_map(function ($activity) use ($text, $number) {
                    $activity = (array) $activity;

                    return [
                        'row' => isset($activity['row']) ? (int) $activity['row'] : null,
                        'activity' => $text($activity['activity'] ?? ''),
                        'physical' => array_map($number, array_pad(array_slice((array) ($activity['physical'] ?? []), 0, 3), 3, null)),
                        'financial' => array_map(fn ($value) => $number($value) ?? 0.0, array_pad(array_slice((array) ($activity['financial'] ?? []), 0, 3), 3, null)),
                        'responsible_person' => $text($activity['responsible_person'] ?? ''),
                        'remarks' => $text($activity['remarks'] ?? ''),
                    ];
                }, is_array($project['activities'] ?? null) ? $project['activities'] : [])),
            ];
        }, $projects));
    }

    private function cleanSignatories(array $signatories): array
    {
        $clean = [];
        foreach (['prepared_by', 'recommended_by', 'approved_by'] as $prefix) {
            foreach (['_name', '_position'] as $suffix) {
                $value = $signatories[$prefix.$suffix] ?? null;
                $clean[$prefix.$suffix] = is_scalar($value) && trim((string) $value) !== '' ? mb_substr(trim((string) $value), 0, 255) : null;
            }
        }

        return $clean;
    }

    /** Identifies the content of a plan (without Excel row numbers) to tell whether the user changed anything. */
    private function fingerprint(array $plan): string
    {
        $projects = $this->cleanProjects($plan['projects']);
        foreach ($projects as &$project) {
            unset($project['row']);
            foreach ($project['activities'] as &$activity) {
                unset($activity['row']);
            }
        }

        return md5(json_encode([$this->cleanSignatories($plan['signatories']), $projects]));
    }
}
