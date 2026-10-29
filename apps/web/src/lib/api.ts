import createClient from "openapi-fetch";
import type { paths } from "./api-types";

/** Empty string uses the Vite proxy in development (same-origin cookies). */
const API_BASE = import.meta.env.VITE_API_URL ?? "";

export type AuthDepartment = {
  id: string;
  name: string;
};

export type AuthUser = {
  id: string;
  name: string;
  email: string;
  roles: string[];
  permissions: string[];
  department: AuthDepartment | null;
  warehouse_id: string | null;
};

export type PaginatedMeta = {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
};

export type Product = {
  id: string;
  sku: string;
  barcode: string | null;
  name: string;
  description: string | null;
  category_id: string | null;
  unit_of_measure: string;
  sell_price: number;
  reorder_point: number;
  reorder_quantity: number;
  preferred_supplier_id: string | null;
  track_stock: boolean;
  is_active: boolean;
  created_at: string | null;
  updated_at: string | null;
};

export type Category = {
  id: string;
  name: string;
  parent_id: string | null;
  created_at: string | null;
  updated_at: string | null;
};

export type Warehouse = {
  id: string;
  code: string;
  name: string;
  address: string | null;
  is_default: boolean;
  is_active: boolean;
  created_at: string | null;
  updated_at: string | null;
};

export type Supplier = {
  id: string;
  code: string;
  name: string;
  contact: string | null;
  email: string | null;
  phone: string | null;
  address: string | null;
  supply_mode: string;
  payment_terms_days: number;
  lead_time_days: number;
  status: string;
  notes: string | null;
  created_at: string | null;
  updated_at: string | null;
};

export type SupplierProduct = {
  id: string;
  supplier_id: string;
  product_id: string;
  supplier_sku: string | null;
  agreed_unit_cost: number;
  created_at: string | null;
  updated_at: string | null;
};

export type Customer = {
  id: string;
  mobile: string;
  name: string | null;
  points_balance: number;
  created_at: string | null;
  updated_at: string | null;
};

export type Department = {
  id: string;
  name: string;
  created_at: string | null;
  updated_at: string | null;
};

export type CatalogUser = {
  id: string;
  name: string;
  email: string;
  roles: string[];
  permissions: string[];
  department: AuthDepartment | null;
  warehouse_id: string | null;
  is_active: boolean;
};

export type CompanySettings = {
  id: string;
  name: string;
  currency: string;
  po_approval_threshold: number;
  adjustment_approval_threshold: number;
  points_earn_per: number;
  point_redeem_value: number;
  updated_at: string | null;
};

export type ImageAsset = {
  id: string;
  url: string;
  mime_type: string;
  byte_size: number;
  sort_order: number;
  created_at: string | null;
};

export const apiClient = createClient<paths>({
  baseUrl: `${API_BASE}/api`,
  credentials: "include",
});

apiClient.use({
  onRequest({ request }) {
    request.headers.set("Accept", "application/json");
    request.headers.set("X-Requested-With", "XMLHttpRequest");

    const token = xsrfToken();
    if (token) {
      request.headers.set("X-XSRF-TOKEN", token);
    }

    return request;
  },
});

async function ensureCsrfCookie(): Promise<void> {
  await fetch(`${API_BASE}/sanctum/csrf-cookie`, {
    method: "GET",
    credentials: "include",
  });
}

function xsrfToken(): string | null {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : null;
}

function toAuthUser(user: {
  id: string;
  name: string;
  email: string;
  roles: string[];
  permissions: readonly string[];
  department: AuthDepartment | null;
  warehouse_id: string | null;
}): AuthUser {
  return {
    id: user.id,
    name: user.name,
    email: user.email,
    roles: [...user.roles],
    permissions: [...user.permissions],
    department: user.department,
    warehouse_id: user.warehouse_id,
  };
}

