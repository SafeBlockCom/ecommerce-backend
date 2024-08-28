<?php

namespace App\Console\Commands;

use App\Helpers\ChainService;
use App\Helpers\Constant;
use App\Models\Closet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use function Ramsey\Uuid\v4;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Customer as StripeCustomer;
use Stripe\PaymentMethod;
use App\Models\Order;
use Faker\Factory as Faker;

class GenerateOrders extends Command
{
    protected $signature = 'generate:orders {count=10}';
    protected $description = 'Generate n orders with successful payment on Stripe using a non-3DS card';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $count = (int)$this->argument('count');
        Stripe::setApiKey(env('STRIPE_SECRET'));
        $faker = Faker::create();

        $closet = Closet::findByReference('144879bc-cb61-46b8-b947-2cbe175489cd');
        for ($i = 0; $i < $count; $i++) {
            // Generate random customer data using Faker
            $firstName = $faker->firstName;
            $lastName = $faker->lastName;
            $email = $faker->email;
            $phoneNumber = $faker->phoneNumber;
            $address = $faker->address;
            $city = $faker->city;
            $state = $faker->state;
            $postalCode = $faker->postcode;
            $country = $faker->countryCode;

            $ref = v4();
            DB::table('customers')->insert([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'country_code' => 92,
                'phone_number' => $phoneNumber,
                'country_id' => 77,
                'password' => Hash::make('123456'),
                'status' => Constant::CUSTOMER_STATUS['Active'],
                'subscription_status' => Constant::Yes,
                'identifier' => $ref,
            ]);
            $orderCustomer = DB::table('customers')->where('identifier', $ref)->first();
            $billingDetails = [
                'f_name' => $firstName,
                'l_name' => $lastName,
                'email' => $email,
                'phone_number' => $phoneNumber,
                'country' => $country,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'postal_code' => $postalCode
            ];
            // Create Order in the database
            $order = Order::create([
                'total_amount' => 111,
                'order_id' => v4(),
                'sub_total_amount' => 105,
                'discount_amount' => 0,
                'shipment_charges' => 6,
                'closet_id' => $closet->id,
                'customer_id' => $orderCustomer->id,
                'payment_method' => 'stripe',
            ]);
            $products = [
                'product_id' => '65504_ri-251646',
                'name' => 'Kids Shoes',
                'sku' => '65504_ri-251646',
                'quantity' => 3,
                'price' => 35,
                'discount' => 0,
                'sale_price' => 105,
                'shipping_price' => 6,
                'sub_total' => 111,
                'image' => 'http://safeblockcom.test/images/closets/4/products/ri-251646-0.jpeg',
                'short_description' => 'It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout.'
            ];
            // Associate products with the order
            $order->addProducts([$products]);;

            $order->addBillingInfo($billingDetails);

            // Create a new customer in Stripe
            $stripeCustomer = StripeCustomer::retrieve('cus_QkBzTfkmDpwoKu');


            // Step 3: Create a PaymentIntent using the saved payment method
            $paymentIntent = PaymentIntent::create([
                'amount' => 11100, // Amount in cents (e.g., $111.00)
                'currency' => 'usd',
                'customer' => $stripeCustomer->id, // Attach this to the Stripe customer
                'payment_method' => 'pm_1PshcMFd3RlWo8KlkhPXC8j9', // Use the saved payment method or test token
                'off_session' => true,
                'confirm' => true, // Automatically confirm the payment
            ]);


            $order->payment_intent_id = $paymentIntent->id;
            $order->save();

            // Check if payment was successful
            if ($paymentIntent->status == 'succeeded') {
                // Add Blockchain
                $blockchain = self::blockchain($order, $orderCustomer, $closet);
                $order->block_ref = $blockchain;
                $order->save();
                $this->info("Order {$order->id} created successfully with Stripe payment.");
            } else {
                $this->error("Payment for Order $i failed.");
            }
        }

        $this->info("{$count} orders generated successfully.");
    }

    private
    static function blockchain($order, $customer, $closet)
    {
        $body = [
            'type' => "order",
            'sender' => $customer->first_name . " " . $customer->last_name,
            'recipient' => $closet->closet_reference,
            'amount' => $order->total_amount,
            'data' => [
                "orderId" => $order->order_id
            ],
        ];
        $result = (array)ChainService::addTransaction($body);
        return $result['data']['transaction']['identifier'];
    }

}
