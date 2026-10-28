import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { useFieldArray, useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  cancelTransfer,
  createTransfer,
  listProducts,
  listTransfers,
  listWarehouses,
  receiveTransfer,
  shipTransfer,
  type Transfer,
} from "../lib/api";
import { useAuth } from "../lib/auth";
import { prettyLabel } from "../lib/labels";
import styles from "./catalog.module.css";

const schema = z
  .object({
    source_warehouse_id: z.string().min(1, "Choose a source warehouse"),
    destination_warehouse_id: z.string().min(1, "Choose a destination warehouse"),
    lines: z
      .array(
        z.object({
          product_id: z.string().min(1, "Choose a product"),
          quantity: z.coerce.number().int().min(1, "Quantity must be at least 1"),
        }),
      )
      .min(1),
  })
  .refine((values) => values.source_warehouse_id !== values.destination_warehouse_id, {
    message: "Source and destination must differ",
    path: ["destination_warehouse_id"],
  });

type FormValues = z.infer<typeof schema>;

function canActAt(userWarehouseId: string | null | undefined, warehouseId: string): boolean {
  return userWarehouseId == null || userWarehouseId === warehouseId;
}

export function TransfersPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const pin = user?.warehouse_id ?? null;

  const warehousesQuery = useQuery({ queryKey: ["warehouses"], queryFn: listWarehouses });
  const productsQuery = useQuery({ queryKey: ["products"], queryFn: listProducts });
  const transfersQuery = useQuery({ queryKey: ["transfers"], queryFn: listTransfers });

  const {
    register,
    control,
    handleSubmit,
    reset,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      source_warehouse_id: pin ?? "",
      destination_warehouse_id: "",
      lines: [{ product_id: "", quantity: 1 }],
    },
  });

  const { fields, append, remove } = useFieldArray({ control, name: "lines" });
  const sourceId = watch("source_warehouse_id");

  const createMutation = useMutation({
    mutationFn: createTransfer,
    onSuccess: async (transfer) => {
      await queryClient.invalidateQueries({ queryKey: ["transfers"] });
      reset({
        source_warehouse_id: pin ?? "",
        destination_warehouse_id: "",
        lines: [{ product_id: "", quantity: 1 }],
      });
      setFormError(null);
      setSelectedId(transfer.id);
    },
    onError: (err: Error) => setFormError(err.message),
  });

  const actionMutation = useMutation({
    mutationFn: async (action: "ship" | "receive" | "cancel") => {
      if (!selectedId) {
        throw new Error("Select a transfer.");
      }
      if (action === "ship") {
        return shipTransfer(selectedId);
      }
      if (action === "receive") {
        return receiveTransfer(selectedId);
      }
      return cancelTransfer(selectedId);
    },
    onSuccess: async () => {
      setActionError(null);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["transfers"] }),
        queryClient.invalidateQueries({ queryKey: ["stock-balances"] }),
        queryClient.invalidateQueries({ queryKey: ["stock-lots"] }),
        queryClient.invalidateQueries({ queryKey: ["stock-movements"] }),
      ]);
    },
    onError: (err: Error) => setActionError(err.message),
  });

  const warehouses = warehousesQuery.data?.data ?? [];
  const sourceOptions = pin ? warehouses.filter((warehouse) => warehouse.id === pin) : warehouses;
  const products = (productsQuery.data?.data ?? []).filter((product) => product.track_stock);
  const transfers = transfersQuery.data?.data ?? [];
  const selected = transfers.find((transfer) => transfer.id === selectedId) ?? null;

  const onSubmit = handleSubmit((values) => {
    setFormError(null);
    createMutation.mutate({
      source_warehouse_id: values.source_warehouse_id,
      destination_warehouse_id: values.destination_warehouse_id,
      lines: values.lines.map((line) => ({
        product_id: line.product_id,
        quantity: line.quantity,
      })),
    });
  });

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Transfers</h1>
          <p className={styles.subtitle}>Draft a move, ship it, then receive it at the destination.</p>
        </div>
      </header>

      <div className={styles.split}>
        <div className={styles.tableWrap}>
          {transfersQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
          {transfersQuery.isError ? (
            <p className={styles.error}>{(transfersQuery.error as Error).message}</p>
          ) : null}
          {transfersQuery.data && transfers.length === 0 ? (
            <p className={styles.muted}>No transfers yet.</p>
          ) : null}
          {transfers.length > 0 ? (
            <table className={styles.table}>
              <thead>
                <tr>
                  <th>From</th>
                  <th>To</th>
                  <th>Status</th>
                  <th>Lines</th>
                </tr>
              </thead>
              <tbody>
                {transfers.map((transfer) => (
                  <tr
                    key={transfer.id}
                    className={[styles.clickable, selectedId === transfer.id ? styles.selected : ""]
                      .filter(Boolean)
                      .join(" ")}
                    onClick={() => {
                      setSelectedId(transfer.id);
                      setActionError(null);
                    }}
                  >
                    <td>{transfer.source_warehouse.code}</td>
                    <td>{transfer.destination_warehouse.code}</td>
                    <td>{prettyLabel(transfer.status)}</td>
                    <td className="mono">{transfer.lines.length}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : null}
        </div>

        <div className={styles.stack}>
          {selected ? (
            <TransferActions
              transfer={selected}
              pin={pin}
              busy={actionMutation.isPending}
              error={actionError}
              onAction={(action) => actionMutation.mutate(action)}
            />
          ) : null}

          <section className={styles.panel}>
            <h2 className={styles.panelTitle}>Create draft</h2>
            <form className={styles.form} onSubmit={onSubmit}>
              <label className={styles.label}>
                Source
                <select className={styles.select} {...register("source_warehouse_id")}>
                  <option value="">Choose a warehouse</option>
                  {sourceOptions.map((warehouse) => (
                    <option key={warehouse.id} value={warehouse.id}>
                      {warehouse.code} · {warehouse.name}
                    </option>
                  ))}
                </select>
                {errors.source_warehouse_id ? (
                  <span className={styles.fieldError}>{errors.source_warehouse_id.message}</span>
                ) : null}
              </label>
              <label className={styles.label}>
                Destination
                <select className={styles.select} {...register("destination_warehouse_id")}>
                  <option value="">Choose a warehouse</option>
                  {warehouses
                    .filter((warehouse) => warehouse.id !== sourceId)
                    .map((warehouse) => (
                      <option key={warehouse.id} value={warehouse.id}>
                        {warehouse.code} · {warehouse.name}
                      </option>
                    ))}
                </select>
                {errors.destination_warehouse_id ? (
                  <span className={styles.fieldError}>{errors.destination_warehouse_id.message}</span>
                ) : null}
              </label>

              {fields.map((field, index) => (
                <div className={styles.lineRow} key={field.id}>
                  <label className={styles.label}>
                    Product
                    <select className={styles.select} {...register(`lines.${index}.product_id`)}>
                      <option value="">Choose a product</option>
                      {products.map((product) => (
                        <option key={product.id} value={product.id}>
                          {product.sku} · {product.name}
                        </option>
                      ))}
                    </select>
                    {errors.lines?.[index]?.product_id ? (
                      <span className={styles.fieldError}>{errors.lines[index]?.product_id?.message}</span>
                    ) : null}
                  </label>
                  <label className={styles.label}>
                    Qty
                    <input
                      className={`${styles.input} mono`}
                      type="number"
                      step={1}
                      min={1}
                      {...register(`lines.${index}.quantity`)}
                    />
                    {errors.lines?.[index]?.quantity ? (
                      <span className={styles.fieldError}>{errors.lines[index]?.quantity?.message}</span>
                    ) : null}
                  </label>
                  {fields.length > 1 ? (
                    <button className={styles.buttonSecondary} type="button" onClick={() => remove(index)}>
                      Remove
                    </button>
                  ) : (
                    <span />
                  )}
                </div>
              ))}

              <button
                className={styles.buttonSecondary}
                type="button"
                onClick={() => append({ product_id: "", quantity: 1 })}
              >
                Add line
              </button>

              {formError ? <p className={styles.error}>{formError}</p> : null}
              <button className={styles.button} type="submit" disabled={isSubmitting || createMutation.isPending}>
                {createMutation.isPending ? "Saving…" : "Save draft"}
              </button>
            </form>
          </section>
        </div>
      </div>
    </div>
  );
}

function TransferActions({
  transfer,
  pin,
  busy,
  error,
  onAction,
}: {
  transfer: Transfer;
  pin: string | null;
  busy: boolean;
  error: string | null;
  onAction: (action: "ship" | "receive" | "cancel") => void;
}) {
  const canShip = transfer.status === "draft" && canActAt(pin, transfer.source_warehouse_id);
  const canCancel = transfer.status === "draft" && canActAt(pin, transfer.source_warehouse_id);
  const canReceive = transfer.status === "in_transit" && canActAt(pin, transfer.destination_warehouse_id);
  const blocked =
    (transfer.status === "draft" && !canShip) || (transfer.status === "in_transit" && !canReceive);

  return (
    <section className={styles.panel}>
      <h2 className={styles.panelTitle}>
        {transfer.source_warehouse.code} → {transfer.destination_warehouse.code}
      </h2>
      <div className={styles.detailGrid}>
        <div className={styles.detailRow}>
          <span className={styles.detailLabel}>Status</span>
          <span>{prettyLabel(transfer.status)}</span>
        </div>
        {transfer.lines.map((line) => (
          <div className={styles.detailRow} key={line.id}>
            <span className={styles.detailLabel}>{line.product.sku}</span>
            <span className="mono">{line.quantity}</span>
          </div>
        ))}
      </div>
      {blocked ? <p className={styles.muted}>This transfer is outside your pinned warehouse.</p> : null}
      <div className={styles.actions}>
        {canShip ? (
          <button className={styles.button} type="button" disabled={busy} onClick={() => onAction("ship")}>
            Ship
          </button>
        ) : null}
        {canReceive ? (
          <button className={styles.button} type="button" disabled={busy} onClick={() => onAction("receive")}>
            Receive
          </button>
        ) : null}
        {canCancel ? (
          <button
            className={styles.buttonSecondary}
            type="button"
            disabled={busy}
            onClick={() => onAction("cancel")}
          >
            Cancel
          </button>
        ) : null}
      </div>
      {error ? <p className={styles.error}>{error}</p> : null}
    </section>
  );
}
