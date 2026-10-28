import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { listStockMovements, listWarehouses } from "../lib/api";
import { prettyLabel } from "../lib/labels";
import { useAuth } from "../lib/auth";
import styles from "./catalog.module.css";

export function MovementsPage() {
  const { user } = useAuth();
  const [warehouseId, setWarehouseId] = useState(user?.warehouse_id ?? "");

  const warehousesQuery = useQuery({
    queryKey: ["warehouses"],
    queryFn: listWarehouses,
  });

  const movementsQuery = useQuery({
    queryKey: ["stock-movements", warehouseId],
    queryFn: () => listStockMovements(warehouseId || undefined),
  });

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Movements</h1>
          <p className={styles.subtitle}>Append-only record of quantity changes.</p>
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
            <option value="">All warehouses</option>
            {(warehousesQuery.data?.data ?? []).map((warehouse) => (
              <option key={warehouse.id} value={warehouse.id}>
                {warehouse.code} · {warehouse.name}
              </option>
            ))}
          </select>
        </label>
      </div>

      {movementsQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
      {movementsQuery.isError ? (
        <p className={styles.error}>{(movementsQuery.error as Error).message}</p>
      ) : null}
      {movementsQuery.data && movementsQuery.data.data.length === 0 ? (
        <p className={styles.muted}>No movements yet.</p>
      ) : null}
      {movementsQuery.data && movementsQuery.data.data.length > 0 ? (
        <div className={styles.tableWrap}>
          <table className={styles.table}>
            <thead>
              <tr>
                <th>Type</th>
                <th>Warehouse</th>
                <th>Product</th>
                <th className={styles.money}>Quantity</th>
                <th>Time</th>
              </tr>
            </thead>
            <tbody>
              {movementsQuery.data.data.map((movement) => (
                <tr key={movement.id}>
                  <td>{prettyLabel(movement.type)}</td>
                  <td>{movement.warehouse.code}</td>
                  <td>
                    <span className="mono">{movement.product.sku}</span> {movement.product.name}
                  </td>
                  <td className={`mono ${styles.money}`}>{movement.quantity}</td>
                  <td className="mono">
                    {movement.occurred_at ? new Date(movement.occurred_at).toLocaleString() : "—"}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}
