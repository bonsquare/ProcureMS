<?php

namespace App\Http\Controllers;

use App\Models\AgencySetting;
use App\Models\AuditLog;
use App\Models\BudgetAllocation;
use App\Models\LiquidationReport;
use App\Models\Organization;
use App\Models\ProcurementDocument;
use App\Models\ProcurementRequest;
use App\Models\School;
use App\Models\SchoolStaff;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AppItemLinkService;
use App\Services\BudgetService;
use App\Services\DocumentNumberService;
use App\Services\FiscalYearService;
use App\Services\MasterTransactionService;
use App\Services\ProcurementWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HomeController extends Controller
{
    private function isMasterUser(?User $user = null): bool
    {
        $user ??= request()->user();

        return $user?->role === 'master_user';
    }

    private function scopedSchoolIds(?User $user = null)
    {
        $user ??= request()->user();

        if ($this->isMasterUser($user)) {
            return School::query()->pluck('id');
        }

        return Schema::hasColumn('schools', 'organization_id') && $user?->organization_id
            ? School::query()->where('organization_id', $user->organization_id)->pluck('id')
            : School::query()->whereKey($user?->school_id)->pluck('id');
    }

    private function authorizeSchoolAccess(?int $schoolId): void
    {
        $user = request()->user();
        abort_if(! $this->isMasterUser() && ! School::query()
            ->whereKey($schoolId)
            ->when(
                Schema::hasColumn('schools', 'organization_id') && $user?->organization_id,
                fn ($query) => $query->where('organization_id', $user->organization_id),
                fn ($query) => $query->whereKey($user?->school_id)
            )
            ->exists(), 403);
    }

    private function authorizeProcurementAccess(ProcurementRequest $procurementRequest): void
    {
        $this->authorizeSchoolAccess($procurementRequest->school_id);
    }

    private function authorizeLiquidationAccess(LiquidationReport $liquidationReport): void
    {
        $this->authorizeSchoolAccess($liquidationReport->school_id);
    }

    private function organizationIdForSchool(int $schoolId): int
    {
        $organizationId = School::withoutGlobalScopes()->whereKey($schoolId)->value('organization_id');
        abort_unless($organizationId, 422, 'The selected school is not assigned to an organization.');

        return (int) $organizationId;
    }

    public function index()
    {
        $user = request()->user();
        $schools = School::withCount('users')
            ->with(['subscriptions' => fn ($query) => $query->latest()])
            ->when(! $this->isMasterUser($user), fn ($query) => $query->whereIn('id', $this->scopedSchoolIds($user)))
            ->latest()
            ->get();
        $schoolIds = $schools->pluck('id');
        $pendingPreRegistrations = $this->isMasterUser($user)
            ? School::with(['users' => fn ($query) => $query->oldest()])
                ->where('status', 'inactive')
                ->orderBy('created_at')
                ->get()
            : collect();

        return view('home', [
            'schools' => $schools,
            'pendingPreRegistrations' => $pendingPreRegistrations,
            'totalSchools' => $schools->count(),
            'activeSchools' => $schools->where('status', 'active')->count(),
            'totalUsers' => User::when(! $this->isMasterUser($user), fn ($query) => $query->whereIn('school_id', $schoolIds))->count(),
            'activeSubscriptions' => Subscription::where('status', 'active')->whereIn('school_id', $schoolIds)->count(),
            'auditLogs' => AuditLog::with(['user', 'school'])
                ->when(! $this->isMasterUser($user), fn ($query) => $query->whereIn('school_id', $schoolIds))
                ->latest()
                ->take(10)
                ->get(),
            'pendingProcurements' => ProcurementRequest::whereIn('school_id', $schoolIds)->whereIn('status', ['submitted', 'pending_approval'])->count(),
            'isMasterUser' => $this->isMasterUser($user),
        ]);
    }

    public function dashboardDiagnostics()
    {
        $startedAt = microtime(true);
        DB::select('select 1');

        return response()->json([
            'database' => 'Operational',
            'database_latency_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            'storage' => is_writable(storage_path()) ? 'Writable' : 'Needs attention',
            'cache' => 'Operational',
            'checked_at' => now()->format('M d, Y h:i:s A'),
        ]);
    }

    public function exportAuditLogs()
    {
        $logs = AuditLog::with(['user', 'school'])
            ->when(! $this->isMasterUser(), fn ($query) => $query->whereIn('school_id', $this->scopedSchoolIds()))
            ->latest()
            ->get();

        return response()->streamDownload(function () use ($logs) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'User', 'School', 'Action', 'Record Type', 'Record ID']);
            foreach ($logs as $log) {
                fputcsv($output, [
                    $log->created_at?->format('Y-m-d H:i:s'),
                    $log->user?->name ?? 'System',
                    $log->school?->name ?? 'Agency-wide',
                    $log->action,
                    $log->auditable_type,
                    $log->auditable_id,
                ]);
            }
            fclose($output);
        }, 'audit-logs-'.now()->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function storeSchool(Request $request)
    {
        abort_unless($this->isMasterUser(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'school_type' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'division' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'in:active,inactive'],
            'system_user_name' => ['required', 'string', 'max:255'],
            'system_user_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'system_user_role' => ['required', 'in:'.implode(',', array_keys(User::ROLES))],
            'system_user_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $actorId = $request->user()?->id;

        DB::transaction(function () use ($data, $actorId) {
            $organization = Organization::create([
                'name' => $data['name'],
                'slug' => 'org-'.Str::lower(Str::random(12)),
                'status' => $data['status'] === 'active' ? 'active' : 'pending',
                'fiscal_year' => now()->year,
            ]);
            $organization->update(['organization_code' => sprintf('ORG-%06d', $organization->id)]);
            $schoolData = collect($data)->except([
                'system_user_name',
                'system_user_email',
                'system_user_role',
                'system_user_password',
                'system_user_password_confirmation',
            ])->all();
            $schoolData['organization_id'] = $organization->id;

            $nextNumber = max(1000, ((int) School::max('id')) + 1000);
            do {
                $schoolData['code'] = 'SCH-'.$nextNumber++;
            } while (School::where('code', $schoolData['code'])->exists());

            $school = School::create($schoolData);

            $systemUser = User::create([
                'name' => $data['system_user_name'],
                'email' => $data['system_user_email'],
                'password' => $data['system_user_password'],
                'role' => $data['system_user_role'],
                'organization_id' => $organization->id,
                'school_id' => $school->id,
                'position' => 'System User',
            ]);

            Subscription::create([
                'organization_id' => $organization->id,
                'school_id' => $school->id,
                'plan' => 'trial',
                'billing_cycle' => 'monthly',
                'amount' => 0,
                'payment_status' => 'pending',
                'status' => 'trial',
                'starts_at' => now(),
                'subscription_end' => now()->addDays(30),
            ]);

            AuditLog::create([
                'user_id' => $actorId,
                'school_id' => $school->id,
                'action' => 'created_school_and_system_user',
                'auditable_type' => School::class,
                'auditable_id' => $school->id,
                'metadata' => ['system_user_id' => $systemUser->id],
            ]);
        });

        return redirect()->route('home')->with('success', 'School and system user added successfully.');
    }

    public function procurement(ProcurementWorkspaceService $workspaceService)
    {
        $user = request()->user();
        $procurementRequests = ProcurementRequest::with('school', 'requester', 'liquidationReports', 'transaction', 'documents', 'items')
            ->whereIn('school_id', $this->scopedSchoolIds())
            ->latest()
            ->get();

        $procurementRequests->each(fn (ProcurementRequest $request) => $request->setAttribute('workspace', $workspaceService->present($request)));

        $requests = $procurementRequests->map(fn (ProcurementRequest $request) => [
            'record_id' => $request->id,
            'ors' => $request->liquidationReports->map(fn ($r) => ['number' => $r->ors_number ?: $r->report_number, 'status' => $r->status])->all(),
            'id' => $request->request_number,
            'title' => $request->title,
            'transaction_id' => $request->master_transaction_id,
            'transaction_number' => $request->transaction?->transaction_number,
            'school' => $request->school?->name ?? 'Unassigned',
            'by' => $request->requester?->name ?? 'System User',
            'amount' => '₱'.number_format((float) $request->amount, 2),
            'status' => str($request->status)->replace('_', ' ')->title()->toString(),
            'tone' => in_array($request->status, ['pending_approval', 'returned']) ? 'error' : ($request->status === 'approved' ? 'secondary' : 'primary'),
            'date' => optional($request->requested_at ?? $request->created_at)->format('M d, Y'),
        ])->all();

        return view('procurement', [
            'requests' => $requests,
            'isMasterUser' => $this->isMasterUser($user),
            'currentSchoolName' => $this->isMasterUser($user) ? null : $user?->school?->name,
            'procurementMetrics' => [
                'total' => $procurementRequests->count(),
                'pending' => $procurementRequests->whereIn('status', ['submitted', 'pending_approval'])->count(),
                'forCanvass' => $procurementRequests->whereIn('status', ['for_canvass', 'submitted'])->count(),
                'completed' => $procurementRequests->whereIn('status', ['approved', 'completed'])->count(),
                'completedAmount' => $procurementRequests->whereIn('status', ['approved', 'completed'])->sum('amount'),
            ],
            'activeProcurementArea' => 'overview',
            'procurementRequests' => $procurementRequests,
            'attentionRequests' => $procurementRequests->whereIn('status', ['submitted', 'pending_approval', 'returned', 'for_canvass'])->take(5),
            'recentRequests' => $procurementRequests->take(6),
        ]);
    }

    public function procurementRequests(ProcurementWorkspaceService $workspaceService)
    {
        $search = trim((string) request('search'));
        $status = trim((string) request('status'));
        $schoolId = request()->integer('school_id');
        $procurementRequests = ProcurementRequest::with(['school', 'requester', 'liquidationReports', 'transaction', 'documents', 'items'])
            ->whereIn('school_id', $this->scopedSchoolIds())
            ->when($search !== '', fn ($builder) => $builder->where(fn ($nested) => $nested->where('request_number', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")))
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when($this->isMasterUser() && $schoolId, fn ($builder) => $builder->where('school_id', $schoolId))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $procurementRequests->getCollection()->transform(function (ProcurementRequest $request) use ($workspaceService) {
            $request->setAttribute('workspace', $workspaceService->present($request));

            return $request;
        });

        return view('procurement-requests', [
            'procurementRequests' => $procurementRequests,
            'isMasterUser' => $this->isMasterUser(),
            'currentSchoolName' => $this->isMasterUser() ? null : request()->user()?->school?->name,
            'activeProcurementArea' => 'requests',
            'schools' => $this->isMasterUser() ? School::where('status', 'active')->orderBy('name')->get() : collect(),
            'filtersApplied' => $search !== '' || $status !== '' || ($this->isMasterUser() && $schoolId),
        ]);
    }

    public function showProcurement(ProcurementRequest $procurementRequest, ProcurementWorkspaceService $workspaceService)
    {
        $this->authorizeProcurementAccess($procurementRequest);
        $procurementRequest->load(['school', 'requester', 'items', 'documents.creator', 'transaction.events.user', 'liquidationReports']);

        return view('procurement-show', [
            'procurementRequest' => $procurementRequest,
            'workspace' => $workspaceService->present($procurementRequest),
            'isMasterUser' => $this->isMasterUser(),
            'currentSchoolName' => $this->isMasterUser() ? null : request()->user()?->school?->name,
            'activeProcurementArea' => 'requests',
            'activeSection' => in_array(request('section'), ['summary', 'items', 'documents', 'activity'], true) ? request('section') : 'summary',
        ]);
    }

    public function procurementDocumentIndex()
    {
        $type = trim((string) request('type'));
        $status = trim((string) request('status'));
        $requestSearch = trim((string) request('request'));
        $schoolId = request('school_id');
        $documents = ProcurementDocument::with(['procurementRequest.school', 'creator'])
            ->whereHas('procurementRequest', fn ($query) => $query->whereIn('school_id', $this->scopedSchoolIds()))
            ->when($type !== '', fn ($query) => $query->where('document_type', $type))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($requestSearch !== '', fn ($query) => $query->whereHas('procurementRequest', fn ($requestQuery) => $requestQuery
                ->where(fn ($nested) => $nested->where('request_number', 'like', "%{$requestSearch}%")->orWhere('title', 'like', "%{$requestSearch}%"))))
            ->when($this->isMasterUser() && $schoolId, fn ($query) => $query->whereHas('procurementRequest', fn ($requestQuery) => $requestQuery->where('school_id', $schoolId)))
            ->latest('document_date')
            ->paginate(20)
            ->withQueryString();

        return view('procurement-documents-index', [
            'documents' => $documents,
            'documentTypes' => $this->procurementDocumentTypes(),
            'isMasterUser' => $this->isMasterUser(),
            'currentSchoolName' => $this->isMasterUser() ? null : request()->user()?->school?->name,
            'activeProcurementArea' => 'documents',
            'schools' => $this->isMasterUser() ? School::where('status', 'active')->orderBy('name')->get() : collect(),
            'filtersApplied' => $type !== '' || $status !== '' || $requestSearch !== '' || ($this->isMasterUser() && $schoolId),
        ]);
    }

    public function procurementReceiving(ProcurementWorkspaceService $workspaceService)
    {
        $receivingRequests = ProcurementRequest::with(['school', 'requester', 'documents', 'items'])
            ->whereIn('school_id', $this->scopedSchoolIds())
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $receivingRequests->getCollection()->transform(function (ProcurementRequest $request) use ($workspaceService) {
            $request->setAttribute('receiving', $workspaceService->receiving($request));

            return $request;
        });
        $receivingRequests->setCollection($receivingRequests->getCollection()->sortBy(fn ($request) => match ($request->receiving['status']) {
            'partial' => 0,
            'missing_iar', 'missing_po', 'missing_both' => 1,
            default => 2,
        })->values());

        return view('procurement-receiving', [
            'receivingRequests' => $receivingRequests,
            'isMasterUser' => $this->isMasterUser(),
            'currentSchoolName' => $this->isMasterUser() ? null : request()->user()?->school?->name,
            'activeProcurementArea' => 'receiving',
        ]);
    }

    public function suppliers()
    {
        $search = trim((string) request('search'));
        $status = trim((string) request('status'));

        return view('suppliers', [
            'suppliers' => Supplier::when(! $this->isMasterUser(), fn ($query) => $query->whereIn('school_id', $this->scopedSchoolIds()))
                ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('business_name', 'like', "%{$search}%")->orWhere('contact_person', 'like', "%{$search}%")))
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->orderBy('business_name')->paginate(20)->withQueryString(),
            'activeProcurementArea' => 'suppliers',
            'filtersApplied' => $search !== '' || $status !== '',
        ]);
    }

    private function procurementRequestRows($procurementRequests): array
    {
        return collect($procurementRequests)->map(fn (ProcurementRequest $request) => [
            'record_id' => $request->id,
            'ors' => $request->relationLoaded('liquidationReports')
                ? $request->liquidationReports->map(fn ($report) => ['number' => $report->ors_number ?: $report->report_number, 'status' => $report->status])->all()
                : [],
            'id' => $request->request_number,
            'title' => $request->title,
            'transaction_id' => $request->master_transaction_id,
            'transaction_number' => $request->relationLoaded('transaction') ? $request->transaction?->transaction_number : null,
            'school' => $request->relationLoaded('school') ? ($request->school?->name ?? 'Unassigned') : 'Unassigned',
            'by' => $request->relationLoaded('requester') ? ($request->requester?->name ?? 'System User') : 'System User',
            'amount' => '₱'.number_format((float) $request->amount, 2),
            'status' => str($request->status)->replace('_', ' ')->title()->toString(),
            'tone' => in_array($request->status, ['pending_approval', 'returned'], true) ? 'error' : ($request->status === 'approved' ? 'secondary' : 'primary'),
            'date' => optional($request->requested_at ?? $request->created_at)->format('M d, Y'),
        ])->all();
    }

    public function storeSupplier(Request $request)
    {
        $data = $request->validate($this->supplierRules());
        if (! $this->isMasterUser()) {
            $data['school_id'] = $request->user()?->school_id;
        }
        $data['has_company_owner'] = $request->boolean('has_company_owner');
        if (! $data['has_company_owner']) {
            $data = array_merge($data, ['owner_salutation' => null, 'owner_given_name' => null, 'owner_middle_initial' => null, 'owner_last_name' => null]);
        }
        Supplier::create($data + ['status' => 'active']);

        return redirect()->route('suppliers')->with('success', 'Supplier saved successfully.');
    }

    public function updateSupplier(Request $request, Supplier $supplier)
    {
        $this->authorizeSchoolAccess($supplier->school_id);

        $data = $request->validate($this->supplierRules());
        if (! $this->isMasterUser()) {
            $data['school_id'] = $request->user()?->school_id;
        }
        $data['has_company_owner'] = $request->boolean('has_company_owner');
        if (! $data['has_company_owner']) {
            $data = array_merge($data, ['owner_salutation' => null, 'owner_given_name' => null, 'owner_middle_initial' => null, 'owner_last_name' => null]);
        }
        $supplier->update($data);

        return redirect()->route('suppliers')->with('success', 'Supplier details updated successfully.');
    }

    private function supplierRules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'addressee' => ['nullable', 'string', 'max:255'],
            'has_company_owner' => ['nullable', 'boolean'],
            'owner_salutation' => ['nullable', Rule::in(['Mr.', 'Mrs.', 'Atty.'])],
            'owner_given_name' => ['nullable', 'string', 'max:100'],
            'owner_middle_initial' => ['nullable', 'string', 'max:10'],
            'owner_last_name' => ['nullable', 'string', 'max:100'],
            'business_address' => ['nullable', 'string', 'max:500'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'tin' => ['nullable', 'string', 'max:100'],
            'tax_type' => ['required', Rule::in(['vat', 'non_vat', 'vat_exempt'])],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'business_permit_no' => ['nullable', 'string', 'max:150'],
            'philgeps_no' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }

    private function openBudgetItems()
    {
        return app(BudgetService::class)->withAvailability(BudgetAllocation::whereIn('school_id', $this->scopedSchoolIds())->whereNull('closed_at')
            ->where('fiscal_year', now()->year)->orderBy('particulars')->get());
    }

    /** Approved APP items the PR form can link a line to, with what each has left to request. */
    private function appItemOptions(?int $ignorePrId = null): array
    {
        $links = app(AppItemLinkService::class);

        return $this->scopedSchoolIds()->flatMap(fn ($schoolId) => $links->available((int) $schoolId, $ignorePrId)
            ->map(fn ($item) => [
                'id' => $item->id,
                'school_id' => (int) $schoolId,
                'name' => $item->procurement_item,
                'specifications' => $item->specifications,
                'unit' => $item->unit,
                'unit_price' => (float) $item->estimated_unit_cost,
                'remaining_quantity' => $item->remaining_quantity,
                'remaining_cost' => $item->remaining_cost,
                'fund_source' => $item->fund_source,
            ]))->values()->all();
    }

    public function createProcurement()
    {
        return view('procurement-create', [
            'schools' => School::where('status', 'active')->whereIn('id', $this->scopedSchoolIds())->orderBy('name')->get(),
            'editingRequest' => null,
            'appItems' => $this->appItemOptions(),
            'budgetItems' => $this->openBudgetItems(),
            'agency' => AgencySetting::first(),
            'nextPrNumber' => $this->nextPurchaseRequestNumber(false),
            'activeProcurementArea' => 'requests',
        ]);
    }

    public function editProcurement(ProcurementRequest $procurementRequest)
    {
        $this->authorizeProcurementAccess($procurementRequest);

        $procurementRequest->load(['items', 'school']);

        return view('procurement-create', [
            'schools' => School::where('status', 'active')->whereIn('id', $this->scopedSchoolIds())->orderBy('name')->get(),
            'editingRequest' => $procurementRequest,
            'appItems' => $this->appItemOptions($procurementRequest->id),
            'budgetItems' => $this->openBudgetItems(),
            'agency' => AgencySetting::first(),
            'nextPrNumber' => $this->nextPurchaseRequestNumber(false),
            'activeProcurementArea' => 'requests',
        ]);
    }

    public function storeProcurement(Request $request)
    {
        abort_unless($request->user()->canCreateProcurement(), 403, 'Your role can only view procurement records.');
        $validated = $this->validateProcurement($request);
        $this->authorizeSchoolAccess((int) $validated['school_id']);
        $items = $validated['items'];
        $amount = collect($items)->sum(fn (array $item) => (float) $item['quantity'] * (float) $item['unit_price']);
        $budgetAllocation = ! empty($validated['budget_allocation_id'])
            ? BudgetAllocation::findOrFail($validated['budget_allocation_id'])
            : null;
        $organizationId = $this->organizationIdForSchool((int) $validated['school_id']);
        $fiscalYear = $budgetAllocation?->fiscal_year ?? (int) date('Y', strtotime($validated['request_date']));
        app(FiscalYearService::class)->assertOpen($organizationId, (int) $fiscalYear);
        app(BudgetService::class)->assertAvailable('source_of_fund', (int) $validated['school_id'], $validated['source_of_fund'], (float) $amount);
        $this->assertBudgetItem($validated, (float) $amount);
        app(AppItemLinkService::class)->assertWithinApp((int) $validated['school_id'], $items);

        $procurementRequest = DB::transaction(function () use ($validated, $request, $amount, $items, $budgetAllocation, $fiscalYear) {
            $requestNumber = ! empty($validated['manually_encode_pr_number'])
                ? $validated['manual_pr_number']
                : $this->nextPurchaseRequestNumber(true, $this->organizationIdForSchool((int) $validated['school_id']));
            $masterTransactionId = $budgetAllocation?->master_transaction_id;
            if (! $masterTransactionId) {
                $transaction = app(MasterTransactionService::class)->create(
                    School::findOrFail($validated['school_id']),
                    (int) $fiscalYear,
                    $validated['purpose'],
                    $request->user(),
                    'procurement',
                    'created',
                );
                $masterTransactionId = $transaction->id;
                $budgetAllocation?->update(['master_transaction_id' => $masterTransactionId]);
            }

            $procurementRequest = ProcurementRequest::create([
                'school_id' => $validated['school_id'],
                'requested_by' => $request->user()->id,
                'request_number' => $requestNumber,
                'title' => $validated['purpose'],
                'transaction_description' => $validated['transaction_description'] ?: null,
                'entity_name' => $validated['entity_name'],
                'department_name' => $validated['department_name'],
                'section' => $validated['section'] ?: null,
                'sai_number' => $validated['sai_number'] ?: null,
                'sai_date' => $validated['sai_date'] ?: null,
                'responsibility_center_code' => $validated['responsibility_center_code'] ?: null,
                'source_of_fund' => $validated['source_of_fund'],
                'budget_allocation_id' => $validated['budget_allocation_id'] ?? null,
                'master_transaction_id' => $masterTransactionId,
                'description' => 'Itemized goods request',
                'amount' => $amount,
                'extra_blank_rows' => $validated['extra_blank_rows'],
                'status' => 'submitted',
                'requested_at' => $validated['request_date'],
            ]);

            $this->replaceProcurementItems($procurementRequest, $items);
            app(AppItemLinkService::class)->recordLinks($procurementRequest);
            $procurementRequest->transaction?->update(['status' => 'procurement']);
            $procurementRequest->transaction?->recordEvent('procurement', 'pr_created', null, 'submitted', $procurementRequest->request_number, ['procurement_request_id' => $procurementRequest->id, 'amount' => $amount]);

            return $procurementRequest;
        });

        return redirect()->route('procurement.show', $procurementRequest)->with('success', 'Procurement request created successfully.');
    }

    public function updateProcurement(Request $request, ProcurementRequest $procurementRequest)
    {
        abort_unless($request->user()->canCreateProcurement(), 403, 'Your role can only view procurement records.');
        $this->authorizeProcurementAccess($procurementRequest);
        $validated = $this->validateProcurement($request);
        $this->authorizeSchoolAccess((int) $validated['school_id']);
        $items = $validated['items'];
        $amount = collect($items)->sum(fn (array $item) => (float) $item['quantity'] * (float) $item['unit_price']);
        $budgetAllocation = ! empty($validated['budget_allocation_id'])
            ? BudgetAllocation::findOrFail($validated['budget_allocation_id'])
            : null;
        if ($procurementRequest->liquidationReports()->exists()
            && (int) $procurementRequest->budget_allocation_id !== (int) ($budgetAllocation?->id ?? 0)) {
            throw ValidationException::withMessages(['budget_allocation_id' => 'The budget line cannot be changed after an ORS has been linked to this PR.']);
        }
        app(FiscalYearService::class)->assertOpen(
            (int) $procurementRequest->organization_id,
            (int) ($budgetAllocation?->fiscal_year ?? date('Y', strtotime($validated['request_date']))),
        );
        app(BudgetService::class)->assertAvailable('source_of_fund', (int) $validated['school_id'], $validated['source_of_fund'], (float) $amount, $procurementRequest->created_at?->year, $procurementRequest->id);
        $this->assertBudgetItem($validated, (float) $amount, $procurementRequest->id, null, $procurementRequest->created_at);
        app(AppItemLinkService::class)->assertWithinApp((int) $validated['school_id'], $items, $procurementRequest->id);
        $requestNumber = ! empty($validated['manually_encode_pr_number'])
            ? $validated['manual_pr_number']
            : (preg_match('/^PR-\d{4}-\d{3,}$/', $procurementRequest->request_number)
                ? $procurementRequest->request_number
                : $this->nextPurchaseRequestNumber(true, (int) $procurementRequest->organization_id));

        $procurementRequest->update([
            'school_id' => $validated['school_id'],
            'title' => $validated['purpose'],
            'transaction_description' => $validated['transaction_description'] ?: null,
            'entity_name' => $validated['entity_name'],
            'department_name' => $validated['department_name'],
            'section' => $validated['section'] ?: null,
            'sai_number' => $validated['sai_number'] ?: null,
            'sai_date' => $validated['sai_date'] ?: null,
            'responsibility_center_code' => $validated['responsibility_center_code'] ?: null,
            'source_of_fund' => $validated['source_of_fund'],
            'budget_allocation_id' => $validated['budget_allocation_id'] ?? null,
            'master_transaction_id' => $budgetAllocation?->master_transaction_id,
            'amount' => $amount,
            'extra_blank_rows' => $validated['extra_blank_rows'],
            'requested_at' => $validated['request_date'],
            'request_number' => $requestNumber,
        ]);
        $this->replaceProcurementItems($procurementRequest, $items);
        app(AppItemLinkService::class)->recordLinks($procurementRequest);
        $procurementRequest->transaction?->recordEvent('procurement', 'pr_updated', null, $procurementRequest->status, $procurementRequest->request_number, ['procurement_request_id' => $procurementRequest->id, 'amount' => $amount]);

        return redirect()->route('procurement.show', $procurementRequest)
            ->with('success', 'Procurement request updated successfully.');
    }

    /**
     * Every charge must name a budget line once the school has budgeted for the year; the line must
     * match the school and fund and have room for the amount (annual and quarterly).
     */
    private function assertBudgetItem(array $data, float $amount, ?int $ignorePrId = null, ?int $ignoreOrsId = null, ?\DateTimeInterface $date = null): void
    {
        if (empty($data['budget_allocation_id'])) {
            $budgeted = BudgetAllocation::where('school_id', $data['school_id'])->where('fiscal_year', ($date ?? now())->format('Y'))->whereNull('closed_at');
            if ($budgeted->exists()) {
                $message = (clone $budgeted)->where('source_of_fund', $data['source_of_fund'])->exists()
                    ? 'Select the budget line (account code) this expense is charged to.'
                    : 'The selected account code has no budget allocation for this year.';
                throw ValidationException::withMessages(['budget_allocation_id' => $message]);
            }

            return;
        }

        $item = BudgetAllocation::findOrFail($data['budget_allocation_id']);
        if ($item->school_id !== (int) $data['school_id']) {
            throw ValidationException::withMessages(['budget_allocation_id' => 'The selected budget line belongs to a different school.']);
        }
        if (strcasecmp($item->source_of_fund, (string) $data['source_of_fund']) !== 0) {
            throw ValidationException::withMessages(['budget_allocation_id' => 'The selected fund source does not match this budget line.']);
        }

        app(BudgetService::class)->assertItemAvailable('budget_allocation_id', $item->id, $amount, $ignorePrId, $ignoreOrsId, $date);
    }

    private function validateProcurement(Request $request): array
    {
        $organizationId = $request->filled('school_id')
            ? $this->organizationIdForSchool((int) $request->input('school_id'))
            : (int) $request->user()->organization_id;

        return $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'purpose' => ['required', 'string', 'max:255'],
            'transaction_description' => ['nullable', 'string', 'max:255'],
            'entity_name' => ['required', 'string', 'max:255'],
            'department_name' => ['required', 'string', 'max:255'],
            'section' => ['nullable', 'string', 'max:255'],
            'sai_number' => ['nullable', 'string', 'max:255'],
            'sai_date' => ['nullable', 'date'],
            'responsibility_center_code' => ['nullable', 'string', 'max:255'],
            'request_date' => ['required', 'date'],
            'source_of_fund' => ['required', 'string', 'max:255'],
            'budget_allocation_id' => ['nullable', 'integer', 'exists:budget_allocations,id'],
            'manually_encode_pr_number' => ['nullable', 'boolean'],
            'manual_pr_number' => ['nullable', 'required_if:manually_encode_pr_number,1', 'string', 'max:50', 'regex:/^PR-\d{4}-\d{3,}$/', Rule::unique('procurement_requests', 'request_number')->where('organization_id', $organizationId)->ignore($request->route('procurementRequest'))],
            'extra_blank_rows' => ['nullable', 'integer', 'min:0', 'max:20'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.app_item_id' => ['nullable', 'integer'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['required', 'string', 'max:50'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function nextPurchaseRequestNumber(bool $lock = true, ?int $organizationId = null): string
    {
        $organizationId ??= (int) request()->user()->organization_id;
        $numbers = app(DocumentNumberService::class);

        return $lock
            ? $numbers->next($organizationId, 'purchase_request', 'PR', padding: 3)
            : $numbers->preview($organizationId, 'purchase_request', 'PR', padding: 3);
    }

    private function replaceProcurementItems(ProcurementRequest $procurementRequest, array $items): void
    {
        $procurementRequest->items()->delete();
        foreach ($items as $item) {
            $procurementRequest->items()->create([
                ...$item,
                'total' => (float) $item['quantity'] * (float) $item['unit_price'],
            ]);
        }
    }

    public function printProcurement(ProcurementRequest $procurementRequest)
    {
        $this->authorizeProcurementAccess($procurementRequest);

        $procurementRequest->load(['school', 'requester', 'items', 'documents']);
        $staff = SchoolStaff::where('school_id', $procurementRequest->school_id)->get();
        $rfqMetadata = $procurementRequest->documents->firstWhere('document_type', 'request_for_quotation')?->metadata ?? [];
        $transactionDescription = $procurementRequest->transaction_description ?: data_get($rfqMetadata, 'transaction_description');
        $requestingOfficer = $staff->firstWhere('procurement_role', 'Requesting Officer')
            ?? $staff->firstWhere('procurement_role', 'Procurement Officer');
        $approver = $staff->firstWhere('procurement_role', 'Approver')
            ?? $staff->first(fn ($member) => str_contains(strtolower((string) $member->position), 'school head'));
        $agency = AgencySetting::first() ?? new AgencySetting;

        return view('procurement-print', [
            'procurementRequest' => $procurementRequest,
            'agency' => $agency,
            'school_name' => $procurementRequest->school?->name ?? '',
            'date' => optional($procurementRequest->requested_at ?? $procurementRequest->created_at)->format('F d, Y'),
            'payee_name' => $procurementRequest->school?->name ?? '',
            'amount' => number_format((float) $procurementRequest->amount, 2),
            'purpose' => collect([$transactionDescription, $procurementRequest->title])
                ->filter()
                ->implode(' for '),
            'prepared_by' => $requestingOfficer?->name ?? $procurementRequest->requester?->name ?? '',
            'prepared_by_role' => $requestingOfficer?->position ?? 'Requested by',
            'approved_by' => $approver?->name ?? $procurementRequest->school?->school_head ?? '',
            'approved_by_role' => $approver?->position ?? 'School Head',
        ]);
    }

    public function procurementDocuments(ProcurementRequest $procurementRequest, ProcurementWorkspaceService $workspaceService)
    {
        $this->authorizeProcurementAccess($procurementRequest);

        $procurementRequest->load(['school', 'requester', 'items', 'documents.creator']);
        $schoolStaff = SchoolStaff::where('school_id', $procurementRequest->school_id)
            ->orderBy('name')
            ->get(['id', 'name', 'position', 'document_role']);
        $inspectionOfficer = $schoolStaff->first(fn ($member) => str_contains(strtolower((string) $member->document_role), 'inspection officer'))
            ?? $schoolStaff->first(fn ($member) => str_contains(strtolower((string) $member->position), 'inspection'));

        return view('procurement-documents', [
            'procurementRequest' => $procurementRequest,
            'documentTypes' => $this->procurementDocumentTypes(),
            'suppliers' => Supplier::where('status', 'active')
                ->where('school_id', $procurementRequest->school_id)
                ->orderBy('business_name')
                ->get([
                    'id', 'business_name', 'business_address', 'tin', 'addressee', 'has_company_owner',
                    'owner_salutation', 'owner_given_name', 'owner_middle_initial', 'owner_last_name', 'contact_person',
                    'phone', 'email', 'business_permit_no', 'philgeps_no',
                ]),
            'abstractWinner' => $this->abstractWinner($procurementRequest),
            'purchaseOrder' => $procurementRequest->documents->firstWhere('document_type', 'purchase_order'),
            'nextPoNumber' => $this->nextPurchaseOrderNumber(false, (int) $procurementRequest->organization_id),
            'documentCodes' => collect($this->procurementDocumentTypes())->mapWithKeys(fn ($definition, $type) => [$type => $definition['prefix']]),
            'nextDocumentNumbers' => collect($this->procurementDocumentTypes())->mapWithKeys(fn ($definition, $type) => [$type => $this->nextOfficialDocumentNumber($type, $definition['prefix'], false, (int) $procurementRequest->organization_id)]),
            'schoolStaff' => $schoolStaff,
            'inspectionOfficerName' => $inspectionOfficer?->name ?? '',
            'workspace' => $workspaceService->present($procurementRequest),
            'activeProcurementArea' => 'documents',
        ]);
    }

    public function storeProcurementDocument(Request $request, ProcurementRequest $procurementRequest)
    {
        $this->authorizeProcurementAccess($procurementRequest);

        $types = $this->procurementDocumentTypes();
        $data = $request->validate([
            'document_type' => ['required', Rule::in(array_keys($types))],
            'document_date' => ['required', 'date'],
            'supplier_or_recipient' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'supplier_address' => ['nullable', 'string', 'max:500'],
            'tin' => ['nullable', 'string', 'max:100'],
            'mode_of_procurement' => ['nullable', 'string', 'max:100'],
            'delivery_term' => ['nullable', 'string', 'max:255'],
            'payment_term' => ['nullable', 'string', 'max:255'],
            'delivery_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'deadline_date' => ['nullable', 'date'],
            'source_of_fund' => ['nullable', 'string', 'max:255'],
            'place_of_delivery' => ['nullable', 'string', 'max:255'],
            'delivery_schedule' => ['nullable', 'string', 'max:500'],
            'manually_encode_document_number' => ['nullable', 'boolean'],
            'manual_document_number' => ['nullable', 'required_if:manually_encode_document_number,1', 'string', 'max:50', 'regex:/^[A-Z]+-\d{4}-\d{3,}$/'],
            'second_bidder' => ['nullable', 'string', 'max:255'],
            'third_bidder' => ['nullable', 'string', 'max:255'],
            'bidder_1_name' => ['nullable', 'string', 'max:255'],
            'bidder_2_name' => ['nullable', 'string', 'max:255'],
            'bidder_3_name' => ['nullable', 'string', 'max:255'],
            'bidder_1_prices' => ['nullable', 'array'],
            'bidder_1_prices.*' => ['nullable', 'numeric', 'min:0'],
            'bidder_2_prices' => ['nullable', 'array'],
            'bidder_2_prices.*' => ['nullable', 'numeric', 'min:0'],
            'bidder_3_prices' => ['nullable', 'array'],
            'bidder_3_prices.*' => ['nullable', 'numeric', 'min:0'],
            'bidders' => ['nullable', 'array', 'max:10'],
            'bidders.*.name' => ['nullable', 'string', 'max:255'],
            'bidders.*.prices' => ['nullable', 'array'],
            'bidders.*.prices.*' => ['nullable', 'numeric', 'min:0'],
            'template_variant' => ['nullable', 'in:mooe,sbfp,scheduled_delivery,thirty_days'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'business_address' => ['nullable', 'string', 'max:500'],
            'transaction_description' => ['nullable', 'string', 'max:500'],
            'business_permit_no' => ['nullable', 'string', 'max:255'],
            'philgeps_no' => ['nullable', 'string', 'max:255'],
            'project_title' => ['nullable', 'string', 'max:500'],
            'date_posted' => ['nullable', 'date'],
            'date_opening' => ['nullable', 'date'],
            'time_opening' => ['nullable', 'date_format:H:i'],
            'terms_of_payment' => ['nullable', 'string', 'max:500'],
            'extra_blank_rows' => ['nullable', 'integer', 'min:0', 'max:20'],
            'inspection_officer_name' => ['nullable', 'string', 'max:255'],
            'received_items' => ['nullable', 'array'],
            'received_items.*' => ['nullable', 'numeric', 'min:0'],
            'iars_assignments' => ['nullable', 'array', 'max:30'],
            'iars_assignments.*.staff_id' => ['required_with:iars_assignments', 'integer'],
            'iars_assignments.*.items' => ['nullable', 'array'],
            'iars_assignments.*.items.*' => ['nullable', 'numeric', 'min:0'],
            'ics_items' => ['nullable', 'array'],
            'ics_items.*.included' => ['nullable', 'boolean'],
            'ics_items.*.custodian_id' => ['nullable', 'integer'],
            'ics_items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'ics_items.*.inventory_item_number' => ['nullable', 'string', 'max:255'],
            'ics_items.*.useful_life' => ['nullable', 'string', 'max:255'],
        ]);

        $type = $types[$data['document_type']];
        $awardMetadata = [];
        $linkedDocumentMetadata = [];
        if ($data['document_type'] === 'inventory_acknowledgement_receipt_supplies') {
            $staffIds = collect($data['iars_assignments'] ?? [])
                ->pluck('staff_id')
                ->filter()
                ->map(fn ($id) => (string) $id);

            if ($staffIds->count() !== $staffIds->unique()->count()) {
                return back()->withErrors(['iars_assignments' => 'Each staff member can only be added once in the IARS distribution list.'])->withInput();
            }

            $inspectionAcceptanceReport = $procurementRequest->documents()
                ->where('document_type', 'inspection_acceptance_report')
                ->first();

            if (! $inspectionAcceptanceReport) {
                return back()->withErrors(['document_type' => 'Prepare the Inspection and Acceptance Report first so IARS can use the actual received items.'])->withInput();
            }

            $receivedItems = $inspectionAcceptanceReport->metadata['received_items'] ?? [];
            foreach ($procurementRequest->items as $item) {
                $receivedQuantity = $receivedItems[$item->id] ?? $item->quantity;
                $receivedQuantity = $receivedQuantity === '' || $receivedQuantity === null ? (float) $item->quantity : (float) $receivedQuantity;
                $issuedQuantity = collect($data['iars_assignments'] ?? [])
                    ->sum(fn ($row) => (float) data_get($row, 'items.'.$item->id, 0));

                if ($issuedQuantity > $receivedQuantity) {
                    return back()->withErrors(['iars_assignments' => "The distributed quantity for {$item->name} cannot exceed the IAR actual received quantity."])->withInput();
                }
            }
        }
        if ($data['document_type'] === 'notice_to_proceed') {
            $purchaseOrder = $procurementRequest->documents()
                ->where('document_type', 'purchase_order')
                ->first();

            if ($purchaseOrder) {
                $poMetadata = $purchaseOrder->metadata ?? [];
                foreach (['mode_of_procurement', 'delivery_term', 'payment_term', 'delivery_days', 'source_of_fund'] as $field) {
                    if (array_key_exists($field, $poMetadata) && $poMetadata[$field] !== null && $poMetadata[$field] !== '') {
                        $data[$field] = $poMetadata[$field];
                    }
                }
            }
        }
        if ($data['document_type'] === 'inventory_custodian_slip') {
            $inspectionAcceptanceReport = $procurementRequest->documents()
                ->where('document_type', 'inspection_acceptance_report')
                ->first();
            $purchaseOrder = $procurementRequest->documents()
                ->where('document_type', 'purchase_order')
                ->first();

            if (! $inspectionAcceptanceReport) {
                return back()->withErrors(['document_type' => 'Prepare the Inspection and Acceptance Report first so ICS can use the actual received items.'])->withInput();
            }

            $receivedItems = $inspectionAcceptanceReport->metadata['received_items'] ?? [];
            $selectedItems = collect($data['ics_items'] ?? [])->filter(fn ($row) => ! empty($row['included']));
            if ($selectedItems->isEmpty()) {
                return back()->withErrors(['ics_items' => 'Select at least one received item for ICS.'])->withInput();
            }

            foreach ($procurementRequest->items as $item) {
                $icsItem = $data['ics_items'][$item->id] ?? null;
                if (! $icsItem || empty($icsItem['included'])) {
                    continue;
                }

                $receivedQuantity = $receivedItems[$item->id] ?? $item->quantity;
                $receivedQuantity = $receivedQuantity === '' || $receivedQuantity === null ? (float) $item->quantity : (float) $receivedQuantity;
                $icsQuantity = (float) ($icsItem['quantity'] ?? 0);

                if ($icsQuantity <= 0) {
                    return back()->withErrors(['ics_items' => "Enter a quantity for {$item->name}."])->withInput();
                }
                if ($icsQuantity > $receivedQuantity) {
                    return back()->withErrors(['ics_items' => "The ICS quantity for {$item->name} cannot exceed the IAR actual received quantity."])->withInput();
                }
                if (empty($icsItem['custodian_id'])) {
                    return back()->withErrors(['ics_items' => "Select a custodian for {$item->name}."])->withInput();
                }
            }

            if ($purchaseOrder) {
                $poMetadata = $purchaseOrder->metadata ?? [];
                $linkedDocumentMetadata = array_filter([
                    'purchase_order_number' => $purchaseOrder->document_number,
                    'purchase_order_date' => optional($purchaseOrder->document_date)->format('F d, Y'),
                    'awarded_item_prices' => $poMetadata['awarded_item_prices'] ?? [],
                    'source_of_fund' => $poMetadata['source_of_fund'] ?? null,
                ], fn ($value) => $value !== null && $value !== '');
            }
        }
        if ($data['document_type'] === 'inspection_acceptance_report') {
            $purchaseOrder = $procurementRequest->documents()
                ->where('document_type', 'purchase_order')
                ->first();

            if (! $purchaseOrder) {
                return back()->withErrors(['document_type' => 'Prepare the Purchase Order before creating an Inspection and Acceptance Report.'])->withInput();
            }

            $poMetadata = $purchaseOrder->metadata ?? [];
            $data['supplier_or_recipient'] = $purchaseOrder->supplier_or_recipient;
            foreach ($procurementRequest->items as $item) {
                $receivedQuantity = data_get($data, 'received_items.'.$item->id);
                if ($receivedQuantity !== null && $receivedQuantity !== '' && (float) $receivedQuantity > (float) $item->quantity) {
                    return back()->withErrors(['received_items' => "The actual received quantity for {$item->name} cannot exceed the Purchase Order quantity."])->withInput();
                }
            }
            foreach (['supplier_address', 'tin', 'mode_of_procurement', 'delivery_term', 'payment_term', 'delivery_days', 'source_of_fund'] as $field) {
                if (array_key_exists($field, $poMetadata) && $poMetadata[$field] !== null && $poMetadata[$field] !== '') {
                    $data[$field] = $poMetadata[$field];
                }
            }
            $linkedDocumentMetadata = array_filter([
                'purchase_order_number' => $purchaseOrder->document_number,
                'purchase_order_date' => optional($purchaseOrder->document_date)->format('F d, Y'),
                'winning_bid_amount' => $poMetadata['winning_bid_amount'] ?? null,
                'awarded_item_prices' => $poMetadata['awarded_item_prices'] ?? [],
                'supplier_addressee' => $poMetadata['supplier_addressee'] ?? null,
                'supplier_owner_name' => $poMetadata['supplier_owner_name'] ?? null,
                'supplier_contact_person' => $poMetadata['supplier_contact_person'] ?? null,
                'supplier_phone' => $poMetadata['supplier_phone'] ?? null,
                'supplier_email' => $poMetadata['supplier_email'] ?? null,
                'supplier_tin' => $poMetadata['supplier_tin'] ?? null,
                'supplier_business_permit_no' => $poMetadata['supplier_business_permit_no'] ?? null,
                'supplier_philgeps_no' => $poMetadata['supplier_philgeps_no'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');
        }
        if (in_array($data['document_type'], ['notice_to_award', 'purchase_order', 'notice_to_proceed'], true)) {
            $winner = $this->abstractWinner($procurementRequest);
            if (! $winner) {
                return back()->withErrors(['document_type' => 'Prepare the Abstract of Bids or Quotation with at least one supplier quotation before creating this document.'])->withInput();
            }

            $data['supplier_or_recipient'] = $winner['name'];
            $supplier = Supplier::where('school_id', $procurementRequest->school_id)
                ->where('business_name', $winner['name'])
                ->first();
            $ownerName = trim(implode(' ', array_filter([
                $supplier?->owner_salutation,
                $supplier?->owner_given_name,
                $supplier?->owner_middle_initial ? $supplier->owner_middle_initial.'.' : null,
                $supplier?->owner_last_name,
            ])));
            $awardMetadata = array_filter([
                'winning_bidder' => $winner['name'],
                'winning_bid_amount' => $winner['total'],
                'supplier_address' => $supplier?->business_address,
                'supplier_addressee' => $supplier?->addressee ?: ($ownerName ?: null),
                'supplier_owner_name' => $ownerName ?: null,
                'supplier_contact_person' => $supplier?->contact_person,
                'supplier_phone' => $supplier?->phone,
                'supplier_email' => $supplier?->email,
                'supplier_tin' => $supplier?->tin,
                'supplier_business_permit_no' => $supplier?->business_permit_no,
                'supplier_philgeps_no' => $supplier?->philgeps_no,
                'awarded_item_prices' => $winner['prices'] ?? [],
            ], fn ($value) => $value !== null && $value !== '');
        }
        $existingDocument = $procurementRequest->documents()
            ->where('document_type', $data['document_type'])
            ->first();
        if (! empty($data['manually_encode_document_number'])) {
            $expectedPrefix = $type['prefix'].'-';
            if (! str_starts_with($data['manual_document_number'], $expectedPrefix)) {
                return back()->withErrors(['manual_document_number' => "Use the {$type['prefix']}-YYYY-001 format for this document."])->withInput();
            }
            $numberInUse = ProcurementDocument::where('document_number', $data['manual_document_number'])
                ->where('organization_id', $procurementRequest->organization_id)
                ->when($existingDocument, fn ($query) => $query->whereKeyNot($existingDocument->id))
                ->exists();
            if ($numberInUse) {
                return back()->withErrors(['manual_document_number' => 'This document number is already in use.'])->withInput();
            }
        }
        $documentNumber = ! empty($data['manually_encode_document_number'])
            ? $data['manual_document_number']
            : (preg_match('/^'.preg_quote($type['prefix'], '/').'-\d{4}-\d{3,}$/', (string) $existingDocument?->document_number)
                ? $existingDocument->document_number
                : $this->nextOfficialDocumentNumber($data['document_type'], $type['prefix'], true, (int) $procurementRequest->organization_id));

        $document = ProcurementDocument::updateOrCreate(
            [
                'procurement_request_id' => $procurementRequest->id,
                'document_type' => $data['document_type'],
            ],
            [
                'created_by' => $request->user()?->id,
                'document_number' => $documentNumber,
                'document_date' => $data['document_date'],
                // An RFQ is prepared before a supplier is selected. A business name,
                // when supplied for canvassing, is stored only in the RFQ metadata.
                'supplier_or_recipient' => $data['document_type'] === 'request_for_quotation'
                    ? null
                    : ($data['supplier_or_recipient'] ?? null),
                'notes' => $data['notes'] ?? null,
                'status' => 'prepared',
                'metadata' => array_merge(collect($data)->only([
                    'supplier_address', 'tin', 'mode_of_procurement', 'delivery_term',
                    'payment_term', 'delivery_days', 'deadline_date', 'source_of_fund',
                    'place_of_delivery', 'delivery_schedule',
                    'second_bidder', 'third_bidder', 'template_variant',
                    'bidder_1_name', 'bidder_2_name', 'bidder_3_name',
                    'bidder_1_prices', 'bidder_2_prices', 'bidder_3_prices',
                    'bidders',
                    'business_name', 'business_address', 'business_permit_no', 'philgeps_no', 'transaction_description', 'project_title', 'date_posted',
                    'date_opening', 'time_opening', 'terms_of_payment',
                    'extra_blank_rows',
                    'inspection_officer_name',
                    'received_items',
                    'iars_assignments',
                    'ics_items',
                ])->filter(fn ($value) => $value !== null && $value !== '')->all(), $awardMetadata, $linkedDocumentMetadata),
            ]
        );

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'school_id' => $procurementRequest->school_id,
            'action' => 'prepared_'.$data['document_type'],
            'auditable_type' => ProcurementDocument::class,
            'auditable_id' => $document->id,
        ]);

        return redirect()->route('procurement.documents', $procurementRequest)
            ->with('success', $type['label'].' saved successfully.');
    }

    private function nextPurchaseOrderNumber(bool $lock = true, ?int $organizationId = null): string
    {
        return $this->nextOfficialDocumentNumber('purchase_order', 'PO', $lock, $organizationId);
    }

    private function nextOfficialDocumentNumber(string $documentType, string $code, bool $lock = true, ?int $organizationId = null): string
    {
        $organizationId ??= (int) request()->user()->organization_id;
        $digits = $code === 'RIS' ? 4 : 3;
        $numbers = app(DocumentNumberService::class);

        return $lock
            ? $numbers->next($organizationId, 'procurement_'.$documentType, $code, padding: $digits)
            : $numbers->preview($organizationId, 'procurement_'.$documentType, $code, padding: $digits);
    }

    private function abstractWinner(ProcurementRequest $procurementRequest): ?array
    {
        $abstract = $procurementRequest->documents()
            ->where('document_type', 'abstract_of_bids_quotation')
            ->first();

        if (! $abstract) {
            return null;
        }

        $meta = $abstract->metadata ?? [];
        $legacy = [
            ['name' => $meta['bidder_1_name'] ?? null, 'prices' => $meta['bidder_1_prices'] ?? []],
            ['name' => $meta['bidder_2_name'] ?? null, 'prices' => $meta['bidder_2_prices'] ?? []],
            ['name' => $meta['bidder_3_name'] ?? null, 'prices' => $meta['bidder_3_prices'] ?? []],
        ];
        $items = $procurementRequest->relationLoaded('items') ? $procurementRequest->items : $procurementRequest->items()->get();
        $bidders = collect($meta['bidders'] ?? $legacy)
            ->filter(fn ($bidder) => ! empty($bidder['name']))
            ->map(function (array $bidder) use ($items) {
                $prices = $bidder['prices'] ?? [];
                $hasCurrentItemPrice = $items->contains(fn ($item) => array_key_exists((string) $item->id, $prices));
                if (! $hasCurrentItemPrice && ! empty($prices)) {
                    $prices = $items->values()->mapWithKeys(fn ($item, $index) => [$item->id => array_values($bidder['prices'])[$index] ?? null])->all();
                }

                return [...$bidder, 'prices' => $prices];
            })
            ->values();
        $totals = $bidders->map(function (array $bidder) use ($items) {
            $quotes = 0;
            $total = 0;
            foreach ($items as $item) {
                $price = data_get($bidder, 'prices.'.$item->id);
                if ($price !== null && $price !== '' && is_numeric($price)) {
                    $quotes++;
                    $total += (float) $price * (float) $item->quantity;
                }
            }

            return ['name' => $bidder['name'], 'total' => $total, 'quotes' => $quotes, 'prices' => $bidder['prices'] ?? []];
        })->filter(fn ($bidder) => $bidder['quotes'] > 0)->sortBy('total');

        $winner = $totals->first();

        return $winner ? ['name' => $winner['name'], 'total' => $winner['total'], 'prices' => $winner['prices']] : null;
    }

    public function printProcurementDocument(ProcurementRequest $procurementRequest, ProcurementDocument $procurementDocument)
    {
        $this->authorizeProcurementAccess($procurementRequest);
        abort_unless($procurementDocument->procurement_request_id === $procurementRequest->id, 404);
        $procurementRequest->load(['school', 'requester', 'items']);
        $staff = SchoolStaff::where('school_id', $procurementRequest->school_id)->get();
        $awardAmount = (float) data_get($procurementDocument->metadata, 'winning_bid_amount', $procurementRequest->amount);
        $purchaseOrder = $procurementRequest->documents()->where('document_type', 'purchase_order')->first();
        $inspectionAcceptanceReport = $procurementRequest->documents()->where('document_type', 'inspection_acceptance_report')->first();
        $inventoryAcknowledgementReceipt = $procurementRequest->documents()->where('document_type', 'inventory_acknowledgement_receipt_supplies')->first();
        $requestingOfficer = $staff->firstWhere('procurement_role', 'Requesting Officer');
        $procurementOfficer = $staff->firstWhere('procurement_role', 'Procurement Officer');
        $approver = $staff->firstWhere('procurement_role', 'Approver');
        $canvasser = $staff->firstWhere('procurement_role', 'Canvasser');

        return view('procurement-document-print', [
            'procurementRequest' => $procurementRequest,
            'document' => $procurementDocument,
            'purchaseOrder' => $purchaseOrder,
            'inspectionAcceptanceReport' => $inspectionAcceptanceReport,
            'inventoryAcknowledgementReceipt' => $inventoryAcknowledgementReceipt,
            'documentDefinition' => $this->procurementDocumentTypes()[$procurementDocument->document_type],
            'agency' => AgencySetting::first(),
            'staff' => $staff,
            'iarsStaff' => SchoolStaff::orderBy('name')->get(),
            'schoolHead' => $staff->first(fn ($member) => str_contains(strtolower((string) $member->position), 'school head')) ?? $approver,
            'requestingOfficer' => $requestingOfficer,
            'procurementOfficer' => $procurementOfficer,
            'approver' => $approver,
            'canvasser' => $canvasser,
            'bacChair' => $staff->firstWhere('bac_role', 'BAC Chairperson'),
            'bacViceChair' => $staff->firstWhere('bac_role', 'BAC Vice Chairperson'),
            'bacSecretariat' => $staff->firstWhere('bac_role', 'BAC Secretariat'),
            'bacMembers' => $staff->where('bac_role', 'BAC Member')->values(),
            'disbursingOfficer' => $staff->first(fn ($user) => str_contains(strtolower((string) $user->document_role), 'disbursing officer')) ?? $staff->first(fn ($user) => str_contains(strtolower((string) $user->position), 'disbursing')),
            'inspectionOfficer' => $staff->first(fn ($user) => str_contains(strtolower((string) $user->document_role), 'inspection officer')) ?? $staff->first(fn ($user) => str_contains(strtolower((string) $user->position), 'inspection')),
            'propertyOfficer' => $staff->first(fn ($user) => str_contains(strtolower((string) $user->document_role), 'property custodian')) ?? $staff->first(fn ($user) => str_contains(strtolower((string) $user->position), 'property')),
            'amountInWords' => $this->amountInWords((float) $procurementRequest->amount),
            'awardAmountInWords' => $this->amountInWords($awardAmount),
        ]);
    }

    public function printDeliveryReconciliation(ProcurementRequest $procurementRequest, ProcurementWorkspaceService $workspaceService)
    {
        $this->authorizeProcurementAccess($procurementRequest);

        $procurementRequest->load(['school', 'requester', 'items', 'documents']);
        $purchaseOrder = $procurementRequest->documents->firstWhere('document_type', 'purchase_order');
        $iar = $procurementRequest->documents->firstWhere('document_type', 'inspection_acceptance_report');

        abort_unless($purchaseOrder && $iar, 404);

        $receiving = $workspaceService->receiving($procurementRequest);
        $rows = $receiving['rows'];

        return view('procurement-delivery-reconciliation', [
            'procurementRequest' => $procurementRequest,
            'purchaseOrder' => $purchaseOrder,
            'iar' => $iar,
            'agency' => AgencySetting::first(),
            'rows' => $rows,
            'partialRows' => $rows->where('status', 'Partial')->values(),
            'completeRows' => $rows->where('status', 'Complete')->values(),
            'totalPoAmount' => $receiving['total_po_amount'],
            'totalReceivedAmount' => $receiving['total_received_amount'],
            'totalBalanceQuantity' => $receiving['balance_quantity'],
        ]);
    }

    private function procurementDocumentTypes(): array
    {
        return [
            'request_for_quotation' => ['label' => 'Request for Quotation', 'short' => 'RFQ', 'prefix' => 'RFQ', 'icon' => 'request_quote'],
            'abstract_of_bids_quotation' => ['label' => 'Abstract of Bids or Quotation', 'short' => 'ABQ', 'prefix' => 'ABQ', 'icon' => 'table_view'],
            'notice_to_award' => ['label' => 'Notice to Award', 'short' => 'NOA', 'prefix' => 'NOA', 'icon' => 'workspace_premium'],
            'purchase_order' => ['label' => 'Purchase Order', 'short' => 'PO', 'prefix' => 'PO', 'icon' => 'shopping_cart_checkout'],
            'notice_to_proceed' => ['label' => 'Notice to Proceed', 'short' => 'NTP', 'prefix' => 'NTP', 'icon' => 'play_circle'],
            'inspection_acceptance_report' => ['label' => 'Inspection and Acceptance Report', 'short' => 'IAR', 'prefix' => 'IAR', 'icon' => 'fact_check'],
            'inventory_acknowledgement_receipt_supplies' => ['label' => 'Inventory and Acknowledgement Receipt of Supplies', 'short' => 'IARS', 'prefix' => 'IARS', 'icon' => 'inventory'],
            'requisition_issuance_slip' => ['label' => 'Requisition and Issuance Slip', 'short' => 'RIS', 'prefix' => 'RIS', 'icon' => 'assignment_return'],
            'inventory_custodian_slip' => ['label' => 'Inventory Custodian Slip', 'short' => 'ICS', 'prefix' => 'ICS', 'icon' => 'inventory_2'],
            'property_acknowledgement_receipt' => ['label' => 'Property Acknowledgement Receipt', 'short' => 'PAR', 'prefix' => 'PAR', 'icon' => 'real_estate_agent'],
        ];
    }

    private function amountInWords(float $amount): string
    {
        $pesos = (int) floor($amount);
        $centavos = (int) round(($amount - $pesos) * 100);
        $words = $this->integerInWords($pesos).' Pesos';
        if ($centavos > 0) {
            $words .= ' and '.$this->integerInWords($centavos).' Centavos';
        }

        return $words.' Only';
    }

    private function integerInWords(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $underThousand = function (int $value) use ($ones, $tens): string {
            $parts = [];
            if ($value >= 100) {
                $parts[] = $ones[intdiv($value, 100)].' Hundred';
                $value %= 100;
            }
            if ($value >= 20) {
                $parts[] = $tens[intdiv($value, 10)].($value % 10 ? '-'.$ones[$value % 10] : '');
            } elseif ($value > 0) {
                $parts[] = $ones[$value];
            }

            return implode(' ', $parts);
        };
        $parts = [];
        foreach ([1000000000 => 'Billion', 1000000 => 'Million', 1000 => 'Thousand'] as $value => $label) {
            if ($number >= $value) {
                $parts[] = $underThousand(intdiv($number, $value)).' '.$label;
                $number %= $value;
            }
        }
        if ($number > 0) {
            $parts[] = $underThousand($number);
        }

        return implode(' ', $parts);
    }

    public function liquidation()
    {
        $user = request()->user();
        $schoolIds = $this->scopedSchoolIds($user);
        $schools = School::whereIn('id', $schoolIds)->orderBy('name')->get();
        $selectedSchoolId = request('school_id');
        $status = request('status', 'all');
        $search = trim((string) request('search'));

        $liquidations = LiquidationReport::with(['school', 'submitter', 'procurementRequest', 'transaction'])
            ->whereIn('school_id', $schoolIds)
            ->when($selectedSchoolId && $schoolIds->contains((int) $selectedSchoolId), fn ($query) => $query->where('school_id', $selectedSchoolId))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('report_number', 'like', "%{$search}%")
                        ->orWhere('ors_number', 'like', "%{$search}%")
                        ->orWhere('purpose', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('school', fn ($schoolQuery) => $schoolQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('procurementRequest', fn ($requestQuery) => $requestQuery->where('title', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->get();

        $allLiquidations = LiquidationReport::whereIn('school_id', $schoolIds)->get();
        $approvedThisMonth = $allLiquidations
            ->where('status', 'approved')
            ->filter(fn ($report) => $report->approved_at?->isSameMonth(now()))
            ->count();
        $availableProcurements = ProcurementRequest::with('school')
            ->whereIn('school_id', $schoolIds)
            ->whereDoesntHave('liquidationReports')
            ->latest()
            ->get();

        return view('liquidation', [
            'schools' => $schools,
            'liquidations' => $liquidations,
            'availableProcurements' => $availableProcurements,
            'selectedSchoolId' => $selectedSchoolId,
            'selectedStatus' => $status,
            'search' => $search,
            'isMasterUser' => $this->isMasterUser($user),
            'metrics' => [
                'total' => $allLiquidations->count(),
                'forReview' => $allLiquidations->where('status', 'for_review')->count(),
                'pendingDocuments' => $allLiquidations->where('status', 'pending_documents')->count(),
                'approvedThisMonth' => $approvedThisMonth,
                'totalAmount' => $allLiquidations->sum('amount'),
            ],
            'statusCounts' => [
                'draft' => $allLiquidations->where('status', 'draft')->count(),
                'for_review' => $allLiquidations->where('status', 'for_review')->count(),
                'pending_documents' => $allLiquidations->where('status', 'pending_documents')->count(),
                'approved' => $allLiquidations->where('status', 'approved')->count(),
                'returned' => $allLiquidations->where('status', 'returned')->count(),
            ],
        ]);
    }

    public function storeLiquidation(Request $request)
    {
        abort_unless($request->user()->canCreateObligation(), 403, 'Your role cannot create obligation requests.');
        $schoolIds = $this->scopedSchoolIds();
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'procurement_request_id' => ['nullable', 'exists:procurement_requests,id'],
            'manually_encode_ors_number' => ['nullable', 'boolean'],
            'ors_number' => ['nullable', 'required_if:manually_encode_ors_number,1', 'string', 'max:100', Rule::unique('liquidation_reports', 'ors_number')->where('organization_id', $this->organizationIdForSchool((int) $request->input('school_id')))],
            'redirect_to' => ['nullable', 'in:budget'],
            'source_of_fund' => ['required', 'string', 'max:255'],
            'budget_allocation_id' => ['nullable', 'integer', 'exists:budget_allocations,id'],
            'payee' => ['nullable', 'string', 'max:255'],
            'payee_address' => ['nullable', 'string', 'max:255'],
            'payee_tin' => ['nullable', 'string', 'max:100'],
            'responsibility_center_code' => ['nullable', 'string', 'max:100'],
            'purpose' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['ors_number' => 'ORS serial number', 'manually_encode_ors_number' => 'manual ORS serial option']);
        abort_unless($schoolIds->contains((int) $data['school_id']), 403);
        if (! empty($data['procurement_request_id'])) {
            $procurementRequest = ProcurementRequest::findOrFail($data['procurement_request_id']);
            $this->authorizeProcurementAccess($procurementRequest);
            // The linked PR decides the school, so a mismatched dropdown can't block the entry.
            $data['school_id'] = $procurementRequest->school_id;
            $data['budget_allocation_id'] = $procurementRequest->budget_allocation_id;
            $data['responsibility_center_code'] ??= $procurementRequest->budgetAllocation?->responsibility_center;
            if (empty($data['payee']) && ($awarded = $procurementRequest->awardedPayee())) {
                $data['payee'] = $awarded['name'];
                $data['payee_address'] ??= $awarded['address'];
                $data['payee_tin'] ??= $awarded['tin'];
            }
        }

        if (empty($data['procurement_request_id'])) {
            app(BudgetService::class)->assertAvailable('amount', (int) $data['school_id'], $data['source_of_fund'], (float) $data['amount']);
            $this->assertBudgetItem($data, (float) $data['amount']);
            if (! empty($data['budget_allocation_id'])) {
                $data['responsibility_center_code'] ??= BudgetAllocation::whereKey($data['budget_allocation_id'])->value('responsibility_center');
            }
        }

        $linkedRequest = ! empty($data['procurement_request_id']) ? ProcurementRequest::findOrFail($data['procurement_request_id']) : null;
        $linkedBudget = ! empty($data['budget_allocation_id']) ? BudgetAllocation::findOrFail($data['budget_allocation_id']) : null;
        $organizationId = $this->organizationIdForSchool((int) $data['school_id']);
        $fiscalYear = $linkedBudget?->fiscal_year ?? $linkedRequest?->budgetAllocation?->fiscal_year ?? (int) now()->year;
        app(FiscalYearService::class)->assertOpen($organizationId, (int) $fiscalYear);

        $orsNumber = DB::transaction(function () use ($data, $organizationId, $linkedRequest, $linkedBudget, $fiscalYear) {
            $numbering = app(DocumentNumberService::class);
            $reportNumber = $numbering->next($organizationId, 'liquidation_report', 'LR');
            $orsNumber = ! empty($data['manually_encode_ors_number'])
                ? $data['ors_number']
                : $numbering->next($organizationId, 'obligation_request', 'ORS');
            $masterTransactionId = $linkedRequest?->master_transaction_id ?? $linkedBudget?->master_transaction_id;
            if (! $masterTransactionId) {
                $transaction = app(MasterTransactionService::class)->create(
                    School::findOrFail($data['school_id']),
                    (int) $fiscalYear,
                    $data['purpose'],
                    request()->user(),
                    'liquidation',
                    'created',
                );
                $masterTransactionId = $transaction->id;
                $linkedBudget?->update(['master_transaction_id' => $masterTransactionId]);
                $linkedRequest?->update(['master_transaction_id' => $masterTransactionId]);
            }

            $report = LiquidationReport::create([
                'school_id' => $data['school_id'],
                'master_transaction_id' => $masterTransactionId,
                'procurement_request_id' => $data['procurement_request_id'] ?? null,
                'submitted_by' => request()->user()?->id,
                'report_number' => $reportNumber,
                'ors_number' => $orsNumber,
                'source_of_fund' => $data['source_of_fund'],
                'budget_allocation_id' => $data['budget_allocation_id'] ?? null,
                'payee' => $data['payee'] ?? null,
                'payee_address' => $data['payee_address'] ?? null,
                'payee_tin' => $data['payee_tin'] ?? null,
                'responsibility_center_code' => $data['responsibility_center_code'] ?? null,
                'purpose' => $data['purpose'],
                'amount' => $data['amount'],
                'status' => 'for_review',
                'notes' => $data['notes'] ?? null,
                'submitted_at' => now(),
            ]);
            $report->transaction?->update(['status' => 'liquidation']);
            $report->transaction?->recordEvent('liquidation', 'ors_submitted', null, 'for_review', $report->ors_number, ['liquidation_report_id' => $report->id, 'amount' => $report->amount]);

            return $orsNumber;
        });

        $message = "ORS {$orsNumber} has been created and sent to Accounting for review.";

        return ($data['redirect_to'] ?? null) === 'budget'
            ? redirect()->route('budget')->with('success', $message)
            : back()->with('success', $message);
    }

    public function printOrs(LiquidationReport $liquidationReport)
    {
        $this->authorizeLiquidationAccess($liquidationReport);
        $liquidationReport->load(['school', 'procurementRequest', 'submitter']);
        $staff = SchoolStaff::where('school_id', $liquidationReport->school_id)->get();
        $find = fn (string $needle) => $staff->first(fn ($m) => str_contains(strtolower($m->document_role.' '.$m->position), $needle));

        return view('ors-print', [
            'report' => $liquidationReport,
            'agency' => AgencySetting::first() ?? new AgencySetting,
            'requester' => $find('school head') ?? $find('head'),
            'budgetOfficer' => $find('disburs') ?? $find('budget'),
        ]);
    }

    public function updateLiquidationStatus(Request $request, LiquidationReport $liquidationReport)
    {
        abort_unless($this->isMasterUser(), 403);
        $this->authorizeLiquidationAccess($liquidationReport);

        $data = $request->validate([
            'status' => ['required', Rule::in(['for_review', 'pending_documents', 'approved', 'returned'])],
        ]);
        $previousStatus = $liquidationReport->status;
        $liquidationReport->update([
            'status' => $data['status'],
            'approved_at' => $data['status'] === 'approved' ? now() : null,
        ]);
        $liquidationReport->transaction?->recordEvent('liquidation', 'status_updated', $previousStatus, $data['status'], $liquidationReport->ors_number);

        return back()->with('success', "{$liquidationReport->report_number} status updated.");
    }

    public function googleDrive()
    {
        return view('google-drive', [
            'agency' => AgencySetting::first() ?? new AgencySetting,
        ]);
    }

    public function updateGoogleDriveSettings(Request $request)
    {
        $data = $request->validate([
            'google_drive_folder_name' => ['nullable', 'string', 'max:255'],
            'google_drive_folder_id' => ['nullable', 'string', 'max:255'],
            'google_drive_folder_url' => ['nullable', 'url', 'max:1000'],
        ]);

        $folderId = trim($data['google_drive_folder_id'] ?? '');
        $folderUrl = trim($data['google_drive_folder_url'] ?? '');
        if (! $folderId && $folderUrl && preg_match('~/folders/([^/?#]+)~', $folderUrl, $matches)) {
            $folderId = $matches[1];
        }

        $organizationId = $request->user()?->organization_id;
        $agency = AgencySetting::first() ?? new AgencySetting;
        $agency->fill([
            'google_drive_enabled' => (bool) ($folderId || $folderUrl),
            'google_drive_folder_name' => $data['google_drive_folder_name'] ?: 'ProcureMS Shared Drive',
            'google_drive_folder_id' => $folderId ?: null,
            'google_drive_folder_url' => $folderUrl ?: null,
            'google_drive_connected_at' => now(),
        ]);
        if (Schema::hasColumn('agency_settings', 'organization_id') && ! $agency->organization_id) {
            $agency->organization_id = $organizationId;
        }
        $agency->save();

        return back()->with('success', 'Google Drive settings saved.');
    }

    public function reports()
    {
        return view('reports');
    }

    public function userManagement()
    {
        abort_unless($this->isMasterUser(), 403);

        return view('user-management');
    }

    public function subscriptions()
    {
        abort_unless($this->isMasterUser(), 403);

        return view('subscriptions');
    }

    public function schoolSettings()
    {
        $schoolIds = $this->scopedSchoolIds();
        $isMasterUser = $this->isMasterUser();
        if (request('ui') !== 'staff-save-v7') {
            $parameters = ['ui' => 'staff-save-v7'];
            if ($schoolIds->contains((int) request('school_id'))) {
                $parameters['school_id'] = request('school_id');
            } elseif (! $isMasterUser && $schoolIds->isNotEmpty()) {
                $parameters['school_id'] = $schoolIds->first();
            }

            return redirect()->route('school-settings', array_filter([
                ...$parameters,
            ]));
        }

        $schools = School::withCount(['users', 'procurementRequests'])->whereIn('id', $schoolIds)->orderBy('name')->get();
        $selectedSchool = $schools->firstWhere('id', (int) request('school_id')) ?? ($isMasterUser ? null : $schools->first());
        $selectedOrganization = $selectedSchool?->organization
            ?? (! $isMasterUser ? request()->user()->organization : null);

        return response()->view('school-settings', [
            'agency' => AgencySetting::first() ?? new AgencySetting,
            'schools' => $schools,
            'selectedSchool' => $selectedSchool,
            'selectedOrganization' => $selectedOrganization,
            'staff' => $selectedSchool
                ? SchoolStaff::with('school')->where('school_id', $selectedSchool->id)->orderBy('name')->get()
                : collect(),
            'isMasterUser' => $isMasterUser,
            'pendingPreRegistrations' => $isMasterUser
                ? School::with(['users' => fn ($query) => $query->oldest()])->where('status', 'inactive')->orderBy('created_at')->get()
                : collect(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function updateOrganizationSettings(Request $request)
    {
        $data = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'organization_code' => ['required', 'string', 'max:30'],
            'fiscal_year' => ['required', 'integer', 'between:2000,2100'],
            'default_fund_source' => ['nullable', 'string', 'max:255'],
            'pr_prefix' => ['required', 'alpha_dash', 'max:10'],
            'ors_prefix' => ['required', 'alpha_dash', 'max:10'],
            'dv_prefix' => ['required', 'alpha_dash', 'max:10'],
        ]);

        $organizationId = $this->isMasterUser()
            ? (int) ($data['organization_id'] ?? 0)
            : (int) $request->user()->organization_id;
        abort_unless($organizationId, 403);

        $organization = Organization::findOrFail($organizationId);
        $request->validate([
            'organization_code' => [Rule::unique('organizations', 'organization_code')->ignore($organization->id)],
        ]);

        $organization->update([
            'organization_code' => strtoupper($data['organization_code']),
            'fiscal_year' => $data['fiscal_year'],
            'default_fund_source' => $data['default_fund_source'] ?: null,
            'numbering_preferences' => [
                'purchase_request' => strtoupper($data['pr_prefix']),
                'obligation_request' => strtoupper($data['ors_prefix']),
                'disbursement_voucher' => strtoupper($data['dv_prefix']),
            ],
        ]);

        return back()->with('success', 'Organization defaults and numbering preferences saved.');
    }

    public function updateAgencySettings(Request $request)
    {
        $data = $request->validate([
            'republic_name' => ['nullable', 'string', 'max:255'],
            'agency_name' => ['nullable', 'string', 'max:255'],
            'department_name' => ['required', 'string', 'max:255'],
            'region_name' => ['nullable', 'string', 'max:255'],
            'division_office' => ['nullable', 'string', 'max:255'],
            'division_name' => ['nullable', 'string', 'max:255'],
            'district_name' => ['nullable', 'string', 'max:255'],
            'division_address' => ['nullable', 'string', 'max:1000'],
            'office_section' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'head_name' => ['nullable', 'string', 'max:255'],
            'school_id' => ['nullable', 'exists:schools,id'],
            'department_logo' => ['nullable', 'image', 'max:2048'],
            'division_logo' => ['nullable', 'image', 'max:2048'],
        ]);
        if ($request->hasFile('department_logo')) {
            $data['department_logo_path'] = $request->file('department_logo')->store('logos', 'public');
        }
        if ($request->hasFile('division_logo')) {
            $data['division_logo_path'] = $request->file('division_logo')->store('logos', 'public');
        }
        unset($data['department_logo'], $data['division_logo']);
        $organizationId = $request->user()?->organization_id;
        if ($this->isMasterUser() && ! empty($data['school_id'])) {
            $organizationId = School::withoutGlobalScopes()->findOrFail($data['school_id'])->organization_id;
        }
        unset($data['school_id']);
        AgencySetting::updateOrCreate(
            ['organization_id' => $organizationId],
            $data + ['organization_id' => $organizationId]
        );

        return back()->with('success', 'Agency and department details saved.');
    }

    public function updateSchoolDetails(Request $request)
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'region' => ['nullable', 'string', 'max:255'],
            'division' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'school_type' => ['nullable', 'string', 'max:100'],
            'school_head' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:active,inactive'],
            'school_logo' => ['nullable', 'image', 'max:2048'],
        ]);
        $this->authorizeSchoolAccess((int) $data['school_id']);
        if (! $this->isMasterUser()) {
            unset($data['status']);
        }
        $school = School::findOrFail($data['school_id']);
        unset($data['school_id']);
        if ($request->hasFile('school_logo')) {
            $data['logo_path'] = $request->file('school_logo')->store('logos', 'public');
        }
        unset($data['school_logo']);
        $school->update($data);

        return back()->with('success', 'School details saved.');
    }

    public function approveSchoolRegistration(School $school)
    {
        abort_unless($this->isMasterUser(), 403);

        $school->update(['status' => 'active']);
        $school->organization?->update(['status' => 'active']);

        AuditLog::create([
            'user_id' => request()->user()?->id,
            'school_id' => $school->id,
            'action' => 'approved_school_pre_registration',
            'auditable_type' => School::class,
            'auditable_id' => $school->id,
            'metadata' => ['school_code' => $school->code],
        ]);

        $redirectRoute = request('redirect_to') === 'dashboard' ? 'home' : 'school-settings';
        $redirectParameters = $redirectRoute === 'school-settings' ? ['ui' => 'staff-save-v7', 'school_id' => $school->id] : [];

        return redirect()
            ->route($redirectRoute, $redirectParameters)
            ->with('success', $school->name.' has been approved and activated.');
    }

    public function updateSchoolStaff(Request $request)
    {
        $validated = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'staff' => ['required', 'array'],
            'staff.*.position' => ['nullable', 'string', 'max:255'],
            'staff.*.procurement_role' => ['nullable', 'string', 'max:255'],
            'staff.*.document_role' => ['nullable', 'string', 'max:255'],
            'staff.*.bac_role' => ['nullable', 'string', 'max:255'],
        ]);
        $this->authorizeSchoolAccess((int) $validated['school_id']);
        foreach ($validated['staff'] as $staffId => $roles) {
            SchoolStaff::where('school_id', $validated['school_id'])->whereKey($staffId)->update($roles);
        }

        return back()->with('success', 'School staff and procurement roles saved.');
    }

    public function addSchoolStaff(Request $request)
    {
        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'new_staff' => ['required', 'array', 'min:1'],
            'new_staff.*.name' => ['required', 'string', 'max:255'],
            'new_staff.*.position' => ['nullable', 'string', 'max:255'],
            'new_staff.*.procurement_role' => ['nullable', 'string', 'max:255'],
            'new_staff.*.document_role' => ['nullable', 'string', 'max:255'],
            'new_staff.*.bac_role' => ['nullable', 'string', 'max:255'],
        ]);
        $schoolId = $data['school_id'];
        $this->authorizeSchoolAccess((int) $schoolId);
        unset($data['school_id']);
        foreach ($data['new_staff'] as $staffMember) {
            SchoolStaff::create([...$staffMember, 'school_id' => $schoolId]);
        }

        return redirect(route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $schoolId]).'#staff-list')
            ->with('success', count($data['new_staff']).' staff member(s) added. No login account was created.');
    }

    public function generate(Request $request)
    {
        $request->validate([
            'business_type' => ['required', 'string', 'max:255'],
        ]);

        return response('Generated successfully');
    }
}
