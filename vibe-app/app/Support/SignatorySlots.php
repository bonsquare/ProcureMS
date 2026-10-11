<?php

namespace App\Support;

use App\Models\SchoolStaff;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The signature slots of every official document, and who may sign them. A slot's `var` is the variable the
 * document's template already prints, so a chosen person simply replaces the default one. A slot finds its
 * default holders by `role` ([group, role name] for SchoolStaff::hasRole) and/or by `needle`, a lowercase text
 * found in the staff member's document role or position, the same lookups the print methods use.
 */
final class SignatorySlots
{
    private const BAC_CHAIR = ['key' => 'bac_chair', 'label' => 'BAC Chairperson', 'var' => 'bacChair', 'role' => ['bac_role', 'BAC Chairperson'], 'needle' => null];

    private const SCHOOL_HEAD = ['key' => 'school_head', 'label' => 'School Head', 'var' => 'schoolHead', 'role' => ['procurement_role', 'Approver'], 'needle' => 'school head'];

    private const REQUESTING_OFFICER = ['key' => 'requesting_officer', 'label' => 'Requesting Officer', 'var' => 'requestingOfficer', 'role' => ['procurement_role', 'Requesting Officer'], 'needle' => null];

    private const PROPERTY_OFFICER = ['key' => 'property_officer', 'label' => 'Property Officer', 'var' => 'propertyOfficer', 'role' => null, 'needle' => 'property custodian'];

    private const APPROVED_BY = ['key' => 'approved_by', 'label' => 'Approved by', 'var' => 'approver', 'role' => ['procurement_role', 'Approver'], 'needle' => 'school head'];

    public const DOCUMENTS = [
        'request_for_quotation' => [
            self::BAC_CHAIR,
            ['key' => 'bac_secretariat', 'label' => 'BAC Secretariat', 'var' => 'bacSecretariat', 'role' => ['bac_role', 'BAC Secretariat'], 'needle' => null],
        ],
        'abstract_of_bids_quotation' => [
            self::BAC_CHAIR,
            ['key' => 'bac_vice_chair', 'label' => 'BAC Vice Chairperson', 'var' => 'bacViceChair', 'role' => ['bac_role', 'BAC Vice Chairperson'], 'needle' => null],
            ['key' => 'bac_member_1', 'label' => 'BAC Member 1', 'var' => 'bacMembers', 'role' => ['bac_role', 'BAC Member'], 'needle' => null],
            ['key' => 'bac_member_2', 'label' => 'BAC Member 2', 'var' => 'bacMembers', 'role' => ['bac_role', 'BAC Member'], 'needle' => null],
            ['key' => 'bac_member_3', 'label' => 'BAC Member 3', 'var' => 'bacMembers', 'role' => ['bac_role', 'BAC Member'], 'needle' => null],
        ],
        'notice_to_award' => [self::SCHOOL_HEAD],
        'notice_to_proceed' => [self::SCHOOL_HEAD],
        'purchase_order' => [
            self::SCHOOL_HEAD,
            ['key' => 'disbursing_officer', 'label' => 'Disbursing Officer', 'var' => 'disbursingOfficer', 'role' => null, 'needle' => 'disbursing officer'],
        ],
        'inspection_acceptance_report' => [
            ['key' => 'inspection_officer', 'label' => 'Inspection Officer', 'var' => 'inspectionOfficer', 'role' => null, 'needle' => 'inspection officer'],
            self::PROPERTY_OFFICER,
        ],
        'inventory_acknowledgement_receipt_supplies' => [self::SCHOOL_HEAD],
        'requisition_issuance_slip' => [self::REQUESTING_OFFICER, self::SCHOOL_HEAD, self::PROPERTY_OFFICER],
        'inventory_custodian_slip' => [
            self::PROPERTY_OFFICER,
            ['key' => 'procurement_officer', 'label' => 'Procurement Officer', 'var' => 'procurementOfficer', 'role' => ['procurement_role', 'Procurement Officer'], 'needle' => null],
            self::REQUESTING_OFFICER,
        ],
        'property_acknowledgement_receipt' => [
            ['key' => 'prepared_by', 'label' => 'Prepared by', 'var' => 'requestingOfficer', 'role' => ['procurement_role', 'Requesting Officer'], 'needle' => null],
            self::APPROVED_BY,
        ],
        'purchase_request' => [
            ['key' => 'requested_by', 'label' => 'Requested by', 'var' => 'requestingOfficer', 'role' => ['procurement_role', 'Requesting Officer'], 'needle' => null],
            self::APPROVED_BY,
        ],
        'obligation_request' => [
            ['key' => 'requester', 'label' => 'Requester / School Head', 'var' => 'requester', 'role' => null, 'needle' => 'school head'],
            ['key' => 'budget_officer', 'label' => 'Certified by (Budget / Disbursing Officer)', 'var' => 'budgetOfficer', 'role' => null, 'needle' => 'disburs'],
        ],
        'disbursement_voucher' => [
            ['key' => 'accountant', 'label' => 'Accountant', 'var' => 'accountant', 'role' => null, 'needle' => 'accountant'],
            ['key' => 'head', 'label' => 'School Head', 'var' => 'head', 'role' => null, 'needle' => 'school head'],
        ],
    ];

