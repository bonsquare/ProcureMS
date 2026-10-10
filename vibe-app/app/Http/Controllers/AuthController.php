<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolTakeoverRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SchoolTakeoverService;
use App\Support\PlaceNames;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function register()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // The sign-in field accepts the email address or the username.
        $login = trim($credentials['email']);
        $field = str_contains($login, '@') ? 'email' : 'username';
        if ($field === 'username') {
            $login = strtolower($login);
        }

        if (! Auth::attempt([$field => $login, 'password' => $credentials['password'], 'status' => 'active'], $request->boolean('remember'))) {
            // Someone who registered to take over a school gets a clear message instead of a generic failure.
            $candidate = User::withoutGlobalScopes()->where($field, $login)->first();
            if ($candidate && Hash::check($credentials['password'], $candidate->password)) {
                $takeover = SchoolTakeoverRequest::where('user_id', $candidate->id)->latest('id')->first();
                if ($takeover?->status === 'pending') {
                    return back()->withErrors(['email' => 'Your registration to take over a school is waiting for the master account approval.'])->onlyInput('email');
                }
                if ($takeover?->status === 'declined') {
                    return back()->withErrors(['email' => 'Your registration to take over a school was declined. Contact the system administrator.'])->onlyInput('email');
                }
            }

            return back()->withErrors(['email' => 'The provided credentials are incorrect.'])->onlyInput('email');
        }

        $user = $request->user();
        if (! $user?->seesAllSchools() && $user?->school?->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Your school registration is still pending master account approval.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route('home'));
    }

    public function storeRegistration(Request $request)
    {
        if ($request->input('registration_type') === 'takeover') {
            return $this->storeTakeover($request);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'school_type' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'division' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'system_user_given_name' => ['required', 'string', 'max:100'],
            'system_user_middle_initial' => ['nullable', 'string', 'size:1', 'alpha'],
            'system_user_surname' => ['required', 'string', 'max:100'],
            'system_user_username' => ['required', 'string', 'min:4', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'system_user_position' => ['required', 'string', 'max:255'],
            'system_user_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'system_user_phone' => ['required', 'string', 'max:50'],
            'system_user_password' => ['required', 'string', 'min:8', 'confirmed'],
            'system_user_confirmed' => ['accepted'],
            'privacy_accepted' => ['accepted'],
        ], [
            'system_user_confirmed.accepted' => 'Please confirm that your full name and username are final.',
            'privacy_accepted.accepted' => 'Please read and accept the Privacy Policy to register.',
            'system_user_middle_initial.size' => 'The middle initial is one letter only, or leave it blank.',
            'system_user_middle_initial.alpha' => 'The middle initial is one letter only, or leave it blank.',
        ]);
        $data = PlaceNames::snapFields($data, ['region' => 'region', 'division' => 'division', 'district' => 'district']);

        $acceptedAt = now();

        DB::transaction(function () use ($data, $acceptedAt) {
            $organization = Organization::create([
                'name' => $data['name'],
                'slug' => 'org-'.Str::lower(Str::random(12)),
                'status' => 'pending',
                'fiscal_year' => now()->year,
            ]);
            $organization->update(['organization_code' => sprintf('ORG-%06d', $organization->id)]);
            $schoolData = collect($data)->except([
                'system_user_given_name',
                'system_user_middle_initial',
                'system_user_surname',
                'system_user_username',
                'system_user_position',
                'system_user_email',
                'system_user_phone',
                'system_user_password',
                'system_user_password_confirmation',
                'system_user_confirmed',
                'privacy_accepted',
            ])->all();
            $schoolData['status'] = 'inactive';
            $schoolData['organization_id'] = $organization->id;

            $nextNumber = max(1000, ((int) School::max('id')) + 1000);
            do {
                $schoolData['code'] = 'SCH-'.$nextNumber++;
            } while (School::where('code', $schoolData['code'])->exists());

            $school = School::create($schoolData);

            // The first user of a school is always its administrator; the form does not offer a role.
            $middleInitial = filled($data['system_user_middle_initial'] ?? null) ? strtoupper($data['system_user_middle_initial']).'.' : null;
            $systemUser = User::create([
                'name' => collect([trim($data['system_user_given_name']), $middleInitial, trim($data['system_user_surname'])])->filter()->implode(' '),
                'username' => strtolower($data['system_user_username']),
                'email' => $data['system_user_email'],
                'phone' => $data['system_user_phone'],
                'password' => $data['system_user_password'],
                'role' => 'school_admin',
                'organization_id' => $organization->id,
                'school_id' => $school->id,
                'position' => $data['system_user_position'],
                'privacy_accepted_at' => $acceptedAt,
                'privacy_policy_version' => User::PRIVACY_POLICY_VERSION,
            ]);

            Subscription::create([
                'organization_id' => $organization->id,
                'school_id' => $school->id,
                'user_id' => $systemUser->id,
                'plan' => 'trial',
                'billing_cycle' => 'monthly',
                'amount' => 0,
                'payment_status' => 'pending',
                'status' => 'trial',
                'starts_at' => now(),
                'subscription_end' => now()->addDays(30),
            ]);

            AuditLog::create([
                'user_id' => null,
                'school_id' => $school->id,
                'action' => 'submitted_school_pre_registration',
                'auditable_type' => School::class,
                'auditable_id' => $school->id,
                'metadata' => ['system_user_id' => $systemUser->id, 'privacy_accepted_at' => $acceptedAt->toDateTimeString(), 'privacy_policy_version' => User::PRIVACY_POLICY_VERSION],
            ]);
        });

        return redirect()
            ->route('login')
            ->with('success', 'Pre-registration submitted. Please wait for the master account to approve and activate your school.');
    }

    /** A new person asks to take over a school that has no user; no school is created. */
    private function storeTakeover(Request $request)
    {
        $data = $request->validate([
            'takeover_note' => ['nullable', 'string', 'max:500'],
            'system_user_given_name' => ['required', 'string', 'max:100'],
            'system_user_middle_initial' => ['nullable', 'string', 'size:1', 'alpha'],
            'system_user_surname' => ['required', 'string', 'max:100'],
            'system_user_username' => ['required', 'string', 'min:4', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'system_user_position' => ['required', 'string', 'max:255'],
            'system_user_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'system_user_phone' => ['required', 'string', 'max:50'],
            'system_user_password' => ['required', 'string', 'min:8', 'confirmed'],
            'system_user_confirmed' => ['accepted'],
            'privacy_accepted' => ['accepted'],
        ], [
            'system_user_confirmed.accepted' => 'Please confirm that your full name and username are final.',
            'privacy_accepted.accepted' => 'Please read and accept the Privacy Policy to register.',
            'system_user_middle_initial.size' => 'The middle initial is one letter only, or leave it blank.',
            'system_user_middle_initial.alpha' => 'The middle initial is one letter only, or leave it blank.',
        ]);

        $middleInitial = filled($data['system_user_middle_initial'] ?? null) ? strtoupper($data['system_user_middle_initial']).'.' : null;
        app(SchoolTakeoverService::class)->register([
            'name' => collect([trim($data['system_user_given_name']), $middleInitial, trim($data['system_user_surname'])])->filter()->implode(' '),
            'username' => strtolower($data['system_user_username']),
            'email' => $data['system_user_email'],
            'phone' => $data['system_user_phone'],
            'password' => $data['system_user_password'],
            'position' => $data['system_user_position'],
            'privacy_accepted_at' => now(),
            'privacy_policy_version' => User::PRIVACY_POLICY_VERSION,
        ], $data['takeover_note'] ?? null);

        return redirect()
            ->route('login')
            ->with('success', 'Registration submitted. The master account will review your request, assign your Official Station and activate your account.');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
