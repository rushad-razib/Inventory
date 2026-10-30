import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { getCashBalances, listCashEntries } from "../lib/api";
import { prettyLabel } from "../lib/labels";
import { formatTaka } from "../lib/money";
import styles from "./catalog.module.css";

type LocationFilter = "" | "till" | "bank";

export function CashBookPage() {
  const [location, setLocation] = useState<LocationFilter>("");
  const balancesQuery = useQuery({ queryKey: ["cash-balances"], queryFn: getCashBalances });
  const entriesQuery = useQuery({
    queryKey: ["cash-entries", location],
    queryFn: () => listCashEntries(location || undefined),
  });
  const entries = entriesQuery.data?.data ?? [];
  const balances = balancesQuery.data;

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Cash book</h1>
          <p className={styles.subtitle}>Till and bank are sums of cash entries.</p>
        </div>
      </header>

      {balancesQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
      {balancesQuery.isError ? <p className={styles.error}>{(balancesQuery.error as Error).message}</p> : null}
      {balances ? (
        <div className={styles.toolbar}>
          <section className={styles.panel}>
            <h2 className={styles.panelTitle}>Till</h2>
            <p className={`mono ${styles.money}`}>{formatTaka(balances.till)}</p>
          </section>
          <section className={styles.panel}>
            <h2 className={styles.panelTitle}>Bank</h2>
            <p className={`mono ${styles.money}`}>{formatTaka(balances.bank)}</p>
          </section>
        </div>
      ) : null}

      <div className={styles.actions}>
        {(
          [
            ["", "All"],
            ["till", "Till"],
            ["bank", "Bank"],
          ] as const
        ).map(([value, label]) => (
          <button
            key={label}
            type="button"
            className={location === value ? styles.button : styles.buttonSecondary}
            onClick={() => setLocation(value)}
          >
            {label}
          </button>
        ))}
      </div>

      {entriesQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
      {entriesQuery.isError ? <p className={styles.error}>{(entriesQuery.error as Error).message}</p> : null}
      {entriesQuery.data && entries.length === 0 ? <p className={styles.muted}>No cash entries yet.</p> : null}
      {entries.length > 0 ? (
        <div className={styles.tableWrap}>
          <table className={styles.table}>
            <thead>
              <tr>
                <th>When</th>
                <th>Location</th>
                <th>Direction</th>
                <th>Type</th>
                <th className={styles.money}>Amount</th>
                <th>Source</th>
                <th>Actor</th>
              </tr>
            </thead>
            <tbody>
              {entries.map((entry) => (
                <tr key={entry.id}>
                  <td className="mono">{entry.occurred_at ?? "—"}</td>
                  <td>{prettyLabel(entry.location)}</td>
                  <td>{prettyLabel(entry.direction)}</td>
                  <td>{prettyLabel(entry.type)}</td>
                  <td className={`mono ${styles.money}`}>{formatTaka(entry.amount)}</td>
                  <td>{prettyLabel(entry.source_type)}</td>
                  <td>{entry.actor?.name ?? "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}