    /** The ten documents made in the procurement document center; they share one set of default signers. */
    public const PROCUREMENT_DOCUMENTS = [
        'request_for_quotation', 'abstract_of_bids_quotation', 'notice_to_award', 'notice_to_proceed', 'purchase_order',
        'inspection_acceptance_report', 'inventory_acknowledgement_receipt_supplies', 'requisition_issuance_slip',
        'inventory_custodian_slip', 'property_acknowledgement_receipt',
    ];

    /**
     * Who signs by default: each template variable and the staff member the School Settings roles point to. The print
     * method and the form's "Default (Name)" option both read this, so what the form shows is what prints.
     *
     * @param  Collection<int, SchoolStaff>  $staff  the school's active staff
     * @return array<string, SchoolStaff|Collection|null>
     */
    public static function defaults(string $document, Collection $staff): array
    {
        $role = fn (string $group, string $name) => $staff->first(fn (SchoolStaff $member) => $member->hasRole($group, $name));
        $text = fn (string $field, string $needle) => $staff->first(fn (SchoolStaff $member) => str_contains(strtolower((string) $member->{$field}), $needle));
        $approver = $role('procurement_role', 'Approver');

        if ($document === 'purchase_request') {
            return [
                'requestingOfficer' => $role('procurement_role', 'Requesting Officer') ?? $role('procurement_role', 'Procurement Officer'),
                'approver' => $approver ?? $text('position', 'school head'),
            ];
        }

        if ($document === 'obligation_request') {
            $find = fn (string $needle) => $staff->first(fn (SchoolStaff $member) => str_contains(strtolower($member->document_role.' '.$member->position), $needle));

            return [
                'requester' => $find('school head') ?? $find('head'),
                'budgetOfficer' => $find('disburs') ?? $find('budget'),
            ];
        }

        if (! in_array($document, self::PROCUREMENT_DOCUMENTS, true)) {
            return [];
        }

        return [
            'requestingOfficer' => $role('procurement_role', 'Requesting Officer'),
            'procurementOfficer' => $role('procurement_role', 'Procurement Officer'),
            'approver' => $approver,
            'canvasser' => $role('procurement_role', 'Canvasser'),
            'schoolHead' => $text('position', 'school head') ?? $approver,
            'bacChair' => $role('bac_role', 'BAC Chairperson'),
            'bacViceChair' => $role('bac_role', 'BAC Vice Chairperson'),
            'bacSecretariat' => $role('bac_role', 'BAC Secretariat'),
            'bacMembers' => $staff->filter(fn (SchoolStaff $member) => $member->hasRole('bac_role', 'BAC Member'))->values(),
            'disbursingOfficer' => $text('document_role', 'disbursing officer') ?? $text('position', 'disbursing'),
            'inspectionOfficer' => $text('document_role', 'inspection officer') ?? $text('position', 'inspection'),
            'propertyOfficer' => $text('document_role', 'property custodian') ?? $text('position', 'property'),
        ];
    }

    /** The default person of one slot, out of what defaults() returned. */
    public static function defaultFor(array $slot, array $defaults): ?SchoolStaff
    {
        $value = $defaults[$slot['var']] ?? null;

        return $value instanceof Collection ? $value->get((int) substr($slot['key'], -1) - 1) : $value;
    }

    /**
     * Everything a form needs to draw the signatory selects of one document.
     *
     * @param  Collection<int, SchoolStaff>  $staff  the school's active staff
     * @param  ?array<string, array{staff_id: int, name: string, position: ?string}>  $saved
     * @return array<int, array{slot: array, default: ?SchoolStaff, candidates: Collection, saved: ?array}>
     */
    public static function panel(string $document, Collection $staff, ?array $saved): array
    {
        $defaults = self::defaults($document, $staff);

        return array_map(fn (array $slot) => [
            'slot' => $slot,
            'default' => self::defaultFor($slot, $defaults),
            'candidates' => self::candidates($staff, $slot),
            'saved' => is_array($saved[$slot['key']] ?? null) ? $saved[$slot['key']] : null,
        ], self::slots($document));
    }

