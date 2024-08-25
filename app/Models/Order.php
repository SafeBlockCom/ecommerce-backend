<?php

namespace App\Models;

use App\Helpers\Constant;
use App\Http\Traits\LoggingTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use function Ramsey\Uuid\v4;

class Order extends Model
{
    protected $table = "orders";
    protected $guarded=[];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function closet()
    {
        return $this->belongsTo(Closet::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function billingInfo()
    {
        return $this->hasOne(OrderBillingDetails::class);
    }

    public static function getValidationRules($type, $params = [])
    {
        $rules = [
            'pay' => [
                'order_ref' => "required",
                'payment_method_id' => "required",
            ],
            'status' => [
                'order_ref' => "required",
            ],
            'createOrder' => [
                'billing_details.f_name' => 'required|string|max:191',
                'billing_details.l_name' => 'required|string|max:191',
                'billing_details.email' => 'required|email',
                'billing_details.phone_number' => 'required|string',
                'billing_details.country' => 'required|string',
                'billing_details.state' => 'required|string',
                'billing_details.city' => 'required|string',
                'billing_details.postal_code' => 'required|string',
                'billing_details.address' => 'required|string',

                'customer_ref' => 'required',
                'closet_ref' => 'required',

                'products' => 'required',
                'products.*.id' => 'required|string',
                'products.*.name' => 'required|string',
                'products.*.sku' => 'nullable|string',
                'products.*.quantity' => 'required|numeric|gt:0',
                'products.*.price' => 'required|gt:0|numeric',
                'products.*.sale_price' => 'required|gt:0|numeric',
                'products.*.image' => 'nullable|string',
                'products.*.description' => 'nullable|string',
                'products.*.short_description' => 'nullable|string',

                'discount_amount' => 'required|numeric|gte:0',
                'sub_total_amount' => 'required|numeric|gt:0',
                'total_amount' => 'required|numeric|gt:0',
                'shipment_charges' => 'nullable',
            ],
        ];

        return $rules[$type];
    }

    public static function findByRef($ref){
        return self::where('order_id', $ref)->first();
    }

    public static function createOrder($request, $customer_id, $closet_id)
    {
        $orderData = [
            'customer_id' => $customer_id,
            'closet_id' => $closet_id,
            'order_id' => v4(),
            'placement_status' => Constant::ORDER_STATUS['created'],
            'shipment_charges'  => $request['shipment_charges'],
            'sub_total_amount' => $request['sub_total_amount'],
            'discount_amount' => $request['discount_amount'],
            'total_amount' => $request['total_amount'],
            'payment_method' => $request['payment']
        ];

        return self::create($orderData);
    }

    public function addBillingInfo($details)
    {
        $orderData = [
            'order_id' => $this->id,
            'f_name' => $details['f_name'],
            'l_name' => $details['l_name'],
            'email' => $details['email'],
            'phone_number'  => $details['phone_number'],
            'country' => $details['country'],
            'address' => $details['address'],
            'city' => $details['city'],
            'state' => $details['state'],
            'postal_code' => $details['postal_code']
        ];

        return OrderBillingDetails::create($orderData);
    }

    public function addProducts($products)
    {
        $batchInsertData = [];

        foreach ($products as $productItem) {
            $batchInsertData[] = [
                'order_id' => $this->id,
                'product_id' => $productItem['id'] ?? null,
//                'variant_id' => $productItem['variant_id'] ?? null,
                'name' => $productItem['name'] ?? '',
                'sku' => $productItem['sku'] ?? '',
                'quantity' => $productItem['quantity'] ?? '',
                'price' => $productItem['price'] ?? '',
                'sale_price' => $productItem['sale_price'] ?? '',
                'discount' => $productItem['discount'] ?? '',
                'sub_total' => $productItem['sub_total'] ?? '',
                'total' => $productItem['sub_total'] ?? '',
                'image' => $productItem['image'] ?? '',
                'short_description' => $productItem['short_description'] ?? '',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        OrderItem::insert($batchInsertData);
    }

    public function getStatusCallback(){
        $result = [];
        $result['order'] = [
            "order_id" => $this->order_id,
            "customer_id" => $this->customer_id,
            "placement_status" => $this->placement_status,
            "total_amount" => $this->total_amount,
            "sub_total_amount" => $this->sub_total_amount,
            "discount_amount" => $this->discount_amount,
            "shipment_charges" => $this->shipment_charges,
            "payment_intent_id" => $this->payment_intent_id,
            "block_ref" => $this->block_ref,
            "payment_method" => $this->payment_method,
            'created_at' => $this->created_at->format('Y-m-d h:m:s')
        ];
        $result['closet'] = $this->closet->closet_name;
        $result['order_items'] = $this->orderItems;
        $result['billing_details'] = $this->billingInfo;
        return $result;
    }
}
