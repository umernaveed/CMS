<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MobileCustomerController;
use App\Http\Controllers\Api\WmsSyncController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::prefix('mobile')->group(function () {
    Route::post('/tenant/resolve', [MobileCustomerController::class, 'resolveTenant']);
    Route::post('/login', [MobileCustomerController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [MobileCustomerController::class, 'logout']);
        Route::get('/bootstrap', [MobileCustomerController::class, 'bootstrap']);
        Route::get('/packages', [MobileCustomerController::class, 'packages']);
        Route::get('/invoices', [MobileCustomerController::class, 'invoices']);
        Route::get('/delivery-requests', [MobileCustomerController::class, 'deliveryRequests']);
        Route::get('/addresses', [MobileCustomerController::class, 'addresses']);
        Route::get('/pre-alerts', [MobileCustomerController::class, 'preAlerts']);
        Route::get('/purchase-requests', [MobileCustomerController::class, 'purchaseRequests']);
        Route::get('/wallet', [MobileCustomerController::class, 'wallet']);
        Route::post('/invoices/{invoice}/receipt', [MobileCustomerController::class, 'uploadInvoiceReceipt']);
        Route::post('/device-token', [MobileCustomerController::class, 'registerDeviceToken']);
        Route::get('/notifications', [MobileCustomerController::class, 'notifications']);
    });
});

Route::prefix('wms')->group(function () {
    Route::post('/customers', [WmsSyncController::class, 'customer']);
    Route::post('/customers/export', [WmsSyncController::class, 'exportCustomers']);
    Route::post('/couriers/export', [WmsSyncController::class, 'exportCouriers']);
    Route::post('/packages/next-hawb', [WmsSyncController::class, 'nextPackageNumbers']);
    Route::post('/packages', [WmsSyncController::class, 'package']);
    Route::post('/package-upload', [WmsSyncController::class, 'packageUpload']);
    Route::post('/courier/statuses', [WmsSyncController::class, 'status']);
    Route::get('/labels/{trackingNumber}', [WmsSyncController::class, 'label']);
    Route::get('/labels/{trackingNumber}/html', [WmsSyncController::class, 'labelHtml']);
    Route::delete('/packages', [WmsSyncController::class, 'deletePackage']);
    Route::post('/manifests', [WmsSyncController::class, 'manifest']);
});
