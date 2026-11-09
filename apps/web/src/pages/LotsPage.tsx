import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { listStockLots, listWarehouses } from "../lib/api";
import { prettyLabel } from "../lib/labels";
import { formatTaka } from "../lib/money";
import { useAuth } from "../lib/auth";
import { EmptyState } from "./EmptyState";
import styles from "./catalog.module.css";
import { TableToolbar, useTableFilters } from "./TableToolbar";

export function LotsPage() {
  const { user } = useAuth();
  const [warehouseId, setWarehouseId] = useState(user?.warehouse_id ?? "");
  const filters = useTableFilters();

  const warehousesQuery = useQuery({
    queryKey: ["warehouses"],
    queryFn: () => listWarehouses(),
  });

  const lotsQuery = useQuery({
    queryKey: ["stock-lots", warehouseId, filters.q, filters.on, filters.page],
    queryFn: () => listStockLots(warehouseId || undefined, filters.table),
  });

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Lots</h1>
          <p className={styles.subtitle}>Received slices of stock, oldest owned first when consumed.</p>
        </div>
      </header>

      <div className={styles.toolbar}>
        <label className={styles.label}>
          Warehouse
          <select
            className={styles.select}
            value={warehouseId}
            onChange={(event) => {
              setWarehouseId(event.target.value);
              filters.setPage(1);
            }}
          >
            <option value="">All warehouses</option>
            {(warehousesQuery.data?.data ?? []).map((warehouse) => (
              <option key={warehouse.id} value={warehouse.id}>
                {warehouse.code} · {warehouse.name}
              </option>
            ))}
          </select>
        </label>
      </div>

      <TableToolbar
        q={filters.q}
        onQ={filters.setQ}
        date={filters.on}
        onDate={filters.setOn}
        page={filters.page}
        lastPage={lotsQuery.data?.meta.last_page ?? 1}
        onPage={filters.setPage}
      />
      {lotsQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
      {lotsQuery.isError ? <p className={styles.error}>{(lotsQuery.error as Error).message}</p> : null}
      {lotsQuery.data && lotsQuery.data.data.length === 0 ? <EmptyState>No lots yet.</EmptyState> : null}
      {lotsQuery.data && lotsQuery.data.data.length > 0 ? (
        <div className={styles.tableWrap}>
          <table className={styles.table}>
            <thead>
              <tr>
                <th>Warehouse</th>
                <th>Product</th>
                <th>Supplier</th>
                <th>Ownership</th>
                <th className={styles.money}>Unit cost</th>
                <th className={styles.money}>Received</th>
                <th className={styles.money}>On hand</th>
                <th className={styles.money}>Damaged</th>
              </tr>
            </thead>
            <tbody>
              {lotsQuery.data.data.map((lot) => (
                <tr key={lot.id}>
                  <td>{lot.warehouse.code}</td>
                  <td>
                    <span className="mono">{lot.product.sku}</span> {lot.product.name}
                  </td>
                  <td>{lot.supplier?.name ?? "—"}</td>
                  <td>{prettyLabel(lot.ownership)}</td>
                  <td className={`mono ${styles.money}`}>{formatTaka(lot.unit_cost)}</td>
                  <td className={`mono ${styles.money}`}>{lot.qty_received}</td>
                  <td className={`mono ${styles.money}`}>{lot.qty_on_hand}</td>
                  <td className={`mono ${styles.money}`}>{lot.qty_damaged}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}