export function apiErrorMessage(error: unknown, status: number): string {
  if (error && typeof error === "object") {
    const record = error as {
      detail?: string;
      title?: string;
      message?: string;
      errors?: Record<string, string[]>;
    };
    if (record.detail) {
      return record.detail;
    }
    if (record.message) {
      return record.message;
    }
    if (record.errors) {
      const first = Object.values(record.errors).flat()[0];
      if (first) {
        return first;
      }
    }
    if (record.title) {
      return record.title;
    }
  }

  return `Request failed (${status})`;
}

function assertData<T>(
  data: T | undefined,
  error: unknown,
  status: number,
): T {
  if (error || data === undefined) {
    throw new Error(apiErrorMessage(error, status));
  }
  return data;
}

export async function login(email: string, password: string): Promise<AuthUser> {
  await ensureCsrfCookie();

  const { data, error, response } = await apiClient.POST("/v1/auth/login", {
    body: { email, password },
  });

  const payload = assertData(data, error, response.status);
  return toAuthUser(payload.data as unknown as AuthUser);
}

export async function logout(): Promise<void> {
  const { error, response } = await apiClient.POST("/v1/auth/logout");

  if (error) {
    throw new Error(apiErrorMessage(error, response.status));
  }
}

export async function fetchMe(): Promise<AuthUser> {
  const { data, error, response } = await apiClient.GET("/v1/auth/me");
  const payload = assertData(data, error, response.status);
  return toAuthUser(payload.data as unknown as AuthUser);
}

export async function listProducts(): Promise<{ data: Product[]; meta: PaginatedMeta }> {
  const { data, error, response } = await apiClient.GET("/v1/products");
  const payload = assertData(data, error, response.status);
  return {
    data: payload.data as unknown as Product[],
    meta: payload.meta,
  };
}

export type ProductInput = {
  sku: string;
  name: string;
  barcode?: string | null;
  description?: string | null;
  category_id?: string | null;
  unit_of_measure?: string;
  sell_price: number;
  reorder_point?: number;
  reorder_quantity?: number;
  preferred_supplier_id?: string | null;
  track_stock?: boolean;
  is_active?: boolean;
};

export async function createProduct(body: ProductInput): Promise<Product> {
  const { data, error, response } = await apiClient.POST("/v1/products", { body });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as Product;
}

