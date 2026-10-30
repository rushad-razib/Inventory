import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  acceptSupplierReturn,
  createSupplierReturn,
  listStockLots,
  listSupplierReturns,
  recordSupplierRefund,
  rejectSupplierReturn,
  shipSupplierReturn,
  type SupplierReturn,
} from "../lib/api";
import { useAuth } from "../lib/auth";
import { prettyLabel } from "../lib/labels";
import { formatTaka } from "../lib/money";
import { can } from "../lib/permissions";
import styles from "./catalog.module.css";

const schema = z.object({
  stock_lot_id: z.string().min(1, "Choose a damaged lot"),
  quantity: z.coerce.number().int().min(1, "Quantity must be at least 1"),
});

type FormValues = z.infer<typeof schema>;

function outcomeText(row: SupplierReturn): string {
  const parts: string[] = [];
  if (row.credited_amount > 0) {
    parts.push(`Credited payable ${formatTaka(row.credited_amount)}`);
  }
  if (row.credit_note) {
    parts.push(`Credit note ${formatTaka(row.credit_note.amount)}`);
  }
  if (row.refund_receivable) {
    parts.push(
      `Refund receivable ${formatTaka(row.refund_receivable.amount)} (${prettyLabel(row.refund_receivable.status)})`,
    );
  }
  if (parts.length === 0) {
    return "No cash. Damaged quantity removed.";
  }
  return parts.join(". ");
}

