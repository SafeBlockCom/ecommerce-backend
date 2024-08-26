<?php

namespace App\Console\Commands;

use App\Helpers\AppException;
use App\Helpers\ChainService;
use App\Models\Closet;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentStatusUpdate;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class CheckPaymentStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $signature = 'payment:check-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Stripe payment intent status and update database';

    /**
     * Execute the console command.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // Set your Stripe API key
        Stripe::setApiKey(env('STRIPE_SECRET'));

        // Fetch payment status updates that need to be checked
        $paymentStatusUpdates = Order::leftJoin('payment_status_updates', 'orders.id', '=', 'payment_status_updates.order_id')
            ->where(function ($query) {
                $query->whereIn('payment_status_updates.updated_payment_status', ['requires_payment_method', 'requires_confirmation', 'requires_action'])
                    ->where('payment_status_updates.retry_attempt_count', '<', 5);
            })
            ->orWhereNull('payment_status_updates.order_id')
            ->select('orders.*', 'payment_status_updates.updated_payment_status', 'payment_status_updates.retry_attempt_count')
            ->get();

        foreach ($paymentStatusUpdates as $statusUpdate) {
            try {
                // Retrieve the payment intent status from Stripe
                $paymentIntent = PaymentIntent::retrieve($statusUpdate->order_id);
                $currentStatus = $paymentIntent->status;

                // If the status has changed, update the database
                if ($currentStatus !== $statusUpdate->updated_payment_status) {

                    $statusUpdate->prev_payment_status = $statusUpdate->updated_payment_status;
                    $statusUpdate->updated_payment_status = $currentStatus;
                    $statusUpdate->retry_attempt_count += 1;
                    $statusUpdate->last_retry_attempted = Carbon::now();
                    $statusUpdate->save();
                } else {
                    // Increment the retry attempt if the status is the same
                    $statusUpdate->retry_attempt_count += 1;
                    $statusUpdate->last_retry_attempted = Carbon::now();
                    $statusUpdate->save();
                }

                // Log the result
//                AppException::log("Payment intent status updated for Order ID {$statusUpdate->order_id}: {$currentStatus}");

            } catch (\Exception $e) {
//                AppException::log("Failed to check payment status for Order ID {$statusUpdate->order_id}: " . $e->getMessage());
                AppException::log($e);
            }

            $order = Order::findById($statusUpdate->id);
            // Add Blockchain
            if(empty($order->block_ref)) {
                $blockchain = self::blockchain($order, $order->customer, $order->closet);
                echo $blockchain;
                $response['chain'] = $blockchain;
                $order->block_ref = $blockchain;
                $order->save();
            }
        }

        return 0;
    }

    private static function blockchain($order, $customer, $closet)
    {
        $senderName = $customer->first_name . " " . $customer->last_name;
        $body = [
            'type' => "order",
            'sender' => $senderName,
            'recipient' => isset($closet->closet_reference) ? $closet->closet_reference : $senderName,
            'amount' => $order->total_amount,
            'data' => [
                "orderId" => $order->order_id
            ],
        ];
        $result = (array)ChainService::addTransaction($body);
        return $result['data']['transaction']['identifier'];
    }

}