export async function updateProduct(id: string, body: Partial<ProductInput>): Promise<Product> {
  const { data, error, response } = await apiClient.PATCH("/v1/products/{product}", {
    params: { path: { product: id } },
    body,
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as Product;
}

export async function getProduct(id: string): Promise<Product> {
  const { data, error, response } = await apiClient.GET("/v1/products/{product}", {
    params: { path: { product: id } },
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as Product;
}

export async function listProductImages(productId: string): Promise<ImageAsset[]> {
  const { data, error, response } = await apiClient.GET("/v1/products/{product}/images", {
    params: { path: { product: productId } },
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as ImageAsset[];
}

export async function uploadProductImage(productId: string, file: File): Promise<ImageAsset> {
  const { data, error, response } = await apiClient.POST("/v1/products/{product}/images", {
    params: { path: { product: productId } },
    body: { image: file as unknown as string },
    bodySerializer(body) {
      const form = new FormData();
      form.append("image", body.image as unknown as Blob);
      return form;
    },
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as ImageAsset;
}

export async function listCategories(): Promise<{ data: Category[]; meta: PaginatedMeta }> {
  const { data, error, response } = await apiClient.GET("/v1/categories");
  const payload = assertData(data, error, response.status);
  return {
    data: payload.data as unknown as Category[],
    meta: payload.meta,
  };
}

export async function createCategory(body: { name: string; parent_id?: string | null }): Promise<Category> {
  const { data, error, response } = await apiClient.POST("/v1/categories", { body });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as Category;
}

export async function updateCategory(
  id: string,
  body: { name?: string; parent_id?: string | null },
): Promise<Category> {
  const { data, error, response } = await apiClient.PATCH("/v1/categories/{category}", {
    params: { path: { category: id } },
    body,
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as Category;
}

export async function listWarehouses(): Promise<{ data: Warehouse[]; meta: PaginatedMeta }> {
  const { data, error, response } = await apiClient.GET("/v1/warehouses");
  const payload = assertData(data, error, response.status);
  return {
    data: payload.data as unknown as Warehouse[],
    meta: payload.meta,
  };
}

export type WarehouseInput = {
  code: string;
  name: string;
  address?: string | null;
  is_default?: boolean;
  is_active?: boolean;
};

export async function createWarehouse(body: WarehouseInput): Promise<Warehouse> {
  const { data, error, response } = await apiClient.POST("/v1/warehouses", { body });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as Warehouse;
}

export async function updateWarehouse(id: string, body: Partial<WarehouseInput>): Promise<Warehouse> {
  const { data, error, response } = await apiClient.PATCH("/v1/warehouses/{warehouse}", {
    params: { path: { warehouse: id } },
    body,
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as Warehouse;
}

export async function listSuppliers(): Promise<{ data: Supplier[]; meta: PaginatedMeta }> {
  const { data, error, response } = await apiClient.GET("/v1/suppliers");
  const payload = assertData(data, error, response.status);
  return {
    data: payload.data as unknown as Supplier[],
    meta: payload.meta,
  };
}

export type SupplierInput = {
  code: string;
  name: string;
  supply_mode: "outright" | "consignment";
  status: "active" | "inactive";
  contact?: string | null;
  email?: string | null;
  phone?: string | null;
  address?: string | null;
  payment_terms_days?: number;
  lead_time_days?: number;
  notes?: string | null;
};

export async function createSupplier(body: SupplierInput): Promise<Supplier> {
  const { data, error, response } = await apiClient.POST("/v1/suppliers", { body });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as Supplier;
}

export async function updateSupplier(id: string, body: Partial<SupplierInput>): Promise<Supplier> {
  const { data, error, response } = await apiClient.PATCH("/v1/suppliers/{supplier}", {
    params: { path: { supplier: id } },
    body,
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as Supplier;
}

export async function listSupplierProducts(
  supplierId: string,
): Promise<{ data: SupplierProduct[]; meta: PaginatedMeta }> {
  const { data, error, response } = await apiClient.GET("/v1/suppliers/{supplier}/products", {
    params: { path: { supplier: supplierId } },
  });
  const payload = assertData(data, error, response.status);
  return {
    data: payload.data as unknown as SupplierProduct[],
    meta: payload.meta,
  };
}

export async function createSupplierProduct(
  supplierId: string,
  body: { product_id: string; agreed_unit_cost: number; supplier_sku?: string | null },
): Promise<SupplierProduct> {
  const { data, error, response } = await apiClient.POST("/v1/suppliers/{supplier}/products", {
    params: { path: { supplier: supplierId } },
    body,
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as SupplierProduct;
}

export async function listCustomers(): Promise<{ data: Customer[]; meta: PaginatedMeta }> {
  const { data, error, response } = await apiClient.GET("/v1/customers");
  const payload = assertData(data, error, response.status);
  return {
    data: payload.data as unknown as Customer[],
    meta: payload.meta,
  };
}

export async function createCustomer(body: {
  mobile: string;
  name?: string | null;
}): Promise<Customer> {
  const { data, error, response } = await apiClient.POST("/v1/customers", { body });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as Customer;
}

export async function lookupCustomer(mobile: string): Promise<Customer | null> {
  const { data, error, response } = await apiClient.GET("/v1/customers/lookup", {
    params: { query: { mobile } },
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as Customer | null;
}

export async function listUsers(): Promise<{ data: CatalogUser[]; meta: PaginatedMeta }> {
  const { data, error, response } = await apiClient.GET("/v1/users");
  const payload = assertData(data, error, response.status);
  return {
    data: payload.data as unknown as CatalogUser[],
    meta: payload.meta,
  };
}

export type UserInput = {
  name: string;
  email: string;
  password?: string;
  role: string;
  department_id?: string | null;
  warehouse_id?: string | null;
  is_active?: boolean;
};

export async function createUser(body: UserInput & { password: string }): Promise<CatalogUser> {
  const { data, error, response } = await apiClient.POST("/v1/users", { body });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as CatalogUser;
}

export async function updateUser(id: string, body: Partial<UserInput>): Promise<CatalogUser> {
  const { data, error, response } = await apiClient.PATCH("/v1/users/{user}", {
    params: { path: { user: id } },
    body,
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as CatalogUser;
}

export async function listUserImages(userId: string): Promise<ImageAsset[]> {
  const { data, error, response } = await apiClient.GET("/v1/users/{user}/images", {
    params: { path: { user: userId } },
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as ImageAsset[];
}

export async function uploadUserImage(userId: string, file: File): Promise<ImageAsset> {
  const { data, error, response } = await apiClient.POST("/v1/users/{user}/images", {
    params: { path: { user: userId } },
    body: { image: file as unknown as string },
    bodySerializer(body) {
      const form = new FormData();
      form.append("image", body.image as unknown as Blob);
      return form;
    },
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as ImageAsset;
}

export async function listDepartments(): Promise<{ data: Department[]; meta: PaginatedMeta }> {
  const { data, error, response } = await apiClient.GET("/v1/departments");
  const payload = assertData(data, error, response.status);
  return {
    data: payload.data as unknown as Department[],
    meta: payload.meta,
  };
}

export async function createDepartment(body: { name: string }): Promise<Department> {
  const { data, error, response } = await apiClient.POST("/v1/departments", { body });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as Department;
}

export async function fetchSettings(): Promise<CompanySettings> {
  const { data, error, response } = await apiClient.GET("/v1/settings");
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as CompanySettings;
}

export async function updateSettings(body: {
  name?: string;
  po_approval_threshold?: number;
  adjustment_approval_threshold?: number;
  points_earn_per?: number;
  point_redeem_value?: number;
}): Promise<CompanySettings> {
  const { data, error, response } = await apiClient.PATCH("/v1/settings", { body });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as CompanySettings;
}

type NamedRef = {
  id: string;
  code: string;
  name: string;
};

export type StockBalance = {
  id: string;
  warehouse_id: string;
  product_id: string;
  on_hand: number;
  damaged: number;
  in_transit: number;
  warehouse: NamedRef;
  product: { id: string; sku: string; name: string };
};

export type StockLot = {
  id: string;
  goods_receipt_id: string | null;
  supplier_id: string | null;
  product_id: string;
  warehouse_id: string;
  unit_cost: number;
  ownership: string;
  qty_received: number;
  qty_on_hand: number;
  qty_damaged: number;
  received_at: string | null;
  warehouse: NamedRef;
  product: { id: string; sku: string; name: string };
  supplier: NamedRef | null;
};

export type StockMovement = {
  id: string;
  product_id: string;
  warehouse_id: string;
  stock_lot_id: string;
  type: string;
  quantity: number;
  unit_cost: number;
  notes: string | null;
  occurred_at: string | null;
  warehouse: NamedRef;
  product: { id: string; sku: string; name: string };
};

export type TransferLine = {
  id: string;
  product_id: string;
  quantity: number;
  product: { id: string; sku: string; name: string };
  lots: {
    id: string;
    source_lot_id: string;
    destination_lot_id: string | null;
    supplier_id: string | null;
    ownership: string;
    unit_cost: number;
    quantity: number;
  }[];
};

export type Transfer = {
  id: string;
  source_warehouse_id: string;
  destination_warehouse_id: string;
  status: string;
  created_by: string;
  created_at: string | null;
  updated_at: string | null;
  source_warehouse: NamedRef;
  destination_warehouse: NamedRef;
  lines: TransferLine[];
};

export type Adjustment = {
  id: string;
  warehouse_id: string;
  product_id: string;
  quantity: number;
  reason: string;
  status: string;
  requester_id: string;
  approver_id: string | null;
  posted_at: string | null;
  created_at: string | null;
  warehouse: NamedRef;
  product: { id: string; sku: string; name: string };
  requester: { id: string; name: string };
};

function idempotencyHeaders(): { "Idempotency-Key": string } {
  return { "Idempotency-Key": crypto.randomUUID() };
}

export async function listStockBalances(
  warehouseId: string,
): Promise<{ data: StockBalance[]; meta: PaginatedMeta }> {
  const { data, error, response } = await apiClient.GET("/v1/stock-balances", {
    params: { query: { "filter[warehouse_id]": warehouseId } },
  });
  const payload = assertData(data, error, response.status);
  return {
    data: payload.data as unknown as StockBalance[],
    meta: payload.meta,
  };
}

export async function listStockLots(
  warehouseId?: string,
): Promise<{ data: StockLot[]; meta: PaginatedMeta }> {
  const { data, error, response } = await apiClient.GET("/v1/stock-lots", {
    params: {
      query: warehouseId ? { "filter[warehouse_id]": warehouseId } : {},
    },
  });
  const payload = assertData(data, error, response.status);
  return {
    data: payload.data as unknown as StockLot[],
    meta: payload.meta,
  };
}

export async function listStockMovements(
  warehouseId?: string,
): Promise<{ data: StockMovement[]; meta: PaginatedMeta }> {
  const { data, error, response } = await apiClient.GET("/v1/stock-movements", {
    params: {
      query: warehouseId ? { "filter[warehouse_id]": warehouseId } : {},
    },
  });
  const payload = assertData(data, error, response.status);
  return {
    data: payload.data as unknown as StockMovement[],
    meta: payload.meta,
  };
}

export async function listTransfers(): Promise<{ data: Transfer[]; meta: PaginatedMeta }> {
  const { data, error, response } = await apiClient.GET("/v1/transfers");
  const payload = assertData(data, error, response.status);
  return {
    data: payload.data as unknown as Transfer[],
    meta: payload.meta,
  };
}

export async function createTransfer(body: {
  source_warehouse_id: string;
  destination_warehouse_id: string;
  lines: { product_id: string; quantity: number }[];
}): Promise<Transfer> {
  const { data, error, response } = await apiClient.POST("/v1/transfers", { body });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as Transfer;
}

async function postTransferAction(
  path: "/v1/transfers/{transfer}/ship" | "/v1/transfers/{transfer}/receive" | "/v1/transfers/{transfer}/cancel",
  id: string,
): Promise<Transfer> {
  const { data, error, response } = await apiClient.POST(path, {
    params: { path: { transfer: id } },
    headers: idempotencyHeaders(),
  });
  const payload = assertData(data, error, response.status);
  return (payload as unknown as { data: Transfer }).data;
}

export function shipTransfer(id: string): Promise<Transfer> {
  return postTransferAction("/v1/transfers/{transfer}/ship", id);
}

export function receiveTransfer(id: string): Promise<Transfer> {
  return postTransferAction("/v1/transfers/{transfer}/receive", id);
}

export function cancelTransfer(id: string): Promise<Transfer> {
  return postTransferAction("/v1/transfers/{transfer}/cancel", id);
}

export async function listAdjustments(
  warehouseId?: string,
): Promise<{ data: Adjustment[]; meta: PaginatedMeta }> {
  const { data, error, response } = await apiClient.GET("/v1/adjustments", {
    params: {
      query: warehouseId ? { "filter[warehouse_id]": warehouseId } : {},
    },
  });
  const payload = assertData(data, error, response.status);
  return {
    data: payload.data as unknown as Adjustment[],
    meta: payload.meta,
  };
}

export async function createAdjustment(body: {
  warehouse_id: string;
  product_id: string;
  quantity: number;
  reason: "damage" | "count" | "correction";
}): Promise<Adjustment> {
  const { data, error, response } = await apiClient.POST("/v1/adjustments", {
    body,
    headers: idempotencyHeaders(),
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as Adjustment;
}

export async function approveAdjustment(id: string): Promise<Adjustment> {
  const { data, error, response } = await apiClient.POST("/v1/adjustments/{adjustment}/approve", {
    params: { path: { adjustment: id } },
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as Adjustment;
}

export async function rejectAdjustment(id: string): Promise<Adjustment> {
  const { data, error, response } = await apiClient.POST("/v1/adjustments/{adjustment}/reject", {
    params: { path: { adjustment: id } },
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as Adjustment;
}

export type PurchaseOrderLine = {
  id: string;
  product_id: string;
  quantity_ordered: number;
  quantity_received: number;
  unit_cost: number;
  product: { id: string; sku: string; name: string };
};

export type PurchaseOrder = {
  id: string;
  number: string;
  supplier_id: string;
  destination_warehouse_id: string;
  status: string;
  requester_id: string;
  approver_id: string | null;
  expected_on: string | null;
  notes: string | null;
  subtotal: number;
  tax: number;
  total: number;
  created_at: string | null;
  supplier: { id: string; code: string; name: string; supply_mode: string; payment_terms_days: number };
  destination_warehouse: NamedRef;
  requester: { id: string; name: string };
  approver: { id: string; name: string } | null;
  lines: PurchaseOrderLine[];
};

export type PurchaseOrderInput = {
  supplier_id: string;
  destination_warehouse_id: string;
  expected_on?: string | null;
  notes?: string | null;
  lines: { product_id: string; quantity: number }[];
};

export type GoodsReceipt = {
  id: string;
  purchase_order_id: string | null;
  supplier_id: string;
  warehouse_id: string;
  created_at: string | null;
  supplier: { id: string; code: string; name: string; supply_mode: string };
  warehouse: NamedRef;
  actor: { id: string; name: string };
  purchase_order: { id: string; number: string; status: string } | null;
  lines: {
    id: string;
    product_id: string;
    quantity: number;
    unit_cost: number;
    product: { id: string; sku: string; name: string };
  }[];
  lots: {
    id: string;
    product_id: string;
    ownership: string;
    unit_cost: number;
    qty_received: number;
    qty_on_hand: number;
  }[];
  bill: {
    id: string;
    status: string;
    amount: number;
    amount_paid: number;
    due_on: string | null;
  } | null;
};

export type ReorderAlert = {
  warehouse_id: string;
  product_id: string;
  on_hand: number;
  reorder_point: number;
  reorder_quantity: number;
  preferred_supplier_id: string | null;
  warehouse: NamedRef;
  product: { id: string; sku: string; name: string };
  preferred_supplier: { id: string; code: string; name: string; supply_mode: string } | null;
};

export type SupplierBill = {
  id: string;
  supplier_id: string;
  source_type: string;
  source_id: string;
  status: string;
  amount: number;
  amount_paid: number;
  credited_amount: number;
  due_on: string | null;
  supplier: NamedRef;
};

export type SaleLine = {
  id: string;
  product_id: string;
  quantity: number;
  unit_price: number;
  shelf_total: number;
  points_redeemed: number;
  cash_paid: number;
  points_earned: number;
  quantity_returned: number;
  product: { id: string; sku: string; name: string; barcode: string | null };
  allocations: {
    id: string;
    stock_lot_id: string;
    quantity: number;
    unit_cost: number;
    ownership: string;
    payable: { id: string; status: string; amount: number; credited_amount: number; due_on: string | null } | null;
  }[];
};

export type Sale = {
  id: string;
  number: string;
  warehouse_id: string;
  cashier_id: string;
  customer_id: string | null;
  status: string;
  subtotal: number;
  points_redeemed: number;
  cash_and_card_paid: number;
  total: number;
  points_earned: number;
  points_balance_after: number | null;
  created_at: string | null;
  warehouse: NamedRef;
  cashier: { id: string; name: string };
  customer: { id: string; mobile: string; name: string | null; points_balance: number } | null;
  lines: SaleLine[];
  tenders: { id: string; type: string; amount: number }[];
};

export type CustomerReturnLine = {
  id: string;
  sale_line_id: string;
  quantity: number;
  reason: string;
  cash_amount: number;
  points_restored: number;
  points_clawed_back: number;
  product: { id: string; sku: string; name: string };
};

export type CustomerReturn = {
  id: string;
  sale_id: string;
  requester_id: string;
  approver_id: string | null;
  status: string;
  cash_amount: number;
  points_restored: number;
  points_clawed_back: number;
  created_at: string | null;
  sale: { id: string; number: string; warehouse_id: string; status: string; warehouse: NamedRef | null };
  requester: { id: string; name: string };
  approver: { id: string; name: string } | null;
  lines: CustomerReturnLine[];
  supplier_returns: { id: string; stock_lot_id: string; quantity: number; unit_cost: number; status: string }[];
};

export type Approvals = {
  purchase_orders: PurchaseOrder[];
  adjustments: Adjustment[];
  customer_returns: CustomerReturn[];
};

export async function listPurchaseOrders(): Promise<{ data: PurchaseOrder[]; meta: PaginatedMeta }> {
  const { data, error, response } = await apiClient.GET("/v1/purchase-orders");
  const payload = assertData(data, error, response.status);
  return {
    data: payload.data as unknown as PurchaseOrder[],
    meta: payload.meta,
  };
}

export async function createPurchaseOrder(body: PurchaseOrderInput): Promise<PurchaseOrder> {
  const { data, error, response } = await apiClient.POST("/v1/purchase-orders", { body });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as PurchaseOrder;
}

export async function updatePurchaseOrder(id: string, body: PurchaseOrderInput): Promise<PurchaseOrder> {
  const { data, error, response } = await apiClient.PATCH("/v1/purchase-orders/{purchaseOrder}", {
    params: { path: { purchaseOrder: id } },
    body,
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as PurchaseOrder;
}

async function postPurchaseOrderAction(
  path:
    | "/v1/purchase-orders/{purchaseOrder}/submit"
    | "/v1/purchase-orders/{purchaseOrder}/approve"
    | "/v1/purchase-orders/{purchaseOrder}/reject"
    | "/v1/purchase-orders/{purchaseOrder}/cancel",
  id: string,
): Promise<PurchaseOrder> {
  const { data, error, response } = await apiClient.POST(path, {
    params: { path: { purchaseOrder: id } },
  });
  const payload = assertData(data, error, response.status);
  return (payload as unknown as { data: PurchaseOrder }).data;
}

export function submitPurchaseOrder(id: string): Promise<PurchaseOrder> {
  return postPurchaseOrderAction("/v1/purchase-orders/{purchaseOrder}/submit", id);
}

export function approvePurchaseOrder(id: string): Promise<PurchaseOrder> {
  return postPurchaseOrderAction("/v1/purchase-orders/{purchaseOrder}/approve", id);
}

export function rejectPurchaseOrder(id: string): Promise<PurchaseOrder> {
  return postPurchaseOrderAction("/v1/purchase-orders/{purchaseOrder}/reject", id);
}

export function cancelPurchaseOrder(id: string): Promise<PurchaseOrder> {
  return postPurchaseOrderAction("/v1/purchase-orders/{purchaseOrder}/cancel", id);
}

export async function createPurchaseOrdersFromAlerts(lines: {
  warehouse_id: string;
  product_id: string;
}[]): Promise<PurchaseOrder[]> {
  const { data, error, response } = await apiClient.POST("/v1/purchase-orders/from-alerts", {
    body: { lines },
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as PurchaseOrder[];
}

export async function listReorderAlerts(): Promise<{ data: ReorderAlert[]; meta: PaginatedMeta }> {
  const { data, error, response } = await apiClient.GET("/v1/reorder-alerts");
  const payload = assertData(data, error, response.status);
  return {
    data: payload.data as unknown as ReorderAlert[],
    meta: payload.meta,
  };
}

export async function listGoodsReceipts(): Promise<{ data: GoodsReceipt[]; meta: PaginatedMeta }> {
  const { data, error, response } = await apiClient.GET("/v1/goods-receipts");
  const payload = assertData(data, error, response.status);
  return {
    data: payload.data as unknown as GoodsReceipt[],
    meta: payload.meta,
  };
}

export async function createGoodsReceipt(body: {
  purchase_order_id?: string;
  supplier_id?: string;
  warehouse_id?: string;
  lines: { product_id: string; quantity: number }[];
}): Promise<GoodsReceipt> {
  const { data, error, response } = await apiClient.POST("/v1/goods-receipts", {
    body,
    headers: idempotencyHeaders(),
  });
  const payload = assertData(data, error, response.status);
  return (payload as unknown as { data: GoodsReceipt }).data;
}

export async function listApprovals(): Promise<Approvals> {
  const { data, error, response } = await apiClient.GET("/v1/approvals");
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as Approvals;
}

export async function listSupplierBills(): Promise<{ data: SupplierBill[]; meta: PaginatedMeta }> {
  const { data, error, response } = await apiClient.GET("/v1/supplier-bills");
  const payload = assertData(data, error, response.status);
  return {
    data: payload.data as unknown as SupplierBill[],
    meta: payload.meta,
  };
}

export async function lookupProduct(barcode: string): Promise<Product | null> {
  const { data, error, response } = await apiClient.GET("/v1/products/lookup", {
    params: { query: { barcode } },
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as Product | null;
}

export async function listSales(): Promise<{ data: Sale[]; meta: PaginatedMeta }> {
  const { data, error, response } = await apiClient.GET("/v1/sales");
  const payload = assertData(data, error, response.status);
  return {
    data: payload.data as unknown as Sale[],
    meta: payload.meta,
  };
}

export async function createSale(body: {
  warehouse_id: string;
  customer_id?: string | null;
  lines: { product_id: string; quantity: number }[];
  tenders: { type: "cash" | "card" | "points"; amount: number }[];
}): Promise<Sale> {
  const { data, error, response } = await apiClient.POST("/v1/sales", {
    body,
    headers: idempotencyHeaders(),
  });
  const payload = assertData(data, error, response.status);
  return (payload as unknown as { data: Sale }).data;
}

export async function voidSale(id: string): Promise<Sale> {
  const { data, error, response } = await apiClient.POST("/v1/sales/{sale}/void", {
    params: { path: { sale: id } },
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as Sale;
}

export async function listCustomerReturns(saleId?: string): Promise<{ data: CustomerReturn[]; meta: PaginatedMeta }> {
  const { data, error, response } = await apiClient.GET("/v1/customer-returns", {
    params: { query: saleId ? { "filter[sale_id]": saleId } : {} },
  });
  const payload = assertData(data, error, response.status);
  return {
    data: payload.data as unknown as CustomerReturn[],
    meta: payload.meta,
  };
}

export async function createCustomerReturn(body: {
  sale_id: string;
  lines: { sale_line_id: string; quantity: number; reason: "resaleable" | "damaged" }[];
}): Promise<CustomerReturn> {
  const { data, error, response } = await apiClient.POST("/v1/customer-returns", { body });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as CustomerReturn;
}

export async function approveCustomerReturn(id: string): Promise<CustomerReturn> {
  const { data, error, response } = await apiClient.POST("/v1/customer-returns/{customerReturn}/approve", {
    params: { path: { customerReturn: id } },
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as CustomerReturn;
}

export async function rejectCustomerReturn(id: string): Promise<CustomerReturn> {
  const { data, error, response } = await apiClient.POST("/v1/customer-returns/{customerReturn}/reject", {
    params: { path: { customerReturn: id } },
  });
  const payload = assertData(data, error, response.status);
  return payload.data as unknown as CustomerReturn;
}

export async function refundCustomerReturn(id: string): Promise<CustomerReturn> {
  const { data, error, response } = await apiClient.POST("/v1/customer-returns/{customerReturn}/refund", {
    params: { path: { customerReturn: id } },
    headers: idempotencyHeaders(),
  });
  const payload = assertData(data, error, response.status);
  return (payload as unknown as { data: CustomerReturn }).data;
}
