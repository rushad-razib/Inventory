import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { createPurchaseOrdersFromAlerts, listReorderAlerts } from "../lib/api";
import styles from "./catalog.module.css";

function alertKey(warehouseId: string, productId: string): string {
  return `${warehouseId}|${productId}`;
}

export function ReorderAlertsPage() {
  const queryClient = useQueryClient();
  const [selected, setSelected] = useState<string[]>([]);
  const [formError, setFormError] = useState<string | null>(null);
  const alertsQuery = useQuery({ queryKey: ["reorder-alerts"], queryFn: listReorderAlerts });
  const alerts = alertsQuery.data?.data ?? [];

  const createMutation = useMutation({
    mutationFn: () =>
      createPurchaseOrdersFromAlerts(
        selected.map((key) => {
          const [warehouseId, productId] = key.split("|");
          return { warehouse_id: warehouseId ?? "", product_id: productId ?? "" };
        }),
      ),
    onSuccess: async () => {
      setFormError(null);
      setSelected([]);
      await queryClient.invalidateQueries({ queryKey: ["purchase-orders"] });
    },
    onError: (err: Error) => setFormError(err.message),
  });

  const toggle = (key: string) => {
    setSelected((current) => (current.includes(key) ? current.filter((item) => item !== key) : [...current, key]));
  };

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Reorder alerts</h1>
          <p className={styles.subtitle}>On hand at or below the reorder point. Creating drafts does not submit them.</p>
        </div>
      </header>
      {alertsQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
      {alertsQuery.isError ? <p className={styles.error}>{(alertsQuery.error as Error).message}</p> : null}
      {alertsQuery.data && alerts.length === 0 ? <p className={styles.muted}>No products need reordering.</p> : null}
      {alerts.length > 0 ? (
        <div className={styles.tableWrap}>
          <table className={styles.table}>
            <thead>
              <tr>
                <th />
                <th>Warehouse</th>
                <th>Product</th>
                <th className={styles.money}>On hand</th>
                <th className={styles.money}>Reorder point</th>
                <th>Preferred supplier</th>
              </tr>
            </thead>
            <tbody>
              {alerts.map((alert) => {
                const key = alertKey(alert.warehouse_id, alert.product_id);
                return (
                  <tr key={key}>
                    <td>
                      <input type="checkbox" checked={selected.includes(key)} onChange={() => toggle(key)} />
                    </td>
                    <td>{alert.warehouse.code}</td>
                    <td>
                      <span className="mono">{alert.product.sku}</span> {alert.product.name}
                    </td>
                    <td className={`mono ${styles.money}`}>{alert.on_hand}</td>
                    <td className={`mono ${styles.money}`}>{alert.reorder_point}</td>
                    <td>
                      {alert.preferred_supplier
                        ? `${alert.preferred_supplier.code} · ${alert.preferred_supplier.name}`
                        : "—"}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      ) : null}
      {selected.length > 0 ? (
        <div className={styles.actions}>
          <button
            className={styles.button}
            type="button"
            disabled={createMutation.isPending}
            onClick={() => createMutation.mutate()}
          >
            {createMutation.isPending ? "Creating…" : "Create drafts"}
          </button>
        </div>
      ) : null}
      {formError ? <p className={styles.error}>{formError}</p> : null}
      {createMutation.isSuccess ? (
        <p className={styles.muted}>
          Created {createMutation.data.length} draft{createMutation.data.length === 1 ? "" : "s"}.
        </p>
      ) : null}
    </div>
  );
}