    /**
     * The panel of one document for each school a form lets the user pick, keyed by school id. A form that can switch
     * schools shows only the panel of the chosen school, because each school has its own staff.
     *
     * @param  Collection<int, int>  $schoolIds
     * @param  ?array<string, array{staff_id: int, name: string, position: ?string}>  $saved  the saved choices, which belong to $savedSchoolId
     * @return array<int, array<int, array{slot: array, default: ?SchoolStaff, candidates: Collection, saved: ?array}>>
     */
    public static function panelsForSchools(string $document, Collection $schoolIds, ?int $savedSchoolId = null, ?array $saved = null): array
    {
        $staff = SchoolStaff::active()->whereIn('school_id', $schoolIds)->get()->groupBy('school_id');

        return $schoolIds->mapWithKeys(fn ($schoolId) => [
            $schoolId => self::panel($document, $staff->get($schoolId, collect()), $savedSchoolId === (int) $schoolId ? $saved : null),
        ])->all();
    }

    /** @return array<int, array{key: string, label: string, var: string, role: ?array{0: string, 1: string}, needle: ?string}> */
    public static function slots(string $document): array
    {
        return self::DOCUMENTS[$document] ?? [];
    }

    /**
     * The active staff offered for one slot: the people who hold its role first, then everyone else, each by name.
     *
     * @param  Collection<int, SchoolStaff>  $staff
     * @param  array{role: ?array{0: string, 1: string}, needle: ?string}  $slot
     * @return Collection<int, SchoolStaff>
     */
    public static function candidates(Collection $staff, array $slot): Collection
    {
        $active = $staff->filter(fn (SchoolStaff $member) => $member->is_active);
        $byName = fn (SchoolStaff $member) => mb_strtolower($member->name);

        return $active->filter(fn (SchoolStaff $member) => self::holds($member, $slot))->sortBy($byName)
            ->concat($active->reject(fn (SchoolStaff $member) => self::holds($member, $slot))->sortBy($byName))
            ->values();
    }

    /**
     * The chosen people as snapshots, for the slots of this document that were filled in. Blank values, unknown
     * slots and input that is not an array are ignored; anyone who is not an active staff member of the school is refused.
     *
     * @return array<string, array{staff_id: int, name: string, position: ?string}>
     */
    public static function validated(string $document, mixed $input, int $schoolId): array
    {
        if (! is_array($input)) {
            return [];
        }

        $chosen = [];
        foreach (self::slots($document) as $slot) {
            $value = $input[$slot['key']] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                $chosen[$slot['key']] = trim((string) $value);
            }
        }

        $ids = collect($chosen)->filter(fn (string $value) => ctype_digit($value))->map(fn (string $value) => (int) $value)->unique()->values();
        $found = SchoolStaff::active()->where('school_id', $schoolId)->whereIn('id', $ids)->get()->keyBy('id');

        $snapshots = [];
        $errors = [];
        foreach ($chosen as $key => $value) {
            $member = ctype_digit($value) ? $found->get((int) $value) : null;
            if (! $member) {
                $errors['signatories.'.$key] = 'That person cannot sign for this school.';

                continue;
            }
            $snapshots[$key] = ['staff_id' => $member->id, 'name' => $member->name, 'position' => $member->position];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $snapshots;
    }

    /**
     * The template variables with each saved choice put in place of the default person. The BAC members are a list
     * the Abstract reads by row, so a choice replaces only its own row and the others keep theirs.
     *
     * @param  array<string, mixed>  $variables
     * @param  ?array<string, array{name?: string, position?: ?string}>  $saved
     * @return array<string, mixed>
     */
    public static function overlay(string $document, array $variables, ?array $saved): array
    {
        foreach (self::slots($document) as $slot) {
            $snapshot = $saved[$slot['key']] ?? null;
            if (! is_array($snapshot)) {
                continue;
            }

            if ($slot['var'] === 'bacMembers') {
                $members = ($variables['bacMembers'] ?? collect())->all();
                $members[(int) substr($slot['key'], -1) - 1] = self::toStaff($snapshot);
                $variables['bacMembers'] = collect($members);

                continue;
            }

            $variables[$slot['var']] = self::toStaff($snapshot);
        }

        return $variables;
    }

    /** An unsaved staff record carrying a snapshot's name and position, which is all the templates read. */
    public static function toStaff(array $snapshot): SchoolStaff
    {
        return new SchoolStaff(['name' => $snapshot['name'] ?? '', 'position' => $snapshot['position'] ?? null]);
    }

    private static function holds(SchoolStaff $member, array $slot): bool
    {
        if ($slot['role'] && $member->hasRole($slot['role'][0], $slot['role'][1])) {
            return true;
        }

        return $slot['needle'] && str_contains(mb_strtolower($member->document_role.' '.$member->position), $slot['needle']);
    }
}
