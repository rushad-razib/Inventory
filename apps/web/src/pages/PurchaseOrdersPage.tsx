import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useFieldArray, useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  approvePurchaseOrder,
  cancelPurchaseOrder,
  createPurchaseOrder,
  listProducts,
  listPurchaseOrders,
  listSupplierProducts,
  listSuppliers,
  listWarehouses,
  rejectPurchaseOrder,
  submitPurchaseOrder,
  updatePurchaseOrder,
  type PurchaseOrder,
} from "../lib/api";
import { useAuth } from "../lib/auth";
import { prettyLabel } from "../lib/labels";
import { formatTaka } from "../lib/money";
import { can } from "../lib/permissions";
import styles from "./catalog.module.css";

const schema = z.object({
  supplier_id: z.string().min(1, "Choose a supplier"),
  destination_warehouse_id: z.string().min(1, "Choose a warehouse"),
  expected_on: z.string(),
  notes: z.string(),
  lines: z
    .array(
      z.object({
        product_id: z.string().min(1, "Choose a product"),
        quantity: z.coerce.number().int().min(1, "Quantity must be at least 1"),
      }),
    )
    .min(1),
});

type FormValues = z.infer<typeof schema>;

const emptyForm: FormValues = {
  supplier_id: "",
  destination_warehouse_id: "",
  expected_on: "",
  notes: "",
  lines: [{ product_id: "", quantity: 1 }],
};

