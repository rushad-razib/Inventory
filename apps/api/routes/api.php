<?php

use App\Http\Controllers\Api\V1\AdjustmentController;
use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CashEntryController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerReturnController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\GoodsReceiptController;
use App\Http\Controllers\Api\V1\ImageController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductImageController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\ReorderAlertController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\SupplierBillController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\SupplierProductController;
use App\Http\Controllers\Api\V1\SupplierReturnController;
use App\Http\Controllers\Api\V1\SupplierSettlementController;
use App\Http\Controllers\Api\V1\TransferController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserImageController;
use App\Http\Controllers\Api\V1\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('web');

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('web');
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/auth/me/image', [AuthController::class, 'meImage']);
        Route::post('/auth/tokens', [AuthController::class, 'createToken']);
        Route::delete('/auth/tokens/{id}', [AuthController::class, 'revokeToken']);

        Route::get('/settings', [SettingsController::class, 'show']);
        Route::patch('/settings', [SettingsController::class, 'update']);

        Route::get('/departments', [DepartmentController::class, 'index']);
        Route::post('/departments', [DepartmentController::class, 'store']);
        Route::patch('/departments/{department}', [DepartmentController::class, 'update']);

        Route::get('/warehouses', [WarehouseController::class, 'index']);
        Route::post('/warehouses', [WarehouseController::class, 'store']);
        Route::patch('/warehouses/{warehouse}', [WarehouseController::class, 'update']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::get('/users/{user}/images', [UserImageController::class, 'index']);
        Route::post('/users/{user}/images', [UserImageController::class, 'store']);

        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::patch('/categories/{category}', [CategoryController::class, 'update']);

        Route::get('/products/lookup', [ProductController::class, 'lookup']);
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::get('/products/{product}', [ProductController::class, 'show']);
        Route::patch('/products/{product}', [ProductController::class, 'update']);
        Route::get('/products/{product}/images', [ProductImageController::class, 'index']);
        Route::post('/products/{product}/images', [ProductImageController::class, 'store']);

        Route::get('/suppliers', [SupplierController::class, 'index']);
        Route::post('/suppliers', [SupplierController::class, 'store']);
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show']);
        Route::patch('/suppliers/{supplier}', [SupplierController::class, 'update']);
        Route::get('/suppliers/{supplier}/products', [SupplierProductController::class, 'index']);
        Route::post('/suppliers/{supplier}/products', [SupplierProductController::class, 'store']);
        Route::patch('/supplier-products/{supplierProduct}', [SupplierProductController::class, 'update']);

        Route::get('/customers/lookup', [CustomerController::class, 'lookup']);
        Route::get('/customers', [CustomerController::class, 'index']);
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::get('/customers/{customer}', [CustomerController::class, 'show']);
        Route::patch('/customers/{customer}', [CustomerController::class, 'update']);
        Route::get('/customers/{customer}/point-entries', [CustomerController::class, 'pointEntries']);

        Route::get('/stock-balances', [StockController::class, 'balances']);
        Route::get('/stock-lots', [StockController::class, 'lots']);
        Route::get('/stock-movements', [StockController::class, 'movements']);

        Route::get('/transfers', [TransferController::class, 'index']);
        Route::post('/transfers', [TransferController::class, 'store']);
        Route::post('/transfers/{transfer}/ship', [TransferController::class, 'ship']);
        Route::post('/transfers/{transfer}/receive', [TransferController::class, 'receive']);
        Route::post('/transfers/{transfer}/cancel', [TransferController::class, 'cancel']);

        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
        Route::post('/purchase-orders/from-alerts', [PurchaseOrderController::class, 'fromAlerts']);
        Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
        Route::get('/purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'show']);
        Route::patch('/purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'update']);
        Route::post('/purchase-orders/{purchase_order}/submit', [PurchaseOrderController::class, 'submit']);
        Route::post('/purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve']);
        Route::post('/purchase-orders/{purchase_order}/reject', [PurchaseOrderController::class, 'reject']);
        Route::post('/purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel']);

        Route::get('/reorder-alerts', [ReorderAlertController::class, 'index']);

        Route::get('/goods-receipts', [GoodsReceiptController::class, 'index']);
        Route::post('/goods-receipts', [GoodsReceiptController::class, 'store']);
        Route::get('/goods-receipts/{goods_receipt}', [GoodsReceiptController::class, 'show']);

        Route::get('/approvals', [ApprovalController::class, 'index']);

        Route::get('/supplier-bills', [SupplierBillController::class, 'index']);
        Route::get('/supplier-bills/{supplier_bill}', [SupplierBillController::class, 'show']);
        Route::post('/supplier-settlements', [SupplierSettlementController::class, 'store']);
        Route::post('/supplier-refunds', [SupplierSettlementController::class, 'refund']);

        Route::get('/supplier-returns', [SupplierReturnController::class, 'index']);
        Route::post('/supplier-returns', [SupplierReturnController::class, 'store']);
        Route::get('/supplier-returns/{supplier_return}', [SupplierReturnController::class, 'show']);
        Route::post('/supplier-returns/{supplier_return}/ship', [SupplierReturnController::class, 'ship']);
        Route::post('/supplier-returns/{supplier_return}/accept', [SupplierReturnController::class, 'accept']);
        Route::post('/supplier-returns/{supplier_return}/reject', [SupplierReturnController::class, 'reject']);

        Route::get('/cash-entries', [CashEntryController::class, 'index']);
        Route::get('/cash-balances', [CashEntryController::class, 'balances']);

        Route::get('/sales', [SaleController::class, 'index']);
        Route::post('/sales', [SaleController::class, 'store']);
        Route::get('/sales/{sale}', [SaleController::class, 'show']);
        Route::post('/sales/{sale}/void', [SaleController::class, 'void']);

        Route::get('/customer-returns', [CustomerReturnController::class, 'index']);
        Route::post('/customer-returns', [CustomerReturnController::class, 'store']);
        Route::get('/customer-returns/{customer_return}', [CustomerReturnController::class, 'show']);
        Route::post('/customer-returns/{customer_return}/approve', [CustomerReturnController::class, 'approve']);
        Route::post('/customer-returns/{customer_return}/reject', [CustomerReturnController::class, 'reject']);
        Route::post('/customer-returns/{customer_return}/refund', [CustomerReturnController::class, 'refund']);

        Route::get('/adjustments', [AdjustmentController::class, 'index']);
        Route::post('/adjustments', [AdjustmentController::class, 'store']);
        Route::post('/adjustments/{adjustment}/approve', [AdjustmentController::class, 'approve']);
        Route::post('/adjustments/{adjustment}/reject', [AdjustmentController::class, 'reject']);

        Route::get('/dashboard', [DashboardController::class, 'show']);
        Route::get('/reports/stock', [ReportController::class, 'stock']);
        Route::get('/reports/movements', [ReportController::class, 'movements']);
        Route::get('/reports/sales', [ReportController::class, 'sales']);
        Route::get('/reports/demand', [ReportController::class, 'demand']);
        Route::get('/reports/roi', [ReportController::class, 'roi']);
        Route::get('/reports/supplier-spend', [ReportController::class, 'supplierSpend']);
        Route::get('/reports/purchase-orders', [ReportController::class, 'purchaseOrders']);
        Route::get('/reports/{report}/export', [ReportController::class, 'export']);
        Route::get('/audit-logs', [AuditLogController::class, 'index']);

        Route::patch('/images/{image}', [ImageController::class, 'update']);
        Route::delete('/images/{image}', [ImageController::class, 'destroy']);
    });
});
