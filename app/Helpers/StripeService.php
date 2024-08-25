<?php
/**
 * Created by PhpStorm.
 * User: Sara
 * Date: 8/23/2024
 * Time: 10:23 PM
 */
namespace App\Helpers;

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Charge;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(env('STRIPE_SECRET') ?? config('services.stripe.secret'));
    }

    public function createCustomer($email, $token)
    {
        return Customer::create([
            'email' => $email,
            'source' => $token,
        ]);
    }

    public function createCharge($customerId, $amount)
    {
        return Charge::create([
            'customer' => $customerId,
            'amount' => $amount,
            'currency' => 'usd',
        ]);
    }
}