export function PurchaseOrdersPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const canCreate = can(user, "purchasing.create");
  const canApprove = can(user, "purchasing.approve");

  const ordersQuery = useQuery({ queryKey: ["purchase-orders"], queryFn: listPurchaseOrders });
  const suppliersQuery = useQuery({ queryKey: ["suppliers"], queryFn: listSuppliers });
  const warehousesQuery = useQuery({ queryKey: ["warehouses"], queryFn: listWarehouses });
  const productsQuery = useQuery({ queryKey: ["products"], queryFn: listProducts });

  const {
    register,
    control,
    handleSubmit,
    reset,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: emptyForm,
  });
  const { fields, append, remove } = useFieldArray({ control, name: "lines" });
  const supplierId = watch("supplier_id");

  const costsQuery = useQuery({
    queryKey: ["supplier-products", supplierId],
    queryFn: () => listSupplierProducts(supplierId),
    enabled: supplierId !== "",
  });

  const orders = ordersQuery.data?.data ?? [];
  const selected = orders.find((order) => order.id === selectedId) ?? null;
  const editing = selected?.status === "draft" && canCreate;

  useEffect(() => {
    if (!selected || !canCreate) {
      return;
    }
    if (selected.status !== "draft") {
      reset(emptyForm);
      return;
    }
    reset({
      supplier_id: selected.supplier_id,
      destination_warehouse_id: selected.destination_warehouse_id,
      expected_on: selected.expected_on ?? "",
      notes: selected.notes ?? "",
      lines: selected.lines.map((line) => ({
        product_id: line.product_id,
        quantity: line.quantity_ordered,
      })),
    });
  }, [canCreate, reset, selected]);

  const refresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ["purchase-orders"] });
  };

  const saveMutation = useMutation({
    mutationFn: async (values: FormValues) => {
      const body = {
        supplier_id: values.supplier_id,
        destination_warehouse_id: values.destination_warehouse_id,
        expected_on: values.expected_on === "" ? null : values.expected_on,
        notes: values.notes === "" ? null : values.notes,
        lines: values.lines.map((line) => ({
          product_id: line.product_id,
          quantity: line.quantity,
        })),
      };
      if (editing && selected) {
        return updatePurchaseOrder(selected.id, body);
      }
      return createPurchaseOrder(body);
    },
    onSuccess: async (order) => {
      await refresh();
      setFormError(null);
      setSelectedId(order.id);
    },
    onError: (err: Error) => setFormError(err.message),
  });

  const actionMutation = useMutation({
    mutationFn: async (action: "submit" | "approve" | "reject" | "cancel") => {
      if (!selected) {
        throw new Error("Select a purchase order.");
      }
      if (action === "submit") {
        return submitPurchaseOrder(selected.id);
      }
      if (action === "approve") {
        return approvePurchaseOrder(selected.id);
      }
      if (action === "reject") {
        return rejectPurchaseOrder(selected.id);
      }
      return cancelPurchaseOrder(selected.id);
    },
    onSuccess: async () => {
      setActionError(null);
      await refresh();
    },
    onError: (err: Error) => setActionError(err.message),
  });

  const suppliers = (suppliersQuery.data?.data ?? []).filter(
    (supplier) => supplier.supply_mode === "outright" && supplier.status === "active",
  );
  const products = (productsQuery.data?.data ?? []).filter((product) => product.track_stock && product.is_active);
  const costs = new Map((costsQuery.data?.data ?? []).map((row) => [row.product_id, row.agreed_unit_cost]));

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Purchase orders</h1>
          <p className={styles.subtitle}>Draft an outright order, then submit it. Stock moves only when it is received.</p>
        </div>
      </header>

      <div className={styles.split}>
        <div className={styles.tableWrap}>
          {ordersQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
          {ordersQuery.isError ? <p className={styles.error}>{(ordersQuery.error as Error).message}</p> : null}
          {ordersQuery.data && orders.length === 0 ? <p className={styles.muted}>No purchase orders yet.</p> : null}
          {orders.length > 0 ? (
            <table className={styles.table}>
              <thead>
                <tr>
                  <th>Number</th>
                  <th>Supplier</th>
                  <th>Warehouse</th>
                  <th>Status</th>
                  <th className={styles.money}>Total</th>
                </tr>
              </thead>
              <tbody>
                {orders.map((order) => (
                  <tr
                    key={order.id}
                    className={[styles.clickable, selectedId === order.id ? styles.selected : ""].filter(Boolean).join(" ")}
                    onClick={() => {
                      setSelectedId(order.id);
                      setActionError(null);
                    }}
                  >
                    <td className="mono">{order.number}</td>
                    <td>{order.supplier.code}</td>
                    <td>{order.destination_warehouse.code}</td>
                    <td>{prettyLabel(order.status)}</td>
                    <td className={`mono ${styles.money}`}>{formatTaka(order.total)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : null}
        </div>

        <div className={styles.stack}>
          {selected ? (
            <OrderPanel
              order={selected}
              canCreate={canCreate}
              canApprove={canApprove}
              isRequester={selected.requester_id === user?.id}
              busy={actionMutation.isPending}
              error={actionError}
              onAction={(action) => actionMutation.mutate(action)}
            />
          ) : null}

          {canCreate ? (
            <section className={styles.panel}>
              <div className={styles.header}>
                <h2 className={styles.panelTitle}>{editing ? `Edit ${selected?.number}` : "Create draft"}</h2>
                {editing ? (
                  <button
                    className={styles.buttonSecondary}
                    type="button"
                    onClick={() => {
                      setSelectedId(null);
                      reset(emptyForm);
                    }}
                  >
                    New draft
                  </button>
                ) : null}
              </div>
              <form className={styles.form} onSubmit={handleSubmit((values) => saveMutation.mutate(values))}>
                <label className={styles.label}>
                  Supplier
                  <select className={styles.select} {...register("supplier_id")}>
                    <option value="">Choose a supplier</option>
                    {suppliers.map((supplier) => (
                      <option key={supplier.id} value={supplier.id}>
                        {supplier.code} · {supplier.name}
                      </option>
                    ))}
                  </select>
                  {errors.supplier_id ? <span className={styles.fieldError}>{errors.supplier_id.message}</span> : null}
                </label>
                <label className={styles.label}>
                  Warehouse
                  <select className={styles.select} {...register("destination_warehouse_id")}>
                    <option value="">Choose a warehouse</option>
                    {(warehousesQuery.data?.data ?? []).map((warehouse) => (
                      <option key={warehouse.id} value={warehouse.id}>
                        {warehouse.code} · {warehouse.name}
                      </option>
                    ))}
                  </select>
                  {errors.destination_warehouse_id ? (
                    <span className={styles.fieldError}>{errors.destination_warehouse_id.message}</span>
                  ) : null}
                </label>
                <label className={styles.label}>
                  Expected
                  <input className={styles.input} type="date" {...register("expected_on")} />
                </label>
                <label className={styles.label}>
                  Notes
                  <input className={styles.input} {...register("notes")} />
                </label>
                {fields.map((field, index) => {
                  const productId = watch(`lines.${index}.product_id`);
                  const cost = costs.get(productId);
                  return (
                    <div className={styles.lineRow} key={field.id}>
                      <label className={styles.label}>
                        Product
                        <select className={styles.select} {...register(`lines.${index}.product_id`)}>
                          <option value="">Choose a product</option>
                          {products.map((product) => (
                            <option key={product.id} value={product.id}>
                              {product.sku} · {product.name}
                              {costs.has(product.id) ? ` · ${formatTaka(costs.get(product.id) ?? 0)}` : ""}
                            </option>
                          ))}
                        </select>
                        {cost !== undefined ? (
                          <span className={styles.muted}>Agreed cost {formatTaka(cost)}</span>
                        ) : null}
                        {errors.lines?.[index]?.product_id ? (
                          <span className={styles.fieldError}>{errors.lines[index]?.product_id?.message}</span>
                        ) : null}
                      </label>
                      <label className={styles.label}>
                        Qty
                        <input className={`${styles.input} mono`} type="number" min={1} step={1} {...register(`lines.${index}.quantity`)} />
                      </label>
                      {fields.length > 1 ? (
                        <button className={styles.buttonSecondary} type="button" onClick={() => remove(index)}>
                          Remove
                        </button>
                      ) : (
                        <span />
                      )}
                    </div>
                  );
                })}
                <button className={styles.buttonSecondary} type="button" onClick={() => append({ product_id: "", quantity: 1 })}>
                  Add line
                </button>
                {formError ? <p className={styles.error}>{formError}</p> : null}
                <button className={styles.button} type="submit" disabled={isSubmitting || saveMutation.isPending}>
                  {saveMutation.isPending ? "Saving…" : "Save draft"}
                </button>
              </form>
            </section>
          ) : null}
        </div>
      </div>
    </div>
  );
}

function OrderPanel({
  order,
  canCreate,
  canApprove,
  isRequester,
  busy,
  error,
  onAction,
}: {
  order: PurchaseOrder;
  canCreate: boolean;
  canApprove: boolean;
  isRequester: boolean;
  busy: boolean;
  error: string | null;
  onAction: (action: "submit" | "approve" | "reject" | "cancel") => void;
}) {
  const canSubmit = canCreate && order.status === "draft";
  const canCancel =
    canCreate && (order.status === "draft" || order.status === "pending_approval" || order.status === "approved");
  const canDecide = canApprove && !isRequester && order.status === "pending_approval";

  return (
    <section className={styles.panel}>
      <h2 className={styles.panelTitle}>
        {order.number} · {prettyLabel(order.status)}
      </h2>
      <div className={styles.detailGrid}>
        <div className={styles.detailRow}>
          <span className={styles.detailLabel}>Requester</span>
          <span>{order.requester.name}</span>
        </div>
        {order.lines.map((line) => (
          <div className={styles.detailRow} key={line.id}>
            <span className={styles.detailLabel}>{line.product.sku}</span>
            <span className="mono">
              {line.quantity_received}/{line.quantity_ordered} · {formatTaka(line.unit_cost)}
            </span>
          </div>
        ))}
      </div>
      <div className={styles.actions}>
        {canSubmit ? (
          <button className={styles.button} type="button" disabled={busy} onClick={() => onAction("submit")}>
            Submit
          </button>
        ) : null}
        {canDecide ? (
          <>
            <button className={styles.button} type="button" disabled={busy} onClick={() => onAction("approve")}>
              Approve
            </button>
            <button className={styles.buttonSecondary} type="button" disabled={busy} onClick={() => onAction("reject")}>
              Reject
            </button>
          </>
        ) : null}
        {canCancel ? (
          <button className={styles.buttonSecondary} type="button" disabled={busy} onClick={() => onAction("cancel")}>
            Cancel
          </button>
        ) : null}
      </div>
      {order.status === "pending_approval" && isRequester ? (
        <p className={styles.muted}>Waiting for another approver.</p>
      ) : null}
      {error ? <p className={styles.error}>{error}</p> : null}
    </section>
  );
}
