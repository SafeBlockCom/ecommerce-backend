<?php

namespace App\Console\Commands;

use App\Helpers\AppException;
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
        $paymentStatusUpdates = PaymentStatusUpdate::whereIn('updated_payment_status', ['requires_payment_method', 'requires_confirmation', 'requires_action'])
            ->where('retry_attempt_count', '<', 5)
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
                AppException::info("Payment intent status updated for Order ID {$statusUpdate->order_id}: {$currentStatus}");

            } catch (\Exception $e) {
                AppException::log("Failed to check payment status for Order ID {$statusUpdate->order_id}: " . $e->getMessage());
            }
        }

        return 0;
    }
}
