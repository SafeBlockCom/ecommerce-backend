<?php
/**
 * Created by PhpStorm.
 * User: Sara
 * Date: 6/28/2023
 * Time: 12:05 AM
 */

namespace App\Http\Controllers\Api;


use App\Helpers\ApiResponseHandler;
use App\Helpers\AppException;
use App\Helpers\ChainService;
use App\Helpers\Constant;
use App\Models\Closet;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentStatusUpdate;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class OrderController
{
    public function __construct()
    {
        Stripe::setApiKey(env('STRIPE_SECRET') ?? config('services.stripe.secret'));
    }

    /**
     * @OA\Post(
     *     path="/v1/order/create",
     *     tags={"Order"},
     *     summary="Create Order using Access Token",
     *     operationId="createOrder",
     *
     *     @OA\Response(response=200,description="Success"),
     *
     *     @OA\RequestBody(
     *         description="Create Order",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="total_amount",
     *                     description="Order Total Amount",
     *                     type="number"
     *                 ),
     *                 @OA\Property(
     *                     property="sub_total_amount",
     *                     description="Order Sub Total Amount",
     *                     type="number"
     *                 ),
     *                 @OA\Property(
     *                     property="discount_amount",
     *                     description="Order Discount Amount",
     *                     type="number"
     *                 ),
     *                 @OA\Property(
     *                     property="products",
     *                     description="List of Products in Customer Cart",
     *                     type="array",
     *                     @OA\Items(
     *                              @OA\Property(
     *                                  property="id",
     *                                  description="Product Id",
     *                                  type="string",
     *                              ),
     *                              @OA\Property(
     *                                  property="name",
     *                                  description="Product Name",
     *                                  type="string",
     *                              ),
     *                              @OA\Property(
     *                                  property="sku",
     *                                  description="Product SKU",
     *                                  type="string",
     *                              ),
     *                              @OA\Property(
     *                                  property="quantity",
     *                                  description="Product Quantity",
     *                                  type="integer",
     *                              ),
     *                              @OA\Property(
     *                                  property="price",
     *                                  description="Product Price",
     *                                  type="number",
     *                              ),
     *                              @OA\Property(
     *                                  property="sale_price",
     *                                  description="Product Sale Price",
     *                                  type="number",
     *                              ),
     *                              @OA\Property(
     *                                  property="image",
     *                                  description="Product Image URL",
     *                                  type="string",
     *                              ),
     *                              @OA\Property(
     *                                  property="short_description",
     *                                  description="Product Short Description",
     *                                  type="string",
     *                              ),
     *                              @OA\Property(
     *                                  property="description",
     *                                  description="Product Description",
     *                                  type="string",
     *                              ),
     *                     )
     *                  ),
     *                 @OA\Property(
     *                     property="customer",
     *                     description="Customer Details",
     *                     type="object",
     *                     @OA\Property(
     *                          property="auth_code",
     *                          description="Customer SSO Auth Code",
     *                          type="string",
     *                      ),
     *                     @OA\Property(
     *                          property="name",
     *                          description="Customer Name",
     *                          type="string",
     *                      ),
     *                     @OA\Property(
     *                          property="email",
     *                          description="Customer Email",
     *                          type="string",
     *                      ),
     *                     @OA\Property(
     *                          property="country_code",
     *                          description="Country Code",
     *                          type="string",
     *                      ),
     *                     @OA\Property(
     *                          property="phone_number",
     *                          description="Customer Phone Number",
     *                          type="string",
     *                      ),
     *                  )
     *              ),
     *         )
     *     ),
     *     security={
     *          {"merchant_access_token": {}}
     *     }
     * )
     */

    public function createOrder(Request $request)
    {

        try {
            $requestData = $request->all();
            $response = [];
            $validator = Validator::make($requestData, Order::getValidationRules('createOrder', $requestData));

            if ($validator->fails()) {
                return ApiResponseHandler::validationError($validator->errors());
            }
            $customer = Customer::findByRef($requestData['customer_ref']);
            if (!$customer) {
                return ApiResponseHandler::failure("Customer not found");
            }
            $closet = Closet::findByReference($requestData['closet_ref']);
            if (!$closet) {
                return ApiResponseHandler::failure("Closet not found");
            }

            $order = Order::createOrder($requestData, $customer->id, $closet->id);
            $order->addProducts($requestData['products']);
            $order->addBillingInfo($requestData['billing_details']);
            $response['order_ref'] = $order->order_id;

            // Add Blockchain
            $blockchain = self::blockchain($order, $customer, $closet);
            $response['chain'] = $blockchain;
            $order->block_ref = $blockchain;
            $order->save();
            return ApiResponseHandler::success($response, "You have created an order.");

        } catch (ApiErrorException $e) {
            AppException::log($e);
            return ApiResponseHandler::failure(__('messages.general.failed'), $e->getMessage());
        } catch (\Exception $e) {
            AppException::log($e);
            return ApiResponseHandler::failure(__('messages.general.failed'), $e->getMessage());
        }
    }

    private static function blockchain($order, $customer, $closet)
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

    /**
     * @OA\Post(
     *     path="/v1/order/pay",
     *     tags={"Order"},
     *     summary="Order Payment using Access Token",
     *     operationId="payOrder",
     *
     *     @OA\Response(response=200,description="Success"),
     *
     *     @OA\RequestBody(
     *         description="Create Order",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="order_ref",
     *                     description="Order Ref",
     *                     type="string"
     *                 ),
     *         )
     *     ),
     *     security={
     *          {"merchant_access_token": {}}
     *     }
     * )
     */

    public function payOrder(Request $request)
    {

        try {
            $requestData = $request->all();
            $response = [];
            $validator = Validator::make($requestData, Order::getValidationRules('pay', $requestData));

            if ($validator->fails()) {
                return ApiResponseHandler::validationError($validator->errors());
            }
            $customer = Customer::findByRef($requestData['customer_ref']);
            if (!$customer) {
                return ApiResponseHandler::failure("Customer not found");
            }
            $order = Order::findByRef($requestData['order_ref']);
            $response['order_ref'] = $order->order_id;

            $paymentIntent = PaymentIntent::create([
                'amount' => $order->total_amount * 100, // Amount in cents
                'currency' => 'GBP',
                'payment_method' => $requestData['payment_method_id'],
                'confirmation_method' => 'automatic',
                'confirm' => true,
                'payment_method_types' => ['card'],
                'return_url' => env("SHOPPING_APP_LINK") . '/order/completed?order_ref=' . $order->order_id,
            ]);
            $order->payment_intent_id = $paymentIntent->id;
            $order->save();

            PaymentStatusUpdate::findOrderStatus($order, $paymentIntent);
            $response['payment_intent'] = $paymentIntent;
            // Retrieve the Payment Intent from Stripe
            $paymentIntent = PaymentIntent::retrieve($paymentIntent->id);
            PaymentStatusUpdate::findOrderStatus($order, $paymentIntent);
            // Confirm the PaymentIntent
            $paymentIntent = $paymentIntent->confirm([
                'payment_method' => $requestData['payment_method_id'],
            ]);

            if ($paymentIntent->status === 'requires_action') {
                $response['order_completed'] = Constant::No;
                $response['order_ref'] = $order->order_id;
                $response['requires_action'] = Constant::Yes;
                $response['payment_intent_client_secret'] = $paymentIntent->client_secret;
                $response['payment_method_id'] = $requestData['payment_method_id'];
                if ($paymentIntent->status === 'requires_action' && $paymentIntent->next_action->type === 'redirect_to_url') {
                    $redirectUrl = $paymentIntent->next_action->redirect_to_url->url;
                    $response['redirect_url'] = $redirectUrl;
                }
                return ApiResponseHandler::success($response, "Payment.");
            }
            // Check the status of the Payment Intent
            $status = $paymentIntent->status;
            if ($status == "succeeded") {
                $order->placement_status = Constant::ORDER_STATUS['placed'];
            } else if ($status == "processing") {
                $order->placement_status = Constant::ORDER_STATUS['awaiting-confirmation'];
            } else if ($status == "canceled") {
                $order->placement_status = Constant::ORDER_STATUS['cancelled'];
            } else {
                $order->placement_status = Constant::ORDER_STATUS['failed'];
            }
            $order->save();
            $response['order_completed'] = Constant::Yes;
            $response['order_ref'] = $order->order_id;
            $response['requires_action'] = Constant::No;
            $response['payment_intent_client_secret'] = $paymentIntent->client_secret;
            if ($paymentIntent->status === 'requires_action' && $paymentIntent->next_action->type === 'redirect_to_url') {
                $redirectUrl = $paymentIntent->next_action->redirect_to_url->url;
                $response['redirect_url'] = $redirectUrl;
            }
            $response['order'] = $order->getStatusCallback();
            return ApiResponseHandler::success($response, "Payment.");

        } catch (ApiErrorException $e) {
            AppException::log($e);
            return ApiResponseHandler::failure(__('messages.general.failed'), $e->getMessage());
        } catch (\Exception $e) {
            AppException::log($e);
            return ApiResponseHandler::failure(__('messages.general.failed'), $e->getMessage());
        }
    }


    public function orderStatus(Request $request)
    {
        try {
            $requestData = $request->all();
            $response = [];
            $validator = Validator::make($requestData, Order::getValidationRules('status', $requestData));

            if ($validator->fails()) {
                return ApiResponseHandler::validationError($validator->errors());
            }
            $order = Order::findByRef($requestData['order_ref']);
            $response['order_ref'] = $order->order_id;

            $paymentIntent = [];
            if(!empty($order->payment_intent_id)) {
                $paymentIntent = PaymentIntent::retrieve($order->payment_intent_id);
            }

            PaymentStatusUpdate::findOrderStatus($order, $paymentIntent);

            // Confirm the Payment Intent
            $response['intent'] = $paymentIntent;
            $response['order'] = $order->getStatusCallback();
            $response['status_updated'] = PaymentStatusUpdate::where('order_id', $order->id)->first();

            return ApiResponseHandler::success($response, "Order status update successfully.");

        } catch (ApiErrorException $e) {
            AppException::log($e);
            return ApiResponseHandler::failure(__('messages.general.failed'), $e->getMessage());
        } catch (\Exception $e) {
            AppException::log($e);
            return ApiResponseHandler::failure(__('messages.general.failed'), $e->getMessage());
        }
    }

    public function fetchOrderStatus(Request $request, $orderRef)
    {
        try {
            $order = Order::findByRef($orderRef);
            $response['order_ref'] = $order->order_id;

            if (empty($order)) {
                return ApiResponseHandler::failure('Order not found');
            }

            // Retrieve the Payment Intent from Stripe
            $paymentIntent = [];
            if(!empty($order->payment_intent_id)) {
                $paymentIntent = PaymentIntent::retrieve($order->payment_intent_id);
            }
//            return [
//                '$order' => $order,
//                '$paymentIntent' =>$paymentIntent
//            ];
            PaymentStatusUpdate::findOrderStatus($order, $paymentIntent);
            $response['order'] = $order->getStatusCallback();
            $response['status_updated'] = PaymentStatusUpdate::where('order_id', $order->id)->first();

            return ApiResponseHandler::success($response, "Order status update successfully.");

        } catch (ApiErrorException $e) {
            AppException::log($e);
            return ApiResponseHandler::failure(__('messages.general.failed'), $e->getMessage());
        } catch (\Exception $e) {
            AppException::log($e);
            return ApiResponseHandler::failure(__('messages.general.failed'), $e->getMessage());
        }
    }
}
