import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useMemo, useState } from "react";
import { createSupplierSettlement, listSupplierBills, listSuppliers } from "../lib/api";
import { useAuth } from "../lib/auth";
import { prettyLabel } from "../lib/labels";
import { formatTaka } from "../lib/money";
import { can } from "../lib/permissions";
import styles from "./catalog.module.css";

function remainder(bill: { amount: number; amount_paid: number; credited_amount: number }): number {
  return bill.amount - bill.amount_paid - bill.credited_amount;
}

export function SupplierBillsPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const canSettle = can(user, "purchasing.settle");
  const [supplierId, setSupplierId] = useState("");
  const [status, setStatus] = useState("");
  const [selected, setSelected] = useState<string[]>([]);
  const [payError, setPayError] = useState<string | null>(null);
  const [paidAmount, setPaidAmount] = useState<number | null>(null);

  const suppliersQuery = useQuery({ queryKey: ["suppliers"], queryFn: listSuppliers });
  const billsQuery = useQuery({
    queryKey: ["supplier-bills", supplierId, status],
    queryFn: () =>
      listSupplierBills({
        supplier_id: supplierId || undefined,
        status: status || undefined,
      }),
  });

  const bills = billsQuery.data?.data ?? [];
  const selectedBills = bills.filter((bill) => selected.includes(bill.id));
  const selectedSuppliers = new Set(selectedBills.map((bill) => bill.supplier_id));
  const selectedRemainder = selectedBills.reduce((sum, bill) => sum + remainder(bill), 0);
  const oneSupplier = selectedSuppliers.size === 1;

  const payableIds = useMemo(
    () => new Set(bills.filter((bill) => remainder(bill) > 0).map((bill) => bill.id)),
    [bills],
  );

  const payMutation = useMutation({
    mutationFn: createSupplierSettlement,
    onSuccess: async (settlement) => {
      setPaidAmount(settlement.amount);
      setSelected([]);
      setPayError(null);
      await queryClient.invalidateQueries({ queryKey: ["supplier-bills"] });
      await queryClient.invalidateQueries({ queryKey: ["cash-balances"] });
      await queryClient.invalidateQueries({ queryKey: ["cash-entries"] });
    },
    onError: (error: Error) => setPayError(error.message),
  });

  function toggle(id: string) {
    setPaidAmount(null);
    setPayError(null);
    setSelected((current) => (current.includes(id) ? current.filter((item) => item !== id) : [...current, id]));
  }

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Payables</h1>
          <p className={styles.subtitle}>Pay the open remainder of one supplier from the bank.</p>
        </div>
      </header>

      <div className={styles.toolbar}>
        <label className={styles.label}>
          Supplier
          <select
            className={styles.select}
            value={supplierId}
            onChange={(event) => {
              setSupplierId(event.target.value);
              setSelected([]);
            }}
          >
            <option value="">All suppliers</option>
            {(suppliersQuery.data?.data ?? []).map((supplier) => (
              <option key={supplier.id} value={supplier.id}>
                {supplier.name}
              </option>
            ))}
          </select>
        </label>
        <label className={styles.label}>
          Status
          <select
            className={styles.select}
            value={status}
            onChange={(event) => {
              setStatus(event.target.value);
              setSelected([]);
            }}
          >
            <option value="">All statuses</option>
            <option value="open">Open</option>
            <option value="credited">Credited</option>
            <option value="settled">Settled</option>
          </select>
        </label>
      </div>

      {billsQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
      {billsQuery.isError ? <p className={styles.error}>{(billsQuery.error as Error).message}</p> : null}
      {billsQuery.data && bills.length === 0 ? <p className={styles.muted}>No supplier bills yet.</p> : null}

      {bills.length > 0 ? (
        <div className={styles.split}>
          <div className={styles.tableWrap}>
            <table className={styles.table}>
              <thead>
                <tr>
                  {canSettle ? <th /> : null}
                  <th>Supplier</th>
                  <th>Status</th>
                  <th className={styles.money}>Amount</th>
                  <th className={styles.money}>Paid</th>
                  <th className={styles.money}>Credited</th>
                  <th className={styles.money}>Remainder</th>
                  <th>Due</th>
                  <th>Source</th>
                </tr>
              </thead>
              <tbody>
                {bills.map((bill) => {
                  const open = remainder(bill);
                  return (
                    <tr key={bill.id}>
                      {canSettle ? (
                        <td>
                          {open > 0 ? (
                            <input
                              type="checkbox"
                              checked={selected.includes(bill.id)}
                              onChange={() => toggle(bill.id)}
                              aria-label={`Select ${bill.supplier.name}`}
                            />
                          ) : null}
                        </td>
                      ) : null}
                      <td>{bill.supplier.name}</td>
                      <td>{prettyLabel(bill.status)}</td>
                      <td className={`mono ${styles.money}`}>{formatTaka(bill.amount)}</td>
                      <td className={`mono ${styles.money}`}>{formatTaka(bill.amount_paid)}</td>
                      <td className={`mono ${styles.money}`}>{formatTaka(bill.credited_amount)}</td>
                      <td className={`mono ${styles.money}`}>{formatTaka(open)}</td>
                      <td className="mono">{bill.due_on ?? "—"}</td>
                      <td>{prettyLabel(bill.source_type)}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {canSettle ? (
            <section className={styles.panel}>
              <h2 className={styles.panelTitle}>Pay from bank</h2>
              <div className={styles.detailGrid}>
                <div className={styles.detailRow}>
                  <span className={styles.detailLabel}>Bills</span>
                  <span className="mono">{selected.filter((id) => payableIds.has(id)).length}</span>
                </div>
                <div className={styles.detailRow}>
                  <span className={styles.detailLabel}>Remainder</span>
                  <span className="mono">{formatTaka(selectedRemainder)}</span>
                </div>
              </div>
              {selected.length > 0 && !oneSupplier ? (
                <p className={styles.error}>Choose bills from one supplier.</p>
              ) : null}
              {payError ? <p className={styles.error}>{payError}</p> : null}
              {paidAmount !== null ? (
                <p className={styles.muted}>
                  Bank payment recorded for {formatTaka(paidAmount)}.
                </p>
              ) : null}
              <button
                className={styles.button}
                type="button"
                disabled={payMutation.isPending || selected.length === 0 || !oneSupplier}
                onClick={() => payMutation.mutate(selected)}
              >
                {payMutation.isPending ? "Paying…" : "Pay from bank"}
              </button>
            </section>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
