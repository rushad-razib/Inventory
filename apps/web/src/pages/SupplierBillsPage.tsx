import { useQuery } from "@tanstack/react-query";
import { listSupplierBills } from "../lib/api";
import { prettyLabel } from "../lib/labels";
import { formatTaka } from "../lib/money";
import styles from "./catalog.module.css";

export function SupplierBillsPage() {
  const billsQuery = useQuery({ queryKey: ["supplier-bills"], queryFn: listSupplierBills });
  const bills = billsQuery.data?.data ?? [];

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Supplier bills</h1>
          <p className={styles.subtitle}>Open bills from outright receipts. Payment waits for a later phase.</p>
        </div>
      </header>
      {billsQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
      {billsQuery.isError ? <p className={styles.error}>{(billsQuery.error as Error).message}</p> : null}
      {billsQuery.data && bills.length === 0 ? <p className={styles.muted}>No supplier bills yet.</p> : null}
      {bills.length > 0 ? (
        <div className={styles.tableWrap}>
          <table className={styles.table}>
            <thead>
              <tr>
                <th>Supplier</th>
                <th>Status</th>
                <th className={styles.money}>Amount</th>
                <th className={styles.money}>Paid</th>
                <th className={styles.money}>Credited</th>
                <th>Due</th>
                <th>Source</th>
              </tr>
            </thead>
            <tbody>
              {bills.map((bill) => (
                <tr key={bill.id}>
                  <td>{bill.supplier.name}</td>
                  <td>{prettyLabel(bill.status)}</td>
                  <td className={`mono ${styles.money}`}>{formatTaka(bill.amount)}</td>
                  <td className={`mono ${styles.money}`}>{formatTaka(bill.amount_paid)}</td>
                  <td className={`mono ${styles.money}`}>{formatTaka(bill.credited_amount)}</td>
                  <td className="mono">{bill.due_on ?? "—"}</td>
                  <td>{prettyLabel(bill.source_type)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}
