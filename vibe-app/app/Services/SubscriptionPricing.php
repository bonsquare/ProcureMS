<?php

namespace App\Services;

use App\Models\PromoCode;
use App\Models\School;
use App\Models\Subscription;
use Illuminate\Validation\ValidationException;

/** What a school pays for the Annual plan: the price the master set for it, else a promo code it typed, else the list price. */
class SubscriptionPricing
{
    public const LIST_PRICE = 1000.00;

    public const MINIMUM = 1.00;

    /**
     * @return array{list_price: float, discount: float, amount: float, promo: ?PromoCode, source: 'list'|'school'|'promo'}
     *
     * @throws ValidationException when the promo code is not usable
     */
    public function quote(School $school, ?string $promoCode = null): array
    {
        $promo = filled($promoCode) ? $this->usablePromo((string) $promoCode) : null;

        $charge = $this->schoolCharge($school);
        if ($charge !== null) {
            return $this->result($charge, null, 'school');
        }
        if ($promo) {
            return $this->result($promo->priceFor(self::LIST_PRICE), $promo, 'promo');
        }

        return $this->result(self::LIST_PRICE, null, 'list');
    }

    /** The price the master set for the school and that still applies (its end date itself counts). */
    private function schoolCharge(School $school): ?float
    {
        $subscription = Subscription::withoutGlobalScopes()->where('school_id', $school->id)->whereNotNull('charge_amount')->latest('starts_at')->latest('id')->first();
        if (! $subscription || ($subscription->charge_ends_at && $subscription->charge_ends_at->startOfDay()->lt(now()->startOfDay()))) {
            return null;
        }

        return (float) $subscription->charge_amount;
    }

    private function usablePromo(string $code): PromoCode
    {
        $promo = PromoCode::query()->where('code', strtoupper(trim($code)))->first();
        if (! $promo || ! $promo->usableAt()) {
            throw ValidationException::withMessages(['promo_code' => 'This promo code is not valid.']);
        }

        return $promo;
    }

    /** @return array{list_price: float, discount: float, amount: float, promo: ?PromoCode, source: string} */
    private function result(float $amount, ?PromoCode $promo, string $source): array
    {
        $amount = min(self::LIST_PRICE, max(self::MINIMUM, round($amount, 2)));

        return ['list_price' => self::LIST_PRICE, 'discount' => round(self::LIST_PRICE - $amount, 2), 'amount' => $amount, 'promo' => $promo, 'source' => $source];
    }
}
