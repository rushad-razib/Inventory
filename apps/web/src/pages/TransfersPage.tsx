import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useFieldArray, useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  cancelTransfer,
  createTransfer,
  listAllStockBalances,
  listTransfers,
  listWarehouses,
  receiveTransfer,
  shipTransfer,
  type Transfer,
} from "../lib/api";
import { useAuth } from "../lib/auth";
import { prettyLabel } from "../lib/labels";
import { EmptyState } from "./EmptyState";
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
  const [mode, setMode] = useState<"send" | "request">("send");

  const warehousesQuery = useQuery({ queryKey: ["warehouses"], queryFn: listWarehouses });
  const transfersQuery = useQuery({ queryKey: ["transfers"], queryFn: listTransfers });

  const {
    register,
    control,
    handleSubmit,
    reset,
    watch,
    getValues,
    setValue,
    setError,
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

  const warehouseRows = warehousesQuery.data?.data;

  useEffect(() => {
    if (pin == null || warehouseRows == null) {
      return;
    }
    if (mode === "send") {
      setValue("source_warehouse_id", pin);
      if (getValues("destination_warehouse_id") === pin) {
        setValue("destination_warehouse_id", "");
      }
      return;
    }
    setValue("destination_warehouse_id", pin);
    if (getValues("source_warehouse_id") === pin) {
      setValue("source_warehouse_id", "");
    }
  }, [mode, pin, warehouseRows, getValues, setValue]);

  const sourceStockQuery = useQuery({
    queryKey: ["stock-balances", "transfer-source", sourceId],
    queryFn: () => listAllStockBalances(sourceId),
    enabled: sourceId !== "",
  });

  const availableProducts = (sourceStockQuery.data ?? [])
    .filter((balance) => balance.on_hand > 0)
    .sort((a, b) => a.product.sku.localeCompare(b.product.sku));

  useEffect(() => {
    if (sourceId === "") {
      getValues("lines").forEach((line, index) => {
        if (line.product_id) {
          setValue(`lines.${index}.product_id`, "");
        }
      });
      return;
    }

    if (!sourceStockQuery.isSuccess) {
      return;
    }

    const availableIds = new Set(
      sourceStockQuery.data.filter((balance) => balance.on_hand > 0).map((balance) => balance.product_id),
    );
    getValues("lines").forEach((line, index) => {
      if (line.product_id && !availableIds.has(line.product_id)) {
        setValue(`lines.${index}.product_id`, "");
      }
    });
  }, [sourceId, sourceStockQuery.isSuccess, sourceStockQuery.data, getValues, setValue]);

  const createMutation = useMutation({
    mutationFn: createTransfer,
    onSuccess: async (transfer) => {
      await queryClient.invalidateQueries({ queryKey: ["transfers"] });
      reset({
        source_warehouse_id: pin != null && mode === "send" ? pin : "",
        destination_warehouse_id: pin != null && mode === "request" ? pin : "",
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
  const sourceOptions =
    pin == null ? warehouses : mode === "send" ? warehouses.filter((warehouse) => warehouse.id === pin) : warehouses.filter((warehouse) => warehouse.id !== pin);
  const destinationOptions =
    pin != null && mode === "request"
      ? warehouses.filter((warehouse) => warehouse.id === pin)
      : warehouses.filter((warehouse) => warehouse.id !== sourceId);
  const transfers = transfersQuery.data?.data ?? [];
  const selected = transfers.find((transfer) => transfer.id === selectedId) ?? null;

  const onSubmit = handleSubmit((values) => {
    setFormError(null);

    if (!sourceStockQuery.isSuccess || sourceStockQuery.isFetching) {
      setFormError("Wait for the source warehouse stock to load.");
      return;
    }

    const onHandByProduct = new Map(
      sourceStockQuery.data.map((balance) => [balance.product_id, balance.on_hand]),
    );
    const requested = new Map<string, number>();
    for (const line of values.lines) {
      requested.set(line.product_id, (requested.get(line.product_id) ?? 0) + line.quantity);
    }

    let overStock = false;
    values.lines.forEach((line, index) => {
      const onHand = onHandByProduct.get(line.product_id) ?? 0;
      const total = requested.get(line.product_id) ?? line.quantity;
      if (total > onHand) {
        setError(`lines.${index}.quantity`, {
          type: "validate",
          message: `Only ${onHand} on hand at the source`,
        });
        overStock = true;
      }
    });
    if (overStock) {
      return;
    }

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
          {transfersQuery.data && transfers.length === 0 ? <EmptyState>No transfers yet.</EmptyState> : null}
          {transfers.length > 0 ? (
            <table className={styles.table}>
              <thead>
                <tr>
                  <th>From</th>
                  <th>To</th>
                  <th>Product</th>
                  <th className={styles.money}>Quantity</th>
                  <th>Status</th>
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
                    <td>{transfer.source_warehouse.name}</td>
                    <td>{transfer.destination_warehouse.name}</td>
                    <td>
                      {transfer.lines.map((line) => (
                        <div key={line.id}>{line.product.name}</div>
                      ))}
                    </td>
                    <td className={styles.money}>
                      {transfer.lines.map((line) => (
                        <div key={line.id} className="mono">
                          {line.quantity}
                        </div>
                      ))}
                    </td>
                    <td>{prettyLabel(transfer.status)}</td>
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
            <h2 className={styles.panelTitle}>{pin != null && mode === "request" ? "Request stock" : "Create draft"}</h2>
            {pin != null ? (
              <div className={styles.actions}>
                <button
                  className={mode === "send" ? styles.button : styles.buttonSecondary}
                  type="button"
                  onClick={() => setMode("send")}
                >
                  Send stock
                </button>
                <button
                  className={mode === "request" ? styles.button : styles.buttonSecondary}
                  type="button"
                  onClick={() => setMode("request")}
                >
                  Request stock
                </button>
              </div>
            ) : null}
            <form className={styles.form} onSubmit={onSubmit}>
              <label className={styles.label}>
                Source
                <select className={styles.select} {...register("source_warehouse_id")}>
                  {pin != null && mode === "send" ? null : <option value="">Choose a warehouse</option>}
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
                  {pin != null && mode === "request" ? null : <option value="">Choose a warehouse</option>}
                  {destinationOptions.map((warehouse) => (
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
                    <select
                      className={styles.select}
                      {...register(`lines.${index}.product_id`)}
                      disabled={sourceId === "" || sourceStockQuery.isLoading}
                    >
                      <option value="">
                        {sourceId === "" ? "Choose a source first" : "Choose a product"}
                      </option>
                      {availableProducts.map((balance) => (
                        <option key={balance.product_id} value={balance.product_id}>
                          {balance.product.sku} · {balance.product.name}
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
                {createMutation.isPending ? "Saving…" : mode === "request" && pin != null ? "Request draft" : "Save draft"}
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
  const involved =
    pin == null || pin === transfer.source_warehouse_id || pin === transfer.destination_warehouse_id;
  const canShip = transfer.status === "draft" && canActAt(pin, transfer.source_warehouse_id);
  const canCancel = transfer.status === "draft" && involved;
  const canReceive = transfer.status === "in_transit" && canActAt(pin, transfer.destination_warehouse_id);
  const blocked = transfer.status === "in_transit" && !canReceive;

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
      {blocked ? (
        <p className={styles.muted}>Waiting for the destination warehouse to receive this transfer.</p>
      ) : null}
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
