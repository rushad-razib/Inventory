import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { createCashMovement, getCashBalances, listCashEntries } from "../lib/api";
import { useAuth } from "../lib/auth";
import { prettyLabel } from "../lib/labels";
import { formatTaka } from "../lib/money";
import { can } from "../lib/permissions";
import { EmptyState } from "./EmptyState";
import styles from "./catalog.module.css";

type LocationFilter = "" | "cash" | "bank" | "drawer";

export function CashBookPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [location, setLocation] = useState<LocationFilter>("");
  const [pot, setPot] = useState<"cash" | "bank">("cash");
  const [direction, setDirection] = useState<"in" | "out">("in");
  const [amount, setAmount] = useState("");
  const [note, setNote] = useState("");
  const [error, setError] = useState<string | null>(null);
  const balancesQuery = useQuery({ queryKey: ["cash-balances"], queryFn: getCashBalances });
  const entriesQuery = useQuery({
    queryKey: ["cash-entries", location],
    queryFn: () => listCashEntries(location || undefined),
  });
  const entries = entriesQuery.data?.data ?? [];
  const balances = balancesQuery.data;
  const managesCash = can(user, "cash.manage");

  const movement = useMutation({
    mutationFn: () =>
      createCashMovement({
        location: pot,
        direction,
        amount: Number(amount),
        note: note.trim(),
      }),
    onSuccess: async () => {
      setAmount("");
      setNote("");
      setError(null);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["cash-balances"] }),
        queryClient.invalidateQueries({ queryKey: ["cash-entries"] }),
        queryClient.invalidateQueries({ queryKey: ["dashboard"] }),
      ]);
    },
    onError: (err: Error) => setError(err.message),
  });

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Cash book</h1>
          <p className={styles.subtitle}>Main cash, bank, and cash still sitting in open drawers.</p>
        </div>
      </header>

      {balancesQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
      {balancesQuery.isError ? <p className={styles.error}>{(balancesQuery.error as Error).message}</p> : null}
      {balances ? (
        <div className={styles.toolbar}>
          <section className={styles.panel}>
            <h2 className={styles.panelTitle}>Main cash</h2>
            <p className={`mono ${styles.money}`}>{formatTaka(balances.cash)}</p>
          </section>
          <section className={styles.panel}>
            <h2 className={styles.panelTitle}>Bank</h2>
            <p className={`mono ${styles.money}`}>{formatTaka(balances.bank)}</p>
          </section>
          <section className={styles.panel}>
            <h2 className={styles.panelTitle}>Open drawers</h2>
            <p className={`mono ${styles.money}`}>{formatTaka(balances.drawer)}</p>
          </section>
        </div>
      ) : null}

      {managesCash ? (
        <section className={styles.panel}>
          <h2 className={styles.panelTitle}>Add or withdraw</h2>
          <form
            className={styles.form}
            onSubmit={(event) => {
              event.preventDefault();
              movement.mutate();
            }}
          >
            <div className={styles.toolbar}>
              <label className={styles.label}>
                Account
                <select className={styles.select} value={pot} onChange={(event) => setPot(event.target.value as "cash" | "bank")}>
                  <option value="cash">Main cash</option>
                  <option value="bank">Bank</option>
                </select>
              </label>
              <label className={styles.label}>
                Direction
                <select className={styles.select} value={direction} onChange={(event) => setDirection(event.target.value as "in" | "out")}>
                  <option value="in">Add</option>
                  <option value="out">Withdraw</option>
                </select>
              </label>
              <label className={styles.label}>
                Amount
                <input
                  className={`mono ${styles.input}`}
                  type="number"
                  min={1}
                  value={amount}
                  onChange={(event) => setAmount(event.target.value)}
                />
              </label>
              <label className={styles.label}>
                Note
                <input className={styles.input} value={note} onChange={(event) => setNote(event.target.value)} maxLength={500} />
              </label>
            </div>
            {error ? <p className={styles.error}>{error}</p> : null}
            <button className={styles.button} type="submit" disabled={movement.isPending || Number(amount) < 1 || note.trim() === ""}>
              {movement.isPending ? "Saving…" : direction === "in" ? "Add" : "Withdraw"}
            </button>
          </form>
        </section>
      ) : null}

      <div className={styles.actions}>
        {(
          [
            ["", "All"],
            ["cash", "Cash"],
            ["bank", "Bank"],
            ["drawer", "Drawers"],
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
      {entriesQuery.data && entries.length === 0 ? <EmptyState>No cash entries yet.</EmptyState> : null}
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
