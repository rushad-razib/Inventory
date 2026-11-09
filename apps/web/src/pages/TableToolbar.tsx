import { useCallback, useState } from "react";
import type { TableQuery } from "../lib/api";
import styles from "./catalog.module.css";

export function useTableFilters(initialQ = "") {
  const [q, setQuery] = useState(initialQ);
  const [on, setDay] = useState("");
  const [page, setPageState] = useState(1);
  const [seed, setSeed] = useState(initialQ);

  if (initialQ !== seed) {
    setSeed(initialQ);
    setQuery(initialQ);
    setPageState(1);
  }

  const table: TableQuery = {
    q: q || undefined,
    on: on || undefined,
    page,
  };

  const setQ = useCallback((value: string) => {
    setQuery(value);
    setPageState(1);
  }, []);

  const setOn = useCallback((value: string) => {
    setDay(value);
    setPageState(1);
  }, []);

  const setPage = useCallback((value: number) => {
    setPageState(value);
  }, []);

  return { q, on, page, table, setQ, setOn, setPage };
}

export function TableToolbar({
  q,
  onQ,
  date,
  onDate,
  page,
  lastPage,
  onPage,
}: {
  q: string;
  onQ: (value: string) => void;
  date?: string;
  onDate?: (value: string) => void;
  page: number;
  lastPage: number;
  onPage: (page: number) => void;
}) {
  const pages = Math.max(lastPage, 1);

  return (
    <div className={styles.toolbar}>
      <label className={styles.label}>
        Search
        <input className={styles.input} value={q} onChange={(event) => onQ(event.target.value)} />
      </label>
      {onDate ? (
        <label className={styles.label}>
          Date
          <input className={styles.input} type="date" value={date ?? ""} onChange={(event) => onDate(event.target.value)} />
        </label>
      ) : null}
      <div className={styles.pager}>
        <button className={styles.buttonSecondary} type="button" disabled={page <= 1} onClick={() => onPage(page - 1)}>
          Previous
        </button>
        <span className={styles.muted}>
          {page} / {pages}
        </span>
        <button className={styles.buttonSecondary} type="button" disabled={page >= pages} onClick={() => onPage(page + 1)}>
          Next
        </button>
      </div>
    </div>
  );
}
