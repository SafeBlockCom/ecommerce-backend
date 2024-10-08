<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\Products\CategoryProductController;
use App\Http\Controllers\Api\Products\FeaturedProductController;
use App\Http\Controllers\Api\Products\RecentlyViewedProductController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\MetadataController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\PlaygroundTestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Closet\ClosetProductsController;
use App\Http\Controllers\Api\Closet\ClosetController;
use App\Http\Controllers\Api\OrderController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

//Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//    return $request->user();
//});
Route::post('cloudinary/image-upload-test', [PlaygroundTestController::class, 'uploadImageToCloudinary']);

Route::get('countries-meta-data', [MetadataController::class, 'getMetaData']);
Route::get('country-list', [MetadataController::class, 'getCountriesList']);

Route::get('meta-data', [HomeController::class, 'getMetaContent']);
Route::get('mega-menu', [HomeController::class, 'getMegaMenu']);

Route::post('login', [AuthController::class, "login"]);
Route::post('register', [AuthController::class, "register"]);

#Home Page
Route::get('homepage', [HomeController::class, 'getHomePageContent']);
Route::get('homepage/featured-section', [HomeController::class, 'getHomePageFeaturedContent']);

Route::get('/products',  [ProductController::class, 'getProducts']);
Route::post('/product/{handle}',  [ProductController::class, 'getProductDetail']);
#Featured Products
Route::get('/featured-products',  [FeaturedProductController::class, 'getFeaturedProducts']);
Route::post('/filter/featured-products', [FeaturedProductController::class, 'getFeaturedProducts']);
#categories
Route::get('/categories', [CategoryController::class, 'getCategories']);
Route::get('/categories/{slug}', [CategoryController::class, 'getSubCategories']);
#category products
Route::post('/categories/{slug}/products', [CategoryProductController::class, 'getProducts']);
Route::get('/filter/categories/{slug}/products', [CategoryProductController::class, 'getFilteredCategoryProducts']);

#Closet List
Route::get('/closets', [ClosetController::class, 'getAllClosets']);
Route::get('/closets/trending', [ClosetController::class, 'getAllTrendingClosets']);
#Closets
//Route::get('/closet/{slug}', [ClosetProductsController::class, 'getCloset']);
Route::get('/closet/{reference}', [ClosetController::class, 'getClosetDetails']);
#Closet Products
Route::get('/closet/{slug}/product', [ClosetProductsController::class, 'getClosetProducts']);
Route::get('/filter/closet/{slug}/product', [ClosetProductsController::class, 'getFilteredClosetProducts']);
#Closet Category
Route::get('/closet/{slug}/category/{catSlug}', [ClosetController::class, 'getClosetCategory']);
Route::get('/closet/{slug}/category/{catSlug}/product', [ClosetProductsController::class, 'getClosetCategoryProducts']);
//, 'auth:api'

Route::post('customer', [CustomerController::class, 'getCustomerMetaContent'])->middleware(['tokenValidation']);

Route::post('order/status',  [OrderController::class, 'orderStatus']);
Route::get('order/status/{order_ref}',  [OrderController::class, 'fetchOrderStatus']);

Route::middleware(['tokenValidation'])->group(function () {

    Route::post('send/otp', [OtpController::class, "sendOtp"]);
    Route::post('resend/otp', [OtpController::class, "resendOtp"]);
    Route::post('verify/otp', [OtpController::class, "verifyOtp"]);

    Route::post('/closet/create', [CustomerController::class, 'createCloset']);
    Route::post('/closet/{reference}/edit', [CustomerController::class, 'updateCloset']);

    Route::get('/meta-data/product',  [ClosetProductsController::class, 'getProductMeta']);
    Route::post('/add/product',  [ClosetProductsController::class, 'addProduct']);

    #Recently Viewed Products
    Route::post('/recently-viewed-products',  [RecentlyViewedProductController::class, 'getRecentlyViewedProducts']);

    Route::post('/order/create',  [OrderController::class, 'createOrder']);
    Route::post('/order/pay',  [OrderController::class, 'payOrder']);
//    Route::post('/closet/create', [CustomerController::class, 'createCloset']);
    Route::post('product/{productId}',  [ProductController::class, 'getProductDetail']);

});
