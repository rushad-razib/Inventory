import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  approveAdjustment,
  createAdjustment,
  listAdjustments,
  listProducts,
  listWarehouses,
  rejectAdjustment,
  type Adjustment,
} from "../lib/api";
import { useAuth } from "../lib/auth";
import { prettyLabel } from "../lib/labels";
import { can } from "../lib/permissions";
import styles from "./catalog.module.css";

const schema = z
  .object({
    warehouse_id: z.string().min(1, "Choose a warehouse"),
    product_id: z.string().min(1, "Choose a product"),
    quantity: z.coerce.number().int().refine((value) => value !== 0, "Quantity cannot be zero"),
    reason: z.enum(["damage", "count", "correction"]),
  })
  .refine((values) => values.reason !== "damage" || values.quantity > 0, {
    message: "Damage quantity must be positive",
    path: ["quantity"],
  });

type FormValues = z.infer<typeof schema>;

export function AdjustmentsPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const pin = user?.warehouse_id ?? null;
  const canCreate = can(user, "inventory.adjust");
  const canApprove = can(user, "purchasing.approve");

  const warehousesQuery = useQuery({ queryKey: ["warehouses"], queryFn: listWarehouses });
  const productsQuery = useQuery({ queryKey: ["products"], queryFn: listProducts });
  const adjustmentsQuery = useQuery({
    queryKey: ["adjustments", pin],
    queryFn: () => listAdjustments(pin ?? undefined),
  });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      warehouse_id: pin ?? "",
      product_id: "",
      quantity: 1,
      reason: "count",
    },
  });

  const refresh = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ["adjustments"] }),
      queryClient.invalidateQueries({ queryKey: ["stock-balances"] }),
      queryClient.invalidateQueries({ queryKey: ["stock-lots"] }),
      queryClient.invalidateQueries({ queryKey: ["stock-movements"] }),
    ]);
  };

  const createMutation = useMutation({
    mutationFn: createAdjustment,
    onSuccess: async (adjustment) => {
      await refresh();
      reset({
        warehouse_id: pin ?? "",
        product_id: "",
        quantity: 1,
        reason: "count",
      });
      setFormError(null);
      setSelectedId(adjustment.id);
    },
    onError: (err: Error) => setFormError(err.message),
  });

  const decisionMutation = useMutation({
    mutationFn: async (decision: "approve" | "reject") => {
      if (!selectedId) {
        throw new Error("Select an adjustment.");
      }
      return decision === "approve" ? approveAdjustment(selectedId) : rejectAdjustment(selectedId);
    },
    onSuccess: async () => {
      setActionError(null);
      await refresh();
    },
    onError: (err: Error) => setActionError(err.message),
  });

  const warehouses = warehousesQuery.data?.data ?? [];
  const warehouseOptions = pin ? warehouses.filter((warehouse) => warehouse.id === pin) : warehouses;
  const products = (productsQuery.data?.data ?? []).filter((product) => product.track_stock);
  const adjustments = adjustmentsQuery.data?.data ?? [];
  const selected = adjustments.find((adjustment) => adjustment.id === selectedId) ?? null;

  const onSubmit = handleSubmit((values) => {
    setFormError(null);
    createMutation.mutate(values);
  });

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Adjustments</h1>
          <p className={styles.subtitle}>
            A positive count or correction puts units on the shelf. Damage moves them off the shelf.
          </p>
        </div>
      </header>

      <div className={styles.split}>
        <div className={styles.tableWrap}>
          {adjustmentsQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
          {adjustmentsQuery.isError ? (
            <p className={styles.error}>{(adjustmentsQuery.error as Error).message}</p>
          ) : null}
          {adjustmentsQuery.data && adjustments.length === 0 ? (
            <p className={styles.muted}>No adjustments yet.</p>
          ) : null}
          {adjustments.length > 0 ? (
            <table className={styles.table}>
              <thead>
                <tr>
                  <th>Warehouse</th>
                  <th>Product</th>
                  <th className={styles.money}>Quantity</th>
                  <th>Reason</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {adjustments.map((adjustment) => (
                  <tr
                    key={adjustment.id}
                    className={[styles.clickable, selectedId === adjustment.id ? styles.selected : ""]
                      .filter(Boolean)
                      .join(" ")}
                    onClick={() => {
                      setSelectedId(adjustment.id);
                      setActionError(null);
                    }}
                  >
                    <td>{adjustment.warehouse.code}</td>
                    <td>
                      <span className="mono">{adjustment.product.sku}</span> {adjustment.product.name}
                    </td>
                    <td className={`mono ${styles.money}`}>{adjustment.quantity}</td>
                    <td>{prettyLabel(adjustment.reason)}</td>
                    <td>{prettyLabel(adjustment.status)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : null}
        </div>

        <div className={styles.stack}>
          {selected ? (
            <DecisionPanel
              adjustment={selected}
              canApprove={canApprove}
              isRequester={selected.requester_id === user?.id}
              busy={decisionMutation.isPending}
              error={actionError}
              onDecision={(decision) => decisionMutation.mutate(decision)}
            />
          ) : null}

          {canCreate ? (
            <section className={styles.panel}>
              <h2 className={styles.panelTitle}>Create adjustment</h2>
              <form className={styles.form} onSubmit={onSubmit}>
                <label className={styles.label}>
                  Warehouse
                  <select className={styles.select} {...register("warehouse_id")}>
                    <option value="">Choose a warehouse</option>
                    {warehouseOptions.map((warehouse) => (
                      <option key={warehouse.id} value={warehouse.id}>
                        {warehouse.code} · {warehouse.name}
                      </option>
                    ))}
                  </select>
                  {errors.warehouse_id ? (
                    <span className={styles.fieldError}>{errors.warehouse_id.message}</span>
                  ) : null}
                </label>
                <label className={styles.label}>
                  Product
                  <select className={styles.select} {...register("product_id")}>
                    <option value="">Choose a product</option>
                    {products.map((product) => (
                      <option key={product.id} value={product.id}>
                        {product.sku} · {product.name}
                      </option>
                    ))}
                  </select>
                  {errors.product_id ? (
                    <span className={styles.fieldError}>{errors.product_id.message}</span>
                  ) : null}
                </label>
                <label className={styles.label}>
                  Quantity
                  <input className={`${styles.input} mono`} type="number" step={1} {...register("quantity")} />
                  {errors.quantity ? <span className={styles.fieldError}>{errors.quantity.message}</span> : null}
                </label>
                <label className={styles.label}>
                  Reason
                  <select className={styles.select} {...register("reason")}>
                    <option value="count">Count</option>
                    <option value="correction">Correction</option>
                    <option value="damage">Damage</option>
                  </select>
                </label>
                {formError ? <p className={styles.error}>{formError}</p> : null}
                <button className={styles.button} type="submit" disabled={isSubmitting || createMutation.isPending}>
                  {createMutation.isPending ? "Saving…" : "Save adjustment"}
                </button>
              </form>
            </section>
          ) : null}
        </div>
      </div>
    </div>
  );
}

function DecisionPanel({
  adjustment,
  canApprove,
  isRequester,
  busy,
  error,
  onDecision,
}: {
  adjustment: Adjustment;
  canApprove: boolean;
  isRequester: boolean;
  busy: boolean;
  error: string | null;
  onDecision: (decision: "approve" | "reject") => void;
}) {
  const pending = adjustment.status === "pending_approval";
  const showActions = pending && canApprove && !isRequester;

  return (
    <section className={styles.panel}>
      <h2 className={styles.panelTitle}>
        {adjustment.product.sku} · {prettyLabel(adjustment.status)}
      </h2>
      <div className={styles.detailGrid}>
        <div className={styles.detailRow}>
          <span className={styles.detailLabel}>Quantity</span>
          <span className="mono">{adjustment.quantity}</span>
        </div>
        <div className={styles.detailRow}>
          <span className={styles.detailLabel}>Reason</span>
          <span>{prettyLabel(adjustment.reason)}</span>
        </div>
        <div className={styles.detailRow}>
          <span className={styles.detailLabel}>Requester</span>
          <span>{adjustment.requester.name}</span>
        </div>
      </div>
      {pending && isRequester ? <p className={styles.muted}>Waiting for another approver.</p> : null}
      {showActions ? (
        <div className={styles.actions}>
          <button className={styles.button} type="button" disabled={busy} onClick={() => onDecision("approve")}>
            Approve
          </button>
          <button
            className={styles.buttonSecondary}
            type="button"
            disabled={busy}
            onClick={() => onDecision("reject")}
          >
            Reject
          </button>
        </div>
      ) : null}
      {error ? <p className={styles.error}>{error}</p> : null}
    </section>
  );
}
