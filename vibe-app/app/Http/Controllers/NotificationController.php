<?php

namespace App\Http\Controllers;

use App\Models\LiquidationReport;
use App\Models\PaymentProof;
use App\Models\School;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** The bell of every page, and the clock's time: what needs the user's attention now, by role, and the Philippine time of the server. */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $items = ($user->seesAllSchools() ? $this->forMaster($user) : $this->subscriptionNotice($user)->merge($this->work($user)))->merge($this->supportAnswers($user));

        return response()->json(['count' => $items->count(), 'items' => $items->values()]);
    }

    public function serverTime(): JsonResponse
    {
        return response()->json(['now' => now()->toIso8601String(), 'timezone' => config('app.timezone')]);
    }

    /** The user's own support requests that Customer Services answered since the user last opened them. @return Collection<int, array{title: string, detail: string, url: string, level: string}> */
    private function supportAnswers(User $user): Collection
    {
        $unread = SupportMessage::query()->where('user_id', $user->id)
            ->whereRaw("(select max(id) from support_replies where support_replies.support_message_id = support_messages.id and support_replies.sender = 'staff') > coalesce(user_read_reply_id, 0)")
            ->selectRaw("id, subject, (select max(id) from support_replies where support_replies.support_message_id = support_messages.id and support_replies.sender = 'staff') as newest")
            ->latest('updated_at')->get();
        if ($unread->isEmpty()) {
            return collect();
        }
        $one = $unread->count() === 1;

        return collect([$this->item(
            $one ? 'New reply from Customer Services' : $unread->count().' support requests have a new reply',
            $one ? Str::limit((string) ($unread->first()->subject ?: 'Support request'), 80) : 'Open Support to read the answers.',
            $one ? route('support.show', $unread->first()) : route('support'),
            'info',
            'support_agent',
            'support:'.$unread->map(fn (SupportMessage $message) => $message->id.'-'.$message->newest)->implode(','),
        )]);
    }

    /** @return Collection<int, array{title: string, detail: string, url: string, level: string}> */
    private function forMaster(User $user): Collection
    {
        $items = collect();
        if ($user->hasAccess('schools') && ($waiting = School::withoutGlobalScopes()->where('status', 'inactive')->count()) > 0) {
            $items->push($this->item($waiting.' '.Str::plural('school', $waiting).' waiting for activation', 'A school registered and needs to be activated.', route('subscriptions'), 'warn'));
        }
        if ($user->hasAccess('subscriptions') && ($proofs = PaymentProof::withoutGlobalScopes()->whereIn('status', PaymentProof::OPEN)->count()) > 0) {
            $items->push($this->item($proofs.' '.Str::plural('proof', $proofs).' of payment to review', 'Check the proof in the school\'s Google Drive, then approve or reject it.', route('subscriptions'), 'warn'));
        }

        if (($queue = SupportMessage::query()->where('status', '!=', 'resolved')->whereNull('assigned_name')->count()) > 0) {
            $items->push($this->item($queue.' support '.Str::plural('request', $queue).' waiting for someone', 'Open the support queue and take or assign them.', route('support', ['filter' => 'unassigned']), 'warn', 'support_agent', 'support-queue:'.$queue));
        }

        return $items;
    }

    /** @return Collection<int, array{title: string, detail: string, url: string, level: string}> */
    private function subscriptionNotice(User $user): Collection
    {
        $subscription = $user->activeSubscription();
        if (! $subscription) {
            return collect();
        }
        $status = strtolower((string) $subscription->status);
        $days = $subscription->subscription_end ? (int) now()->startOfDay()->diffInDays($subscription->subscription_end->copy()->startOfDay(), false) : null;
        $url = route('subscription');
        $left = fn (int $count) => $count.' '.Str::plural('day', $count);
        $expired = $days !== null && $days < 0 && in_array($status, ['trial', 'active', 'free'], true);

        $item = match (true) {
            $status === 'expired', $expired => $this->item('Your subscription has expired: the school is read-only', 'Send your payment and proof to continue.', $url, 'error'),
            $status === 'suspended' => $this->item('Your subscription is suspended: the school is read-only', 'Contact the administrator.', $url, 'error'),
            $status === 'grace_period' => $this->item('Grace period: pay to keep the school writable', 'Your plan ended; the school becomes read-only when the grace period ends.', $url, 'warn'),
            $status === 'trial' => $this->item($days === 0 ? 'Trial: ends today' : 'Trial: '.$left($days).' left', 'Printing and payments are limited in the trial. Subscribe to remove the limits.', $url, $days <= 1 ? 'warn' : 'info'),
            $status === 'active' && $days !== null && $days <= 30 => $this->item('Your Annual plan ends in '.$left($days), 'Renew to keep the school writable.', $url, 'warn'),
            $status === 'free' && $days !== null && $days <= 7 => $this->item('Your Free plan ends in '.$left($days), 'Then the school becomes read-only unless it is paid.', $url, 'warn'),
            default => null,
        };

        return collect($item ? [$item] : []);
    }

    /** @return Collection<int, array{title: string, detail: string, url: string, level: string}> */
    private function work(User $user): Collection
    {
        $items = collect();
        $reports = fn () => LiquidationReport::query()->where('school_id', $user->school_id);

        if ($user->hasPermission('liquidation.create') && ($returned = $reports()->where('status', 'returned')->count()) > 0) {
            $items->push($this->item($returned.' ORS returned to you', 'Accounting returned it with a remark. Correct it and send it again.', route('budget'), 'warn'));
        }
        if ($user->hasPermission('cash.pay') && ($waiting = $reports()->where('status', 'approved')->whereNotNull('dv_number')->whereNull('paid_at')->where('dv_non_cash', false)->count()) > 0) {
            $items->push($this->item($waiting.' '.Str::plural('DV', $waiting).' waiting for payment', 'Record the payment in Cash.', route('cash'), 'info'));
        }
        if ($user->hasPermission('accounting.approve') && ($review = $reports()->where('status', 'for_review')->count()) > 0) {
            $items->push($this->item($review.' ORS for your review', 'Review it, then approve it or ask for the missing papers.', route('accounting', ['tab' => 'for_review']), 'info'));
        }
        if ($user->hasPermission('accounting.approve') && ($ready = $reports()->where('status', 'approved')->whereNull('dv_number')->count()) > 0) {
            $items->push($this->item($ready.' ORS ready for a DV', 'Create the Disbursement Voucher that goes to Cash.', route('accounting', ['tab' => 'for_dv']), 'info'));
        }
        if ($user->hasPermission('accounting.approve') && ($missing = $reports()->where('status', 'pending_documents')->count()) > 0) {
            $items->push($this->item($missing.' ORS with missing papers', 'Papers are asked for before the ORS can go on.', route('accounting', ['tab' => 'pending_documents']), 'warn'));
        }

        return $items;
    }

    /** @return array{title: string, detail: string, url: string, level: string, icon: ?string, key: ?string} */
    private function item(string $title, string $detail, string $url, string $level, ?string $icon = null, ?string $key = null): array
    {
        return ['title' => $title, 'detail' => $detail, 'url' => $url, 'level' => $level, 'icon' => $icon, 'key' => $key];
    }
}
