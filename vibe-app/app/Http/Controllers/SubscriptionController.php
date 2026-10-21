<?php

namespace App\Http\Controllers;

use App\Exceptions\DriveNotConnected;
use App\Models\AuditLog;
use App\Models\PaymentProof;
use App\Models\School;
use App\Services\GoogleDriveService;
use App\Services\SubscriptionPricing;
use App\Support\PaymentSettings;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** The school's own Subscription page: what it pays, where to pay, and the proof of payment that goes to its own Google Drive. */
class SubscriptionController extends Controller
{
    private const DAILY_LIMIT = 5;

    private const MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public function __construct(private SubscriptionPricing $pricing, private GoogleDriveService $drive) {}

    public function show(Request $request): View
    {
        $school = $this->school($request);
        $subscription = $request->user()->activeSubscription();
        $ends = $subscription?->status === 'grace_period' ? ($subscription->grace_period_end ?? $subscription->subscription_end) : $subscription?->subscription_end;

        return view('subscription', [
            'school' => $school,
            'subscription' => $subscription,
            'daysLeft' => $ends ? (int) now()->startOfDay()->diffInDays($ends->copy()->startOfDay(), false) : null,
            'quote' => $this->pricing->quote($school),
            'settings' => PaymentSettings::get(),
            'proofs' => PaymentProof::query()->where('school_id', $school->id)->latest('id')->limit(10)->get(),
            'driveConnected' => $request->user()->driveIsConnected(),
            'canPay' => $request->user()->role === 'school_admin',
        ]);
    }

    /** The price after the promo code the school typed (the price the master set for the school always wins). */
    public function promo(Request $request): JsonResponse
    {
        $school = $this->school($request);
        $data = $request->validate(['promo_code' => ['nullable', 'string', 'max:40']]);
        $quote = $this->pricing->quote($school, $data['promo_code'] ?? null);

        return response()->json(['list_price' => $quote['list_price'], 'discount' => $quote['discount'], 'amount' => $quote['amount'], 'source' => $quote['source'], 'promo' => $quote['promo']?->code]);
    }

    public function submitProof(Request $request): RedirectResponse
    {
        $school = $this->school($request);
        abort_unless($request->user()->role === 'school_admin', 403);
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric'],
            'promo_code' => ['nullable', 'string', 'max:40'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], ['file.max' => 'The file is too large. The limit is 5 MB.', 'file.mimes' => 'The proof must be a photo (JPG, PNG or WEBP) or a PDF.']);

        try {
            return Cache::lock('subscription-proof:'.$school->id, 60)->block(5, fn () => $this->storeProof($request, $school, $data));
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['proof' => 'Your proof is still being sent. Please wait a moment.']);
        }
    }

    /** One school sends one proof at a time (the lock above), so two presses of the button cannot make two proofs. */
    private function storeProof(Request $request, School $school, array $data): RedirectResponse
    {
        $file = $request->file('file');
        $contents = $file->get();
        $mime = (string) $file->getMimeType();
        $sha = hash('sha256', $contents);
        $this->assertAllowed($school, $data, $mime, $sha);
        $quote = $this->pricing->quote($school, $data['promo_code'] ?? null);
        if (abs((float) $data['amount'] - $quote['amount']) > 0.001) {
            throw ValidationException::withMessages(['amount' => 'The amount to pay is ₱'.number_format($quote['amount'], 2).'.']);
        }

        $connection = $request->user()->driveConnection;
        try {
            $stored = $this->drive->upload($connection, 'Proofs', Str::limit(basename($file->getClientOriginalName()), 150, ''), $contents, $mime);
        } catch (DriveNotConnected $exception) {
            return ($connection->fresh()?->isConnected() ? back() : redirect()->route('google-drive'))->with('error', $exception->getMessage());
        }
        try {
            $this->drive->share($connection, $stored['id'], (string) PaymentSettings::get()['master_email']);
        } catch (\Throwable) {
            $this->drive->delete($connection, $stored['id']);
            throw ValidationException::withMessages(['proof' => 'The proof could not be shared with the administrator. Please try again.']);
        }

        $proof = PaymentProof::create([
            'organization_id' => $school->organization_id, 'school_id' => $school->id, 'user_id' => $request->user()->id, 'subscription_id' => $request->user()->activeSubscription()?->id,
            'reference' => $data['reference'], 'paid_at' => $data['paid_at'], 'list_price' => $quote['list_price'], 'discount_amount' => $quote['discount'], 'promo_code_id' => $quote['promo']?->id, 'amount_to_pay' => $quote['amount'],
            'drive_file_id' => $stored['id'], 'drive_link' => $this->drive->webLink($stored['id']), 'file_name' => Str::limit(basename($file->getClientOriginalName()), 150, ''), 'mime' => $mime, 'size' => $stored['size'], 'sha256' => $sha,
        ]);
        AuditLog::create(['user_id' => $request->user()->id, 'school_id' => $school->id, 'action' => 'subscription_proof_submitted', 'auditable_type' => PaymentProof::class, 'auditable_id' => $proof->id, 'metadata' => ['reason' => null, 'reference' => $proof->reference, 'amount' => (string) $proof->amount_to_pay]]);

        return redirect()->route('subscription')->with('success', 'Your proof of payment was sent. The administrator will check it and activate your school.');
    }

    /** The checks that stop a proof before anything is uploaded; the same file is looked for first, so a repeated file is named as such. */
    private function assertAllowed(School $school, array $data, string $mime, string $sha): void
    {
        if (! in_array($mime, self::MIMES, true)) {
            throw ValidationException::withMessages(['file' => 'The proof must be a photo (JPG, PNG or WEBP) or a PDF.']);
        }
        if (PaymentProof::withoutGlobalScopes()->where('sha256', $sha)->whereIn('status', [PaymentProof::PENDING, PaymentProof::APPROVED])->exists()) {
            throw ValidationException::withMessages(['file' => 'This file was already used as a proof of payment.']);
        }
        if (PaymentProof::query()->where('school_id', $school->id)->where('status', PaymentProof::PENDING)->exists()) {
            throw ValidationException::withMessages(['proof' => 'You already have a proof waiting for review. Please wait for the administrator to decide it.']);
        }
        if (PaymentProof::query()->where('school_id', $school->id)->where('created_at', '>=', now()->startOfDay())->count() >= self::DAILY_LIMIT) {
            throw ValidationException::withMessages(['proof' => 'You can send up to '.self::DAILY_LIMIT.' proofs a day. Please try again tomorrow.']);
        }
        if (blank(PaymentSettings::get()['master_email'])) {
            throw ValidationException::withMessages(['proof' => 'The administrator has not set where proofs are received yet. Please try again later.']);
        }
    }

    private function school(Request $request): School
    {
        $user = $request->user();
        abort_if($user->seesAllSchools() || ! $user->school_id, 403);

        return School::withoutGlobalScopes()->findOrFail($user->school_id);
    }
}
