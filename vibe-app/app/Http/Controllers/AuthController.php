<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The provided credentials are incorrect.'])->onlyInput('email');
        }

        $user = $request->user();
        if ($user?->role !== 'master_user' && $user?->school?->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Your school registration is still pending master account approval.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function storeRegistration(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'school_type' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'division' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'system_user_name' => ['required', 'string', 'max:255'],
            'system_user_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'system_user_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($data) {
            $organization = Organization::create([
                'name' => $data['name'],
                'slug' => 'org-'.Str::lower(Str::random(12)),
                'status' => 'pending',
                'fiscal_year' => now()->year,
            ]);
            $organization->update(['organization_code' => sprintf('ORG-%06d', $organization->id)]);
            $schoolData = collect($data)->except([
                'system_user_name',
                'system_user_email',
                'system_user_password',
                'system_user_password_confirmation',
            ])->all();
            $schoolData['status'] = 'inactive';
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
                'role' => 'school_admin',
                'organization_id' => $organization->id,
                'school_id' => $school->id,
                'position' => 'School Administrator',
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
                'user_id' => null,
                'school_id' => $school->id,
                'action' => 'submitted_school_pre_registration',
                'auditable_type' => School::class,
                'auditable_id' => $school->id,
                'metadata' => ['system_user_id' => $systemUser->id],
            ]);
        });

        return redirect()
            ->route('login')
            ->with('success', 'Pre-registration submitted. Please wait for the master account to approve and activate your school.');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
