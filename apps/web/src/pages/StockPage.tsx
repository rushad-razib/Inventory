import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { listStockBalances, listWarehouses } from "../lib/api";
import { useAuth } from "../lib/auth";
import styles from "./catalog.module.css";

export function StockPage() {
  const { user } = useAuth();
  const [warehouseId, setWarehouseId] = useState(user?.warehouse_id ?? "");

  const warehousesQuery = useQuery({
    queryKey: ["warehouses"],
    queryFn: listWarehouses,
  });

  const balancesQuery = useQuery({
    queryKey: ["stock-balances", warehouseId],
    queryFn: () => listStockBalances(warehouseId),
    enabled: warehouseId !== "",
  });

  const warehouses = warehousesQuery.data?.data ?? [];

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Stock</h1>
          <p className={styles.subtitle}>On hand, damaged, and in transit for one warehouse.</p>
        </div>
      </header>

      <div className={styles.toolbar}>
        <label className={styles.label}>
          Warehouse
          <select
            className={styles.select}
            value={warehouseId}
            onChange={(event) => setWarehouseId(event.target.value)}
          >
            <option value="">Choose a warehouse</option>
            {warehouses.map((warehouse) => (
              <option key={warehouse.id} value={warehouse.id}>
                {warehouse.code} · {warehouse.name}
              </option>
            ))}
          </select>
        </label>
      </div>

      {warehouseId === "" ? <p className={styles.muted}>Choose a warehouse to see stock.</p> : null}
      {balancesQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
      {balancesQuery.isError ? <p className={styles.error}>{(balancesQuery.error as Error).message}</p> : null}
      {balancesQuery.data && balancesQuery.data.data.length === 0 ? (
        <p className={styles.muted}>No stock in this warehouse.</p>
      ) : null}
      {balancesQuery.data && balancesQuery.data.data.length > 0 ? (
        <div className={styles.tableWrap}>
          <table className={styles.table}>
            <thead>
              <tr>
                <th>Product</th>
                <th className={styles.money}>On hand</th>
                <th className={styles.money}>Damaged</th>
                <th className={styles.money}>In transit</th>
              </tr>
            </thead>
            <tbody>
              {balancesQuery.data.data.map((balance) => (
                <tr key={balance.id}>
                  <td>
                    <span className="mono">{balance.product.sku}</span> {balance.product.name}
                  </td>
                  <td className={`mono ${styles.money}`}>{balance.on_hand}</td>
                  <td className={`mono ${styles.money}`}>{balance.damaged}</td>
                  <td className={`mono ${styles.money}`}>{balance.in_transit}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}
