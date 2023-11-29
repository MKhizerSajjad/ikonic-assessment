<?php

namespace App\Services;

use App\Exceptions\AffiliateCreateException;
use App\Mail\AffiliateCreated;
use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class AffiliateService
{
    public function __construct(
        protected ApiService $apiService
    ) {}

    /**
     * Create a new affiliate for the merchant with the given commission rate.
     *
     * @param  Merchant $merchant
     * @param  string $email
     * @param  string $name
     * @param  float $commissionRate
     * @return Affiliate
     */
    public function register(Merchant $merchant, string $email, string $name, float $commissionRate): Affiliate
    {
        $merchantWithEmail = Merchant::whereHas('user', function ($query) use ($email) {
            $query->where('email', $email);
        })->exists();

        $affiliateWithEmail = Affiliate::whereHas('user', function ($query) use ($email) {
            $query->where('email', $email);
        })->exists();

        if ($merchantWithEmail || $affiliateWithEmail ) {
            throw new AffiliateCreateException("Email is already in use");
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => '123456789',
            'type' => User::TYPE_AFFILIATE,
        ]);

        $discountCodeResponse = $this->apiService->createDiscountCode();
        $discountCode = $discountCodeResponse['code'];

        $affiliate = Affiliate::create([
            'user_id' => $user->id,
            'merchant_id' => $merchant->id,
            'commission_rate' => $commissionRate,
            'discount_code' => $discountCode,
        ]);

        Mail::to($user->email)->send(new AffiliateCreated($affiliate));

        return $affiliate;
    }
}
