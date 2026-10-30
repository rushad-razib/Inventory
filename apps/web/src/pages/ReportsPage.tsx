import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import {
  downloadReport,
  getDemandReport,
  getMovementReport,
  getPurchaseOrderReport,
  getRoiReport,
  getSalesReport,
  getStockReport,
  getSupplierSpendReport,
  listProducts,
  listWarehouses,
  type ReportGroup,
} from "../lib/api";
import { prettyLabel } from "../lib/labels";
import { formatTaka } from "../lib/money";
import styles from "./catalog.module.css";

type ReportId = "demand" | "roi" | "sales" | "stock" | "movements" | "supplier-spend" | "purchase-orders";

const REPORTS: { id: ReportId; label: string }[] = [
  { id: "demand", label: "Demand" },
  { id: "roi", label: "ROI" },
  { id: "sales", label: "Sales" },
  { id: "stock", label: "Stock" },
  { id: "movements", label: "Movements" },
  { id: "supplier-spend", label: "Supplier spend" },
  { id: "purchase-orders", label: "Purchase orders" },
];

const MOVEMENT_TYPES = [
  "receipt",
  "transfer_out",
  "transfer_in",
  "adjustment",
  "sale",
  "customer_return_resaleable",
  "customer_return_damaged",
  "supplier_return",
];

type Cell = { text: string; money?: boolean };
type TableModel = { columns: string[]; rows: Cell[][] };

