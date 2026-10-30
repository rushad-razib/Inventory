import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { getDashboard, type DemandPoint, type ReportGroup, type RoiPoint } from "../lib/api";
import { useAuth } from "../lib/auth";
import { formatTaka } from "../lib/money";
import { can } from "../lib/permissions";
import { chartColor, DemandChart, RoiChart, type ChartBar, type DemandSeries } from "./BarChart";
import styles from "./catalog.module.css";

export function HomePage() {
  const { user } = useAuth();
  const allowed = can(user, "reports.view");

  if (!user) {
    return null;
  }

  if (!allowed) {
    return (
      <div className={styles.page}>
        <header className={styles.header}>
          <div>
            <h1 className={styles.title}>Welcome, {user.name}</h1>
            <p className={styles.subtitle}>
              Signed in as <span className="mono">{user.email}</span>
              {user.department ? <> · {user.department.name}</> : null}
            </p>
          </div>
        </header>
        <section className={styles.panel}>
          <h2 className={styles.panelTitle}>Roles</h2>
          <ul className={styles.list}>
            {user.roles.map((role) => (
              <li key={role}>{role}</li>
            ))}
          </ul>
        </section>
      </div>
    );
  }

  return <Dashboard />;
}

function Dashboard() {
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [group, setGroup] = useState<ReportGroup>("product");
  const [metric, setMetric] = useState<"units" | "taka">("units");
  const dashboardQuery = useQuery({
    queryKey: ["dashboard", from, to, group],
    queryFn: () => getDashboard({ from: from || undefined, to: to || undefined, group }),
  });
  const payload = dashboardQuery.data?.data;
  const meta = dashboardQuery.data?.meta;
  const tiles = payload?.tiles;

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Dashboard</h1>
          <p className={styles.subtitle}>Stock, sales still on the books, and the cash sums.</p>
        </div>
      </header>

      {dashboardQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
      {dashboardQuery.isError ? <p className={styles.error}>{(dashboardQuery.error as Error).message}</p> : null}

      {tiles ? (
        <div className={styles.tiles}>
          <Tile label="Owned stock" value={formatTaka(tiles.owned_value)} />
          <Tile label="Consignment stock" value={formatTaka(tiles.consignment_value)} />
          <Tile label="Low stock" value={String(tiles.low_stock)} />
          <Tile label="Sales today" value={formatTaka(tiles.sales_today)} />
          <Tile label="Open orders" value={String(tiles.open_purchase_orders)} />
          <Tile label="Pending approvals" value={String(tiles.pending_approvals)} />
          <Tile label="Payables due" value={formatTaka(tiles.payables_due)} />
          <Tile label="Till" value={formatTaka(tiles.till)} />
          <Tile label="Bank" value={formatTaka(tiles.bank)} />
        </div>
      ) : null}

      <section className={styles.panel}>
        <div className={styles.toolbar}>
          <label className={styles.label}>
            From
            <input className={styles.input} type="date" value={from || meta?.from || ""} onChange={(event) => setFrom(event.target.value)} />
          </label>
          <label className={styles.label}>
            To
            <input className={styles.input} type="date" value={to || meta?.to || ""} onChange={(event) => setTo(event.target.value)} />
          </label>
          <label className={styles.label}>
            Breakdown
            <select className={styles.select} value={group} onChange={(event) => setGroup(event.target.value as ReportGroup)}>
              <option value="product">Product</option>
              <option value="category">Category</option>
            </select>
          </label>
          <label className={styles.label}>
            Demand
            <select className={styles.select} value={metric} onChange={(event) => setMetric(event.target.value as "units" | "taka")}>
              <option value="units">Net units</option>
              <option value="taka">Net taka</option>
            </select>
          </label>
        </div>
      </section>

      <section className={styles.panel}>
        <h2 className={styles.panelTitle}>Daily demand</h2>
        <DemandChart series={demandSeries(payload?.demand ?? [], metric)} empty="No sales in this range." />
        <ChartLegend labels={[...new Set((payload?.demand ?? []).map((point) => point.group_label))]} />
      </section>

      <section className={styles.panel}>
        <h2 className={styles.panelTitle}>ROI</h2>
        <RoiChart bars={roiBars(payload?.roi ?? [])} />
      </section>
    </div>
  );
}

function Tile({ label, value }: { label: string; value: string }) {
  return (
    <section className={styles.panel}>
      <h2 className={styles.panelTitle}>{label}</h2>
      <p className={styles.tileValue}>{value}</p>
    </section>
  );
}

function ChartLegend({ labels }: { labels: string[] }) {
  if (labels.length < 2) {
    return null;
  }

  return (
    <div className={styles.legend}>
      {labels.map((label, index) => (
        <span key={label}>
          <span className={styles.swatch} style={{ background: chartColor(index) }} />
          {label}
        </span>
      ))}
    </div>
  );
}

function demandSeries(points: DemandPoint[], metric: "units" | "taka"): DemandSeries[] {
  const groups = [...new Set(points.map((point) => point.group_label))];

  return groups.map((label, index) => ({
    label,
    color: chartColor(index),
    points: points
      .filter((point) => point.group_label === label)
      .map((point) => {
        const value = metric === "units" ? point.net_units : point.net_taka;
        return {
          date: point.date,
          value,
          title: `${point.date} ${label}: ${metric === "taka" ? formatTaka(value) : value}`,
        };
      }),
  }));
}

function roiBars(points: RoiPoint[]): ChartBar[] {
  return points.map((point, index) => ({
    label: point.group_label.slice(0, 10),
    value: Number(point.roi),
    title: `${point.group_label}: ${point.roi} on ${formatTaka(point.net_sales)} sales and ${formatTaka(point.cost)} cost`,
    color: chartColor(index),
  }));
}
