<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stripe\PaymentIntent;

class PaymentStatusUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'prev_payment_status',
        'updated_payment_status',
        'retry_attempt_count',
        'last_retry_attempted',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class,'order_id');
    }


    public static function findOrderStatus($order, $paymentIntent) {
        $orderStatusUpdate = PaymentStatusUpdate::where('order_id', $order->id)->first();
        if(empty($orderStatusUpdate)) {
            $paymentStatusUpdate = '';
            if(!empty($order->payment_intent_id)) {
                $paymentStatusUpdate = PaymentIntent::retrieve($order->payment_intent_id);
            }
            PaymentStatusUpdate::create([
                "order_id" => $order->id,
                "prev_payment_status" => 0,
                "updated_payment_status" => empty($paymentStatusUpdate) ? 0 : $paymentStatusUpdate->status,
                "retry_attempt_count" => 0,
                "last_retry_attempted" => Carbon::now(),
            ]);
        }else {
            $paymentStatusUpdate = PaymentStatusUpdate::where('order_id', $order->id)
                ->whereIn('updated_payment_status', ['requires_payment_method', 'requires_confirmation', 'requires_action'])
                ->where('retry_attempt_count', '<', 5)
                ->first();

            if(empty($paymentStatusUpdate)) {
                PaymentStatusUpdate::create([
                    "order_id" => $order->id,
                    "prev_payment_status" => 0,
                    "updated_payment_status" => empty($paymentStatusUpdate) ? 0 : $paymentStatusUpdate->status,
                    "retry_attempt_count" => 0,
                    "last_retry_attempted" => Carbon::now(),
                ]);
            }else {
                $paymentStatusUpdate->where('order_id', $order->id)->update([
                    "prev_payment_status" => empty($paymentStatusUpdate) ? 0 : $paymentStatusUpdate->status,
                    "updated_payment_status" => empty($paymentIntent) ? 0 : optional($paymentIntent)->status,
                    "retry_attempt_count" => empty($paymentStatusUpdate) ? 0 : $paymentStatusUpdate->retry_attempt_count+1,
                    "last_retry_attempted" => Carbon::now(),
                ]);
            }
        }
    }
}