export function ReportsPage() {
  const [report, setReport] = useState<ReportId>("demand");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [group, setGroup] = useState<ReportGroup>("product");
  const [movementType, setMovementType] = useState("");
  const [warehouseId, setWarehouseId] = useState("");
  const [productId, setProductId] = useState("");
  const [exportError, setExportError] = useState<string | null>(null);
  const needsRange = report === "demand" || report === "roi" || report === "sales";
  const needsGroup = report === "demand" || report === "roi";
  const needsMovement = report === "movements";

  const warehousesQuery = useQuery({
    queryKey: ["warehouses"],
    queryFn: listWarehouses,
    enabled: needsMovement,
  });
  const productsQuery = useQuery({
    queryKey: ["products"],
    queryFn: listProducts,
    enabled: needsMovement,
  });
  const reportQuery = useQuery({
    queryKey: ["report", report, from, to, group, movementType, warehouseId, productId],
    queryFn: () => loadReport(report, { from, to, group, movementType, warehouseId, productId }),
  });

  async function exportCsv() {
    setExportError(null);
    try {
      await downloadReport(report, exportQuery({ report, from, to, group, movementType, warehouseId, productId }));
    } catch (error) {
      setExportError((error as Error).message);
    }
  }

  const table = reportQuery.data;

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Reports</h1>
          <p className={styles.subtitle}>The same numbers as the dashboard, with a CSV of the current filter.</p>
        </div>
        <button className={styles.button} type="button" onClick={() => void exportCsv()}>
          Download CSV
        </button>
      </header>

      <div className={styles.toolbar}>
        <label className={styles.label}>
          Report
          <select className={styles.select} value={report} onChange={(event) => setReport(event.target.value as ReportId)}>
            {REPORTS.map((item) => (
              <option key={item.id} value={item.id}>
                {item.label}
              </option>
            ))}
          </select>
        </label>
        {needsRange ? (
          <>
            <label className={styles.label}>
              From
              <input className={styles.input} type="date" value={from} onChange={(event) => setFrom(event.target.value)} />
            </label>
            <label className={styles.label}>
              To
              <input className={styles.input} type="date" value={to} onChange={(event) => setTo(event.target.value)} />
            </label>
          </>
        ) : null}
        {needsGroup ? (
          <label className={styles.label}>
            Breakdown
            <select className={styles.select} value={group} onChange={(event) => setGroup(event.target.value as ReportGroup)}>
              <option value="product">Product</option>
              <option value="category">Category</option>
            </select>
          </label>
        ) : null}
        {needsMovement ? (
          <>
            <label className={styles.label}>
              Type
              <select className={styles.select} value={movementType} onChange={(event) => setMovementType(event.target.value)}>
                <option value="">All types</option>
                {MOVEMENT_TYPES.map((type) => (
                  <option key={type} value={type}>
                    {prettyLabel(type)}
                  </option>
                ))}
              </select>
            </label>
            <label className={styles.label}>
              Warehouse
              <select className={styles.select} value={warehouseId} onChange={(event) => setWarehouseId(event.target.value)}>
                <option value="">All warehouses</option>
                {(warehousesQuery.data?.data ?? []).map((warehouse) => (
                  <option key={warehouse.id} value={warehouse.id}>
                    {warehouse.code}
                  </option>
                ))}
              </select>
            </label>
            <label className={styles.label}>
              Product
              <select className={styles.select} value={productId} onChange={(event) => setProductId(event.target.value)}>
                <option value="">All products</option>
                {(productsQuery.data?.data ?? []).map((product) => (
                  <option key={product.id} value={product.id}>
                    {product.sku}
                  </option>
                ))}
              </select>
            </label>
            <label className={styles.label}>
              From
              <input className={styles.input} type="date" value={from} onChange={(event) => setFrom(event.target.value)} />
            </label>
            <label className={styles.label}>
              To
              <input className={styles.input} type="date" value={to} onChange={(event) => setTo(event.target.value)} />
            </label>
          </>
        ) : null}
      </div>

      {exportError ? <p className={styles.error}>{exportError}</p> : null}
      {reportQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
      {reportQuery.isError ? <p className={styles.error}>{(reportQuery.error as Error).message}</p> : null}
      {table && table.rows.length === 0 ? <p className={styles.muted}>No rows for this filter.</p> : null}
      {table && table.rows.length > 0 ? (
        <div className={styles.tableWrap}>
          <table className={styles.table}>
            <thead>
              <tr>
                {table.columns.map((column) => (
                  <th key={column} className={column.endsWith("value") || moneyColumns.has(column) ? styles.money : undefined}>
                    {column}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {table.rows.map((row, index) => (
                <tr key={index}>
                  {row.map((cell, cellIndex) => (
                    <td key={cellIndex} className={cell.money ? `mono ${styles.money}` : undefined}>
                      {cell.text}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}

const moneyColumns = new Set(["Net taka", "Net sales", "Cost", "Cash", "Card", "Billed", "Paid", "Refunded", "Owned value", "Consignment value", "Unit cost"]);

async function loadReport(
  report: ReportId,
  filters: { from: string; to: string; group: ReportGroup; movementType: string; warehouseId: string; productId: string },
): Promise<TableModel> {
  const range = { from: filters.from || undefined, to: filters.to || undefined, group: filters.group };

  if (report === "demand") {
    const rows = await getDemandReport(range);
    return {
      columns: ["Date", "Group", "Net units", "Net taka"],
      rows: rows.map((row) => [
        { text: row.date },
        { text: row.group_label },
        { text: String(row.net_units), money: true },
        { text: formatTaka(row.net_taka), money: true },
      ]),
    };
  }

  if (report === "roi") {
    const rows = await getRoiReport(range);
    return {
      columns: ["Group", "Net units", "Net sales", "Cost", "ROI"],
      rows: rows.map((row) => [
        { text: row.group_label },
        { text: String(row.net_units), money: true },
        { text: formatTaka(row.net_sales), money: true },
        { text: formatTaka(row.cost), money: true },
        { text: row.roi, money: true },
      ]),
    };
  }

  if (report === "sales") {
    const rows = await getSalesReport(range);
    return {
      columns: ["Date", "Sales", "Points", "Cash", "Card"],
      rows: rows.map((row) => [
        { text: row.date },
        { text: String(row.sale_count), money: true },
        { text: String(row.points_redeemed), money: true },
        { text: formatTaka(row.cash), money: true },
        { text: formatTaka(row.card), money: true },
      ]),
    };
  }

  if (report === "stock") {
    const rows = await getStockReport();
    return {
      columns: ["Warehouse", "SKU", "Product", "Owned", "Consignment", "Owned value", "Consignment value"],
      rows: rows.map((row) => [
        { text: row.warehouse_code },
        { text: row.sku },
        { text: row.product_name },
        { text: String(row.owned_on_hand), money: true },
        { text: String(row.consignment_on_hand), money: true },
        { text: formatTaka(row.owned_value), money: true },
        { text: formatTaka(row.consignment_value), money: true },
      ]),
    };
  }

  if (report === "movements") {
    const rows = await getMovementReport({
      type: filters.movementType || undefined,
      warehouseId: filters.warehouseId || undefined,
      productId: filters.productId || undefined,
      from: filters.from || undefined,
      to: filters.to || undefined,
    });
    return {
      columns: ["When", "Warehouse", "SKU", "Type", "Quantity", "Unit cost"],
      rows: rows.map((row) => [
        { text: row.occurred_at ?? "—" },
        { text: row.warehouse.code },
        { text: row.product.sku },
        { text: prettyLabel(row.type) },
        { text: String(row.quantity), money: true },
        { text: formatTaka(row.unit_cost), money: true },
      ]),
    };
  }

  if (report === "supplier-spend") {
    const rows = await getSupplierSpendReport();
    return {
      columns: ["Supplier", "Billed", "Paid", "Refunded"],
      rows: rows.map((row) => [
        { text: row.supplier_name },
        { text: formatTaka(row.billed), money: true },
        { text: formatTaka(row.paid), money: true },
        { text: formatTaka(row.refunded), money: true },
      ]),
    };
  }

  const rows = await getPurchaseOrderReport();
  return {
    columns: ["Number", "Supplier", "Status", "Ordered", "Received"],
    rows: rows.map((row) => [
      { text: row.number },
      { text: row.supplier_name },
      { text: prettyLabel(row.status) },
      { text: String(row.quantity_ordered), money: true },
      { text: String(row.quantity_received), money: true },
    ]),
  };
}

function exportQuery(filters: {
  report: ReportId;
  from: string;
  to: string;
  group: ReportGroup;
  movementType: string;
  warehouseId: string;
  productId: string;
}): string {
  const params = new URLSearchParams();
  if (filters.report === "demand" || filters.report === "roi" || filters.report === "sales") {
    if (filters.from) params.set("from", filters.from);
    if (filters.to) params.set("to", filters.to);
  }
  if (filters.report === "demand" || filters.report === "roi") {
    params.set("group", filters.group);
  }
  if (filters.report === "movements") {
    if (filters.movementType) params.set("filter[type]", filters.movementType);
    if (filters.warehouseId) params.set("filter[warehouse_id]", filters.warehouseId);
    if (filters.productId) params.set("filter[product_id]", filters.productId);
    if (filters.from) params.set("filter[from]", filters.from);
    if (filters.to) params.set("filter[to]", filters.to);
  }
  return params.toString();
}
