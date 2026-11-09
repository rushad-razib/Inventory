import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { listAuditLogs } from "../lib/api";
import { EmptyState } from "./EmptyState";
import styles from "./catalog.module.css";
import { TableToolbar, useTableFilters } from "./TableToolbar";

export function AuditPage() {
  const [actorId, setActorId] = useState("");
  const [action, setAction] = useState("");
  const [auditableType, setAuditableType] = useState("");
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const filters = useTableFilters();
  const logsQuery = useQuery({
    queryKey: ["audit-logs", actorId, action, auditableType, filters.q, filters.on, filters.page],
    queryFn: () =>
      listAuditLogs({
        actorId: actorId || undefined,
        action: action || undefined,
        auditableType: auditableType || undefined,
        q: filters.q || undefined,
        on: filters.on || undefined,
        page: filters.page,
      }),
  });
  const rows = logsQuery.data?.data ?? [];
  const selected = rows.find((row) => row.id === selectedId) ?? null;

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Audit</h1>
          <p className={styles.subtitle}>Who changed a record, and the before and after.</p>
        </div>
      </header>

      <div className={styles.toolbar}>
        <label className={styles.label}>
          Actor id
          <input
            className={styles.input}
            value={actorId}
            onChange={(event) => {
              setActorId(event.target.value);
              filters.setPage(1);
            }}
          />
        </label>
        <label className={styles.label}>
          Action
          <input
            className={styles.input}
            value={action}
            onChange={(event) => {
              setAction(event.target.value);
              filters.setPage(1);
            }}
            placeholder="sale.posted"
          />
        </label>
        <label className={styles.label}>
          Record type
          <input
            className={styles.input}
            value={auditableType}
            onChange={(event) => {
              setAuditableType(event.target.value);
              filters.setPage(1);
            }}
            placeholder="Sale"
          />
        </label>
      </div>

      <TableToolbar
        q={filters.q}
        onQ={filters.setQ}
        date={filters.on}
        onDate={filters.setOn}
        page={filters.page}
        lastPage={logsQuery.data?.meta.last_page ?? 1}
        onPage={filters.setPage}
      />
      {logsQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
      {logsQuery.isError ? <p className={styles.error}>{(logsQuery.error as Error).message}</p> : null}
      {logsQuery.data && rows.length === 0 ? <EmptyState>No audit rows for this filter.</EmptyState> : null}

      <div className={styles.split}>
        {rows.length > 0 ? (
          <div className={styles.tableWrap}>
            <table className={styles.table}>
              <thead>
                <tr>
                  <th>When</th>
                  <th>Action</th>
                  <th>Type</th>
                  <th>Actor</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr
                    key={row.id}
                    className={`${styles.clickable} ${row.id === selectedId ? styles.selected : ""}`}
                    onClick={() => setSelectedId(row.id)}
                  >
                    <td className="mono">{row.created_at ?? "—"}</td>
                    <td className="mono">{row.action}</td>
                    <td>{row.auditable_type}</td>
                    <td>{row.actor?.name ?? "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}

        {selected ? (
          <section className={styles.panel}>
            <h2 className={styles.panelTitle}>{selected.action}</h2>
            <div className={styles.detailGrid}>
              <div className={styles.detailRow}>
                <span className={styles.detailLabel}>Type</span>
                <span>{selected.auditable_type}</span>
              </div>
              <div className={styles.detailRow}>
                <span className={styles.detailLabel}>Actor</span>
                <span>{selected.actor?.name ?? "—"}</span>
              </div>
            </div>
            <h3 className={styles.panelTitle}>Before</h3>
            <pre className={styles.auditJson}>{JSON.stringify(selected.before, null, 2)}</pre>
            <h3 className={styles.panelTitle}>After</h3>
            <pre className={styles.auditJson}>{JSON.stringify(selected.after, null, 2)}</pre>
          </section>
        ) : null}
      </div>
    </div>
  );
}