export function SupplierReturnsPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const canManage = can(user, "supplier_returns.manage");
  const canSettle = can(user, "purchasing.settle");
  const pin = user?.warehouse_id ?? null;
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  const returnsQuery = useQuery({ queryKey: ["supplier-returns"], queryFn: listSupplierReturns });
  const lotsQuery = useQuery({
    queryKey: ["stock-lots"],
    queryFn: () => listStockLots(),
    enabled: canManage,
  });

  const rows = returnsQuery.data?.data ?? [];
  const selected = rows.find((row) => row.id === selectedId) ?? null;
  const damagedLots = (lotsQuery.data?.data ?? []).filter((lot) => lot.qty_damaged > 0);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { stock_lot_id: "", quantity: 1 },
  });

  async function refresh(id?: string) {
    await queryClient.invalidateQueries({ queryKey: ["supplier-returns"] });
    await queryClient.invalidateQueries({ queryKey: ["stock-lots"] });
    await queryClient.invalidateQueries({ queryKey: ["supplier-bills"] });
    await queryClient.invalidateQueries({ queryKey: ["cash-balances"] });
    await queryClient.invalidateQueries({ queryKey: ["cash-entries"] });
    if (id) {
      setSelectedId(id);
    }
  }

  const createMutation = useMutation({
    mutationFn: createSupplierReturn,
    onSuccess: async (created) => {
      setFormError(null);
      reset({ stock_lot_id: "", quantity: 1 });
      await refresh(created.id);
    },
    onError: (error: Error) => setFormError(error.message),
  });

  const shipMutation = useMutation({
    mutationFn: shipSupplierReturn,
    onSuccess: async (row) => {
      setActionError(null);
      await refresh(row.id);
    },
    onError: (error: Error) => setActionError(error.message),
  });

  const acceptMutation = useMutation({
    mutationFn: acceptSupplierReturn,
    onSuccess: async (row) => {
      setActionError(null);
      await refresh(row.id);
    },
    onError: (error: Error) => setActionError(error.message),
  });

  const rejectMutation = useMutation({
    mutationFn: rejectSupplierReturn,
    onSuccess: async (row) => {
      setActionError(null);
      await refresh(row.id);
    },
    onError: (error: Error) => setActionError(error.message),
  });

  const refundMutation = useMutation({
    mutationFn: recordSupplierRefund,
    onSuccess: async (row) => {
      setActionError(null);
      await refresh(row.id);
    },
    onError: (error: Error) => setActionError(error.message),
  });

  const busy =
    shipMutation.isPending || acceptMutation.isPending || rejectMutation.isPending || refundMutation.isPending;
  const canShipHere =
    selected !== null && (pin === null || pin === selected.lot.warehouse.id);

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Supplier returns</h1>
          <p className={styles.subtitle}>Ship damaged stock. Procurement accepts the supplier’s outcome.</p>
        </div>
      </header>

      <div className={styles.split}>
        <div>
          {returnsQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
          {returnsQuery.isError ? <p className={styles.error}>{(returnsQuery.error as Error).message}</p> : null}
          {returnsQuery.data && rows.length === 0 ? <p className={styles.muted}>No supplier returns yet.</p> : null}
          {rows.length > 0 ? (
            <div className={styles.tableWrap}>
              <table className={styles.table}>
                <thead>
                  <tr>
                    <th>Product</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th className={styles.money}>Quantity</th>
                    <th className={styles.money}>Lot cost</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => (
                    <tr
                      key={row.id}
                      className={`${styles.clickable} ${row.id === selectedId ? styles.selected : ""}`}
                      onClick={() => {
                        setSelectedId(row.id);
                        setActionError(null);
                      }}
                    >
                      <td>
                        <span className="mono">{row.lot.product.sku}</span> {row.lot.product.name}
                      </td>
                      <td>{row.lot.supplier?.name ?? "—"}</td>
                      <td>{prettyLabel(row.status)}</td>
                      <td className={`mono ${styles.money}`}>{row.quantity}</td>
                      <td className={`mono ${styles.money}`}>{formatTaka(row.quantity * row.unit_cost)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : null}
        </div>

        <div className={styles.stack}>
          {selected ? (
            <section className={styles.panel}>
              <h2 className={styles.panelTitle}>{selected.lot.product.name}</h2>
              <div className={styles.detailGrid}>
                <div className={styles.detailRow}>
                  <span className={styles.detailLabel}>Status</span>
                  <span>{prettyLabel(selected.status)}</span>
                </div>
                <div className={styles.detailRow}>
                  <span className={styles.detailLabel}>Warehouse</span>
                  <span>{selected.lot.warehouse.name}</span>
                </div>
                <div className={styles.detailRow}>
                  <span className={styles.detailLabel}>Opened</span>
                  <span>{selected.customer_return_id ? "Customer return" : "Warehouse"}</span>
                </div>
                <div className={styles.detailRow}>
                  <span className={styles.detailLabel}>Claim</span>
                  <span className="mono">{formatTaka(selected.quantity * selected.unit_cost)}</span>
                </div>
                {selected.status === "accepted" ? (
                  <div className={styles.detailRow}>
                    <span className={styles.detailLabel}>Outcome</span>
                    <span>{outcomeText(selected)}</span>
                  </div>
                ) : null}
              </div>
              {actionError ? <p className={styles.error}>{actionError}</p> : null}
              <div className={styles.actions}>
                {canManage && selected.status === "draft" && canShipHere ? (
                  <button
                    className={styles.button}
                    type="button"
                    disabled={busy}
                    onClick={() => shipMutation.mutate(selected.id)}
                  >
                    Ship
                  </button>
                ) : null}
                {canSettle && selected.status === "shipped" ? (
                  <>
                    <button
                      className={styles.button}
                      type="button"
                      disabled={busy}
                      onClick={() => acceptMutation.mutate(selected.id)}
                    >
                      Accept
                    </button>
                    <button
                      className={styles.buttonSecondary}
                      type="button"
                      disabled={busy}
                      onClick={() => rejectMutation.mutate(selected.id)}
                    >
                      Reject
                    </button>
                  </>
                ) : null}
                {canSettle && selected.refund_receivable?.status === "open" ? (
                  <button
                    className={styles.button}
                    type="button"
                    disabled={busy}
                    onClick={() => refundMutation.mutate(selected.id)}
                  >
                    Record bank refund
                  </button>
                ) : null}
              </div>
            </section>
          ) : null}

          {canManage ? (
            <section className={styles.panel}>
              <h2 className={styles.panelTitle}>Open a return</h2>
              <form
                className={styles.form}
                onSubmit={handleSubmit(async (values) => {
                  setFormError(null);
                  try {
                    await createMutation.mutateAsync(values);
                  } catch {
                    // The mutation records the message.
                  }
                })}
              >
                <label className={styles.label}>
                  Damaged lot
                  <select className={styles.select} {...register("stock_lot_id")}>
                    <option value="">Choose a lot</option>
                    {damagedLots.map((lot) => (
                      <option key={lot.id} value={lot.id}>
                        {lot.product.sku} · {lot.warehouse.code} · damaged {lot.qty_damaged}
                      </option>
                    ))}
                  </select>
                </label>
                {errors.stock_lot_id ? <p className={styles.error}>{errors.stock_lot_id.message}</p> : null}
                <label className={styles.label}>
                  Quantity
                  <input className={styles.input} type="number" min={1} {...register("quantity")} />
                </label>
                {errors.quantity ? <p className={styles.error}>{errors.quantity.message}</p> : null}
                {formError ? <p className={styles.error}>{formError}</p> : null}
                <button className={styles.button} type="submit" disabled={isSubmitting || createMutation.isPending}>
                  {createMutation.isPending ? "Opening…" : "Open return"}
                </button>
              </form>
            </section>
          ) : null}
        </div>
      </div>
    </div>
  );
}
