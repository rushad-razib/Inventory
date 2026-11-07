<?php

namespace App\Services;

use App\Models\Adjustment;
use App\Models\AuditLog;
use App\Models\CashEntry;
use App\Models\Category;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\CustomerPointEntry;
use App\Models\CustomerReturn;
use App\Models\Department;
use App\Models\DrawerSession;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\StockBalance;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierCredit;
use App\Models\SupplierProduct;
use App\Models\SupplierRefundReceivable;
use App\Models\SupplierReturn;
use App\Models\SupplierSettlement;
use App\Models\Transfer;
use App\Models\TransferLine;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

class DeletionGuard
{
    public function supplier(Supplier $supplier): ?string
    {
        if (
            $this->has(Product::class, 'preferred_supplier_id', $supplier->id)
            || $this->has(SupplierProduct::class, 'supplier_id', $supplier->id)
        ) {
            return 'Cannot delete this supplier. Please edit/delete associated products before deleting this supplier.';
        }

        if (
            $this->has(PurchaseOrder::class, 'supplier_id', $supplier->id)
            || $this->has(GoodsReceipt::class, 'supplier_id', $supplier->id)
            || $this->has(SupplierBill::class, 'supplier_id', $supplier->id)
            || $this->has(SupplierSettlement::class, 'supplier_id', $supplier->id)
            || $this->has(SupplierCredit::class, 'supplier_id', $supplier->id)
            || $this->has(SupplierRefundReceivable::class, 'supplier_id', $supplier->id)
            || SupplierReturn::query()->whereHas(
                'lot',
                fn ($query) => $query->where('supplier_id', $supplier->id),
            )->exists()
        ) {
            return 'Cannot delete this supplier. This supplier has purchase or payment history.';
        }

        return null;
    }

    public function category(Category $category): ?string
    {
        if ($this->has(Product::class, 'category_id', $category->id)) {
            return 'Cannot delete this category. Please edit/delete associated products before deleting this category.';
        }

        if ($this->has(Category::class, 'parent_id', $category->id)) {
            return 'Cannot delete this category. Please edit/delete child categories before deleting this category.';
        }

        return null;
    }

    public function warehouse(Warehouse $warehouse): ?string
    {
        if (
            $this->has(StockBalance::class, 'warehouse_id', $warehouse->id)
            || $this->has(StockLot::class, 'warehouse_id', $warehouse->id)
            || $this->has(StockMovement::class, 'warehouse_id', $warehouse->id)
        ) {
            return 'Cannot delete this warehouse. There are products in this warehouse.';
        }

        if ($this->has(User::class, 'warehouse_id', $warehouse->id)) {
            return 'Cannot delete this warehouse. Please reassign associated users before deleting this warehouse.';
        }

        if (
            Transfer::query()
                ->where('source_warehouse_id', $warehouse->id)
                ->orWhere('destination_warehouse_id', $warehouse->id)
                ->exists()
            || $this->has(Sale::class, 'warehouse_id', $warehouse->id)
            || $this->has(GoodsReceipt::class, 'warehouse_id', $warehouse->id)
            || $this->has(PurchaseOrder::class, 'destination_warehouse_id', $warehouse->id)
            || $this->has(Adjustment::class, 'warehouse_id', $warehouse->id)
            || $this->has(DrawerSession::class, 'warehouse_id', $warehouse->id)
        ) {
            return 'Cannot delete this warehouse. This warehouse has transfers, sales, or other records.';
        }

        if ($warehouse->is_default) {
            return 'Cannot delete this warehouse. Set another warehouse as the default first.';
        }

        return null;
    }

    public function product(Product $product): ?string
    {
        if (
            $this->has(StockBalance::class, 'product_id', $product->id)
            || $this->has(StockLot::class, 'product_id', $product->id)
            || $this->has(StockMovement::class, 'product_id', $product->id)
            || $this->has(SaleLine::class, 'product_id', $product->id)
            || $this->has(PurchaseOrderLine::class, 'product_id', $product->id)
            || $this->has(GoodsReceiptLine::class, 'product_id', $product->id)
            || $this->has(TransferLine::class, 'product_id', $product->id)
            || $this->has(Adjustment::class, 'product_id', $product->id)
        ) {
            return 'Cannot delete this product. It has stock or transaction history.';
        }

        return null;
    }

    public function department(Department $department): ?string
    {
        if ($this->has(User::class, 'department_id', $department->id)) {
            return 'Cannot delete this department. Please reassign associated users before deleting this department.';
        }

        return null;
    }

    public function customer(Customer $customer): ?string
    {
        if (
            $this->has(Sale::class, 'customer_id', $customer->id)
            || $this->has(CustomerPointEntry::class, 'customer_id', $customer->id)
        ) {
            return 'Cannot delete this customer. This customer has sales or point history.';
        }

        return null;
    }

    public function user(User $user, User $actor): ?string
    {
        if ($actor->is($user)) {
            return 'Cannot delete your own user.';
        }

        if ($user->hasRole('Admin') && User::role('Admin')->count() < 2) {
            return 'Cannot delete the last admin.';
        }

        $id = $user->id;

        if (
            $this->has(Sale::class, 'cashier_id', $id)
            || $this->has(CustomerReturn::class, 'requester_id', $id)
            || $this->has(PurchaseOrder::class, 'requester_id', $id)
            || $this->has(PurchaseOrder::class, 'approver_id', $id)
            || $this->has(GoodsReceipt::class, 'actor_id', $id)
            || $this->has(Transfer::class, 'created_by', $id)
            || $this->has(Adjustment::class, 'requester_id', $id)
            || $this->has(Adjustment::class, 'approver_id', $id)
            || $this->has(StockMovement::class, 'actor_id', $id)
            || $this->has(CashEntry::class, 'actor_id', $id)
            || $this->has(DrawerSession::class, 'cashier_id', $id)
            || $this->has(DrawerSession::class, 'closed_by', $id)
            || $this->has(SupplierSettlement::class, 'actor_id', $id)
            || $this->has(CreditNote::class, 'actor_id', $id)
            || $this->has(AuditLog::class, 'actor_id', $id)
        ) {
            return 'Cannot delete this user. This user has associated records.';
        }

        return null;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function has(string $model, string $column, string $id): bool
    {
        return $model::query()->where($column, $id)->exists();
    }
}
