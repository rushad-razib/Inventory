import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { createCustomerReturn, listCustomerReturns, listSales, refundCustomerReturn, type Sale } from "../lib/api";
import { useAuth } from "../lib/auth";
import { prettyLabel } from "../lib/labels";
import { formatStoredRefund, formatTaka } from "../lib/money";
import { can } from "../lib/permissions";
import styles from "./catalog.module.css";

type DraftLine = { saleLineId: string; quantity: number; reason: "resaleable" | "damaged" };

export function CustomerReturnsPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [saleId, setSaleId] = useState("");
  const [drafts, setDrafts] = useState<DraftLine[]>([]);
  const [error, setError] = useState<string | null>(null);
  const salesQuery = useQuery({ queryKey: ["sales"], queryFn: listSales });
  const returnsQuery = useQuery({ queryKey: ["customer-returns"], queryFn: () => listCustomerReturns() });
  const sales = (salesQuery.data?.data ?? []).filter((sale) => sale.status === "completed");
  const selected = sales.find((sale) => sale.id === saleId) ?? null;
  const returns = returnsQuery.data?.data ?? [];

  const requestMutation = useMutation({
    mutationFn: () =>
      createCustomerReturn({
        sale_id: saleId,
        lines: drafts
          .filter((line) => line.quantity > 0)
          .map((line) => ({
            sale_line_id: line.saleLineId,
            quantity: line.quantity,
            reason: line.reason,
          })),
      }),
    onSuccess: async () => {
      setError(null);
      setDrafts([]);
      await queryClient.invalidateQueries({ queryKey: ["customer-returns"] });
      await queryClient.invalidateQueries({ queryKey: ["approvals"] });
    },
    onError: (err: Error) => setError(err.message),
  });

  const refundMutation = useMutation({
    mutationFn: refundCustomerReturn,
    onSuccess: async () => {
      setError(null);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["customer-returns"] }),
        queryClient.invalidateQueries({ queryKey: ["sales"] }),
        queryClient.invalidateQueries({ queryKey: ["stock-balances"] }),
        queryClient.invalidateQueries({ queryKey: ["supplier-bills"] }),
      ]);
    },
    onError: (err: Error) => setError(err.message),
  });

  const chooseSale = (sale: Sale) => {
    setSaleId(sale.id);
    setDrafts(
      sale.lines
        .filter((line) => line.quantity_returned < line.quantity)
        .map((line) => ({
          saleLineId: line.id,
          quantity: 0,
          reason: "resaleable",
        })),
    );
  };

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Customer returns</h1>
          <p className={styles.subtitle}>Request selected units. A manager approves. The till then pays the stored cash.</p>
        </div>
      </header>
      {returnsQuery.data && returns.length === 0 ? <p className={styles.muted}>No customer returns yet.</p> : null}
      {returns.length > 0 ? (
        <div className={styles.tableWrap}>
          <table className={styles.table}>
            <thead>
              <tr>
                <th>Sale</th>
                <th>Status</th>
                <th>Refund</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {returns.map((item) => (
                <tr key={item.id}>
                  <td className="mono">{item.sale.number}</td>
                  <td>{prettyLabel(item.status)}</td>
                  <td className="mono">{formatStoredRefund(item.cash_amount, item.points_restored)}</td>
                  <td>
                    {can(user, "sales.return") && item.status === "approved" ? (
                      <button
                        className={styles.button}
                        type="button"
                        disabled={refundMutation.isPending}
                        onClick={() => refundMutation.mutate(item.id)}
                      >
                        Record payout
                      </button>
                    ) : null}
                    {item.supplier_returns.length > 0 ? (
                      <span className={styles.muted}> Supplier return opened.</span>
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}

      {can(user, "sales.return") ? (
        <section className={styles.panel}>
          <h2 className={styles.panelTitle}>Request a return</h2>
          <label className={styles.label}>
            Sale
            <select
              className={styles.select}
              value={saleId}
              onChange={(event) => {
                const sale = sales.find((item) => item.id === event.target.value);
                if (sale) {
                  chooseSale(sale);
                } else {
                  setSaleId("");
                  setDrafts([]);
                }
              }}
            >
              <option value="">Choose a completed sale</option>
              {sales.map((sale) => (
                <option key={sale.id} value={sale.id}>
                  {sale.number} · {formatTaka(sale.total)}
                </option>
              ))}
            </select>
          </label>
          {selected
            ? selected.lines.map((line) => {
                const draft = drafts.find((item) => item.saleLineId === line.id);
                const remaining = line.quantity - line.quantity_returned;
                if (!draft || remaining <= 0) {
                  return null;
                }
                return (
                  <div key={line.id} className={styles.lineRow}>
                    <span>
                      <span className="mono">{line.product.sku}</span> · {remaining} left ·{" "}
                      {formatStoredRefund(line.cash_paid, line.points_redeemed)}
                    </span>
                    <input
                      className={`mono ${styles.input}`}
                      type="number"
                      min={0}
                      max={remaining}
                      value={draft.quantity}
                      onChange={(event) => {
                        const quantity = Number(event.target.value);
                        setDrafts((current) =>
                          current.map((item) => (item.saleLineId === line.id ? { ...item, quantity } : item)),
                        );
                      }}
                    />
                    <select
                      className={styles.select}
                      value={draft.reason}
                      onChange={(event) => {
                        const reason = event.target.value === "damaged" ? "damaged" : "resaleable";
                        setDrafts((current) =>
                          current.map((item) => (item.saleLineId === line.id ? { ...item, reason } : item)),
                        );
                      }}
                    >
                      <option value="resaleable">resaleable</option>
                      <option value="damaged">damaged</option>
                    </select>
                  </div>
                );
              })
            : null}
          {error ? <p className={styles.error}>{error}</p> : null}
          <button
            className={styles.button}
            type="button"
            disabled={requestMutation.isPending || drafts.every((line) => line.quantity < 1)}
            onClick={() => requestMutation.mutate()}
          >
            {requestMutation.isPending ? "Requesting…" : "Request return"}
          </button>
        </section>
      ) : null}
    </div>
  );
}
