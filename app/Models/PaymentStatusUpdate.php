<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
