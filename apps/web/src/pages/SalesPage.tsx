import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { listCustomerReturns, listSales, voidSale } from "../lib/api";
import { useAuth } from "../lib/auth";
import { prettyLabel } from "../lib/labels";
import { formatTaka } from "../lib/money";
import { can } from "../lib/permissions";
import { EmptyState } from "./EmptyState";
import styles from "./catalog.module.css";
import { TableToolbar, useTableFilters } from "./TableToolbar";

export function SalesPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const filters = useTableFilters();
  const salesQuery = useQuery({
    queryKey: ["sales", filters.q, filters.on, filters.page],
    queryFn: () => listSales(filters.table),
  });
  const returnsQuery = useQuery({
    queryKey: ["customer-returns", selectedId],
    queryFn: () => listCustomerReturns(selectedId ?? undefined),
    enabled: selectedId !== null && can(user, "sales.void"),
  });
  const sales = salesQuery.data?.data ?? [];
  const selected = sales.find((sale) => sale.id === selectedId) ?? null;
  const hasReturn = (returnsQuery.data?.data.length ?? 0) > 0;

  const voidMutation = useMutation({
    mutationFn: () => {
      if (!selected) {
        throw new Error("Select a sale.");
      }
      return voidSale(selected.id);
    },
    onSuccess: async () => {
      setError(null);
      await queryClient.invalidateQueries({ queryKey: ["sales"] });
      await queryClient.invalidateQueries({ queryKey: ["stock-balances"] });
    },
    onError: (err: Error) => setError(err.message),
  });

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Sales</h1>
          <p className={styles.subtitle}>Posted sales. A void is refused once any return exists.</p>
        </div>
      </header>
      <TableToolbar
        q={filters.q}
        onQ={filters.setQ}
        date={filters.on}
        onDate={filters.setOn}
        page={filters.page}
        lastPage={salesQuery.data?.meta.last_page ?? 1}
        onPage={filters.setPage}
      />
      {salesQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
      {salesQuery.isError ? <p className={styles.error}>{(salesQuery.error as Error).message}</p> : null}
      {salesQuery.data && sales.length === 0 ? <EmptyState>No sales yet.</EmptyState> : null}
      {sales.length > 0 ? (
        <div className={styles.tableWrap}>
          <table className={styles.table}>
            <thead>
              <tr>
                <th>Number</th>
                <th>Warehouse</th>
                <th>Status</th>
                <th className={styles.money}>Total</th>
              </tr>
            </thead>
            <tbody>
              {sales.map((sale) => (
                <tr
                  key={sale.id}
                  className={[styles.clickable, selectedId === sale.id ? styles.selected : ""].filter(Boolean).join(" ")}
                  onClick={() => {
                    setSelectedId(sale.id);
                    setError(null);
                  }}
                >
                  <td className="mono">{sale.number}</td>
                  <td>{sale.warehouse.code}</td>
                  <td>{prettyLabel(sale.status)}</td>
                  <td className={`mono ${styles.money}`}>{formatTaka(sale.total)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
      {selected ? (
        <section className={styles.panel}>
          <h2 className={styles.panelTitle}>
            {selected.number} · {prettyLabel(selected.status)}
          </h2>
          {selected.lines.map((line) => (
            <p key={line.id}>
              {line.product.name} <span className="mono">{line.product.sku}</span> · {line.quantity} ·{" "}
              <span className="mono">{formatTaka(line.unit_price)}</span>
            </p>
          ))}
          {selected.tenders.map((tender) => (
            <p key={tender.id} className={styles.muted}>
              {prettyLabel(tender.type)} <span className="mono">{tender.amount}</span>
            </p>
          ))}
          {error ? <p className={styles.error}>{error}</p> : null}
          {can(user, "sales.void") && selected.status === "completed" && !hasReturn ? (
            <button className={styles.buttonSecondary} type="button" disabled={voidMutation.isPending} onClick={() => voidMutation.mutate()}>
              Void
            </button>
          ) : null}
        </section>
      ) : null}
    </div>
  );
}
