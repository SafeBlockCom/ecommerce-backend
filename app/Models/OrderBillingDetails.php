<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderBillingDetails extends Model
{
    use HasFactory;

    protected $table = "order_billing_details";
    protected $fillable = [
        'order_id',
        'f_name',
        'l_name',
        'email',
        'phone_number',
        'country',
        'address',
        'city',
        'state',
        'postal_code',
    ];
}
