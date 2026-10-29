import { BrowserRouter, Navigate, Route, Routes } from "react-router-dom";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { AuthProvider, useAuth } from "./lib/auth";
import { AppShell } from "./layouts/AppShell";
import { LoginPage } from "./pages/LoginPage";
import { HomePage } from "./pages/HomePage";
import { ProductsPage } from "./pages/ProductsPage";
import { CategoriesPage } from "./pages/CategoriesPage";
import { WarehousesPage } from "./pages/WarehousesPage";
import { SuppliersPage } from "./pages/SuppliersPage";
import { CustomersPage } from "./pages/CustomersPage";
import { UsersPage } from "./pages/UsersPage";
import { DepartmentsPage } from "./pages/DepartmentsPage";
import { SettingsPage } from "./pages/SettingsPage";
import { StockPage } from "./pages/StockPage";
import { LotsPage } from "./pages/LotsPage";
import { MovementsPage } from "./pages/MovementsPage";
import { TransfersPage } from "./pages/TransfersPage";
import { AdjustmentsPage } from "./pages/AdjustmentsPage";
import { PurchaseOrdersPage } from "./pages/PurchaseOrdersPage";
import { GoodsReceiptsPage } from "./pages/GoodsReceiptsPage";
import { ReorderAlertsPage } from "./pages/ReorderAlertsPage";
import { ApprovalsPage } from "./pages/ApprovalsPage";
import { SalesPage } from "./pages/SalesPage";
import { PosPage } from "./pages/PosPage";
import { CustomerReturnsPage } from "./pages/CustomerReturnsPage";
import { SupplierBillsPage } from "./pages/SupplierBillsPage";

const queryClient = new QueryClient();

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { user, isLoading } = useAuth();

  if (isLoading) {
    return <div style={{ padding: "2rem" }}>Loading…</div>;
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  return children;
}

function PublicOnly({ children }: { children: React.ReactNode }) {
  const { user, isLoading } = useAuth();

  if (isLoading) {
    return <div style={{ padding: "2rem" }}>Loading…</div>;
  }

  if (user) {
    return <Navigate to="/" replace />;
  }

  return children;
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <BrowserRouter>
          <Routes>
            <Route
              path="/login"
              element={
                <PublicOnly>
                  <LoginPage />
                </PublicOnly>
              }
            />
            <Route
              path="/"
              element={
                <ProtectedRoute>
                  <AppShell />
                </ProtectedRoute>
              }
            >
              <Route index element={<HomePage />} />
              <Route path="products" element={<ProductsPage />} />
              <Route path="categories" element={<CategoriesPage />} />
              <Route path="warehouses" element={<WarehousesPage />} />
              <Route path="stock" element={<StockPage />} />
              <Route path="lots" element={<LotsPage />} />
              <Route path="movements" element={<MovementsPage />} />
              <Route path="transfers" element={<TransfersPage />} />
              <Route path="adjustments" element={<AdjustmentsPage />} />
              <Route path="purchase-orders" element={<PurchaseOrdersPage />} />
              <Route path="goods-receipts" element={<GoodsReceiptsPage />} />
              <Route path="reorder-alerts" element={<ReorderAlertsPage />} />
              <Route path="approvals" element={<ApprovalsPage />} />
              <Route path="supplier-bills" element={<SupplierBillsPage />} />
              <Route path="pos" element={<PosPage />} />
              <Route path="sales" element={<SalesPage />} />
              <Route path="customer-returns" element={<CustomerReturnsPage />} />
              <Route path="suppliers" element={<SuppliersPage />} />
              <Route path="customers" element={<CustomersPage />} />
              <Route path="users" element={<UsersPage />} />
              <Route path="departments" element={<DepartmentsPage />} />
              <Route path="settings" element={<SettingsPage />} />
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </BrowserRouter>
      </AuthProvider>
    </QueryClientProvider>
  );
}
