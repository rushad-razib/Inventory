import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useFieldArray, useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  createGoodsReceipt,
  listGoodsReceipts,
  listProducts,
  listPurchaseOrders,
  listSupplierProducts,
  listSuppliers,
  listWarehouses,
  type GoodsReceipt,
} from "../lib/api";
import { useAuth } from "../lib/auth";
import { prettyLabel } from "../lib/labels";
import { formatTaka } from "../lib/money";
import { can } from "../lib/permissions";
import styles from "./catalog.module.css";

const consignSchema = z.object({
  supplier_id: z.string().min(1, "Choose a supplier"),
  warehouse_id: z.string().min(1, "Choose a warehouse"),
  lines: z
    .array(
      z.object({
        product_id: z.string().min(1, "Choose a product"),
        quantity: z.coerce.number().int().min(1, "Quantity must be at least 1"),
      }),
    )
    .min(1),
});

type ConsignValues = z.infer<typeof consignSchema>;

export function GoodsReceiptsPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [orderId, setOrderId] = useState("");
  const [quantities, setQuantities] = useState<Record<string, number>>({});
  const [orderError, setOrderError] = useState<string | null>(null);
  const [consignError, setConsignError] = useState<string | null>(null);
  const pin = user?.warehouse_id ?? null;
  const canReceive = can(user, "purchasing.receive");

  const receiptsQuery = useQuery({ queryKey: ["goods-receipts"], queryFn: listGoodsReceipts });
  const ordersQuery = useQuery({ queryKey: ["purchase-orders"], queryFn: listPurchaseOrders, enabled: canReceive });
  const suppliersQuery = useQuery({ queryKey: ["suppliers"], queryFn: listSuppliers, enabled: canReceive });
  const warehousesQuery = useQuery({ queryKey: ["warehouses"], queryFn: listWarehouses, enabled: canReceive });
  const productsQuery = useQuery({ queryKey: ["products"], queryFn: listProducts, enabled: canReceive });

  const receipts = receiptsQuery.data?.data ?? [];
  const selected = receipts.find((receipt) => receipt.id === selectedId) ?? null;
  const receivable = (ordersQuery.data?.data ?? []).filter((order) => {
    const open = order.status === "approved" || order.status === "partially_received";
    return open && (pin === null || order.destination_warehouse_id === pin);
  });
  const order = receivable.find((item) => item.id === orderId) ?? null;

  useEffect(() => {
    if (!order) {
      setQuantities({});
      return;
    }
    const next: Record<string, number> = {};
    for (const line of order.lines) {
      next[line.product_id] = Math.max(line.quantity_ordered - line.quantity_received, 0);
    }
    setQuantities(next);
  }, [order]);

  const refresh = async (receipt: GoodsReceipt) => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ["goods-receipts"] }),
      queryClient.invalidateQueries({ queryKey: ["purchase-orders"] }),
      queryClient.invalidateQueries({ queryKey: ["stock-balances"] }),
      queryClient.invalidateQueries({ queryKey: ["stock-lots"] }),
      queryClient.invalidateQueries({ queryKey: ["supplier-bills"] }),
    ]);
    setSelectedId(receipt.id);
  };

  const orderMutation = useMutation({
    mutationFn: async () => {
      if (!order) {
        throw new Error("Choose a purchase order.");
      }
      const lines = order.lines
        .map((line) => ({ product_id: line.product_id, quantity: quantities[line.product_id] ?? 0 }))
        .filter((line) => line.quantity >= 1);
      if (lines.length === 0) {
        throw new Error("Enter a quantity for at least one line.");
      }
      return createGoodsReceipt({ purchase_order_id: order.id, lines });
    },
    onSuccess: async (receipt) => {
      setOrderError(null);
      await refresh(receipt);
    },
    onError: (err: Error) => setOrderError(err.message),
  });

  const {
    register,
    control,
    handleSubmit,
    reset,
    watch,
    formState: { errors },
  } = useForm<ConsignValues>({
    resolver: zodResolver(consignSchema),
    defaultValues: {
      supplier_id: "",
      warehouse_id: pin ?? "",
      lines: [{ product_id: "", quantity: 1 }],
    },
  });
  const { fields, append, remove } = useFieldArray({ control, name: "lines" });
  const supplierId = watch("supplier_id");
  const costsQuery = useQuery({
    queryKey: ["supplier-products", supplierId],
    queryFn: () => listSupplierProducts(supplierId),
    enabled: supplierId !== "",
  });

  const consignMutation = useMutation({
    mutationFn: (values: ConsignValues) =>
      createGoodsReceipt({
        supplier_id: values.supplier_id,
        warehouse_id: values.warehouse_id,
        lines: values.lines.map((line) => ({ product_id: line.product_id, quantity: line.quantity })),
      }),
    onSuccess: async (receipt) => {
      setConsignError(null);
      reset({ supplier_id: "", warehouse_id: pin ?? "", lines: [{ product_id: "", quantity: 1 }] });
      await refresh(receipt);
    },
    onError: (err: Error) => setConsignError(err.message),
  });

  const suppliers = (suppliersQuery.data?.data ?? []).filter(
    (supplier) => supplier.supply_mode === "consignment" && supplier.status === "active",
  );
  const warehouses = (warehousesQuery.data?.data ?? []).filter((warehouse) => pin === null || warehouse.id === pin);
  const products = (productsQuery.data?.data ?? []).filter((product) => product.track_stock && product.is_active);
  const costs = new Map((costsQuery.data?.data ?? []).map((row) => [row.product_id, row.agreed_unit_cost]));

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Receipts</h1>
          <p className={styles.subtitle}>Receive part of an outright order, or post consignment stock with no order.</p>
        </div>
      </header>
      <div className={styles.split}>
        <div className={styles.tableWrap}>
          {receiptsQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
          {receiptsQuery.isError ? <p className={styles.error}>{(receiptsQuery.error as Error).message}</p> : null}
          {receiptsQuery.data && receipts.length === 0 ? <p className={styles.muted}>No receipts yet.</p> : null}
          {receipts.length > 0 ? (
            <table className={styles.table}>
              <thead>
                <tr>
                  <th>When</th>
                  <th>Supplier</th>
                  <th>Warehouse</th>
                  <th>Order</th>
                </tr>
              </thead>
              <tbody>
                {receipts.map((receipt) => (
                  <tr
                    key={receipt.id}
                    className={[styles.clickable, selectedId === receipt.id ? styles.selected : ""].filter(Boolean).join(" ")}
                    onClick={() => setSelectedId(receipt.id)}
                  >
                    <td className="mono">{receipt.created_at ? new Date(receipt.created_at).toLocaleString() : "—"}</td>
                    <td>{receipt.supplier.code}</td>
                    <td>{receipt.warehouse.code}</td>
                    <td className="mono">{receipt.purchase_order?.number ?? "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : null}
        </div>
        <div className={styles.stack}>
          {selected ? <ReceiptDetail receipt={selected} /> : null}
          {canReceive ? (
            <section className={styles.panel}>
              <h2 className={styles.panelTitle}>Receive a purchase order</h2>
              <div className={styles.form}>
                <label className={styles.label}>
                  Order
                  <select className={styles.select} value={orderId} onChange={(event) => setOrderId(event.target.value)}>
                    <option value="">Choose an order</option>
                    {receivable.map((item) => (
                      <option key={item.id} value={item.id}>
                        {item.number} · {item.destination_warehouse.code} · {prettyLabel(item.status)}
                      </option>
                    ))}
                  </select>
                </label>
                {order?.lines.map((line) => {
                  const remaining = line.quantity_ordered - line.quantity_received;
                  return (
                    <label className={styles.label} key={line.id}>
                      {line.product.sku} · {remaining} remaining · {formatTaka(line.unit_cost)}
                      <input
                        className={`${styles.input} mono`}
                        type="number"
                        min={0}
                        max={remaining}
                        step={1}
                        value={quantities[line.product_id] ?? 0}
                        onChange={(event) =>
                          setQuantities((current) => ({
                            ...current,
                            [line.product_id]: Number(event.target.value),
                          }))
                        }
                      />
                    </label>
                  );
                })}
                {orderError ? <p className={styles.error}>{orderError}</p> : null}
                <button
                  className={styles.button}
                  type="button"
                  disabled={!order || orderMutation.isPending}
                  onClick={() => orderMutation.mutate()}
                >
                  {orderMutation.isPending ? "Receiving…" : "Receive"}
                </button>
              </div>
            </section>
          ) : null}
          {canReceive ? (
            <section className={styles.panel}>
              <h2 className={styles.panelTitle}>Consignment receipt</h2>
              <form className={styles.form} onSubmit={handleSubmit((values) => consignMutation.mutate(values))}>
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
                  <select className={styles.select} {...register("warehouse_id")}>
                    <option value="">Choose a warehouse</option>
                    {warehouses.map((warehouse) => (
                      <option key={warehouse.id} value={warehouse.id}>
                        {warehouse.code} · {warehouse.name}
                      </option>
                    ))}
                  </select>
                  {errors.warehouse_id ? <span className={styles.fieldError}>{errors.warehouse_id.message}</span> : null}
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
                            </option>
                          ))}
                        </select>
                        {cost !== undefined ? <span className={styles.muted}>Agreed cost {formatTaka(cost)}</span> : null}
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
                {consignError ? <p className={styles.error}>{consignError}</p> : null}
                <button className={styles.button} type="submit" disabled={consignMutation.isPending}>
                  {consignMutation.isPending ? "Posting…" : "Post consignment"}
                </button>
              </form>
            </section>
          ) : null}
        </div>
      </div>
    </div>
  );
}

function ReceiptDetail({ receipt }: { receipt: GoodsReceipt }) {
  return (
    <section className={styles.panel}>
      <h2 className={styles.panelTitle}>
        {receipt.supplier.code} · {receipt.warehouse.code}
      </h2>
      <div className={styles.detailGrid}>
        {receipt.lots.map((lot) => (
          <div className={styles.detailRow} key={lot.id}>
            <span className={styles.detailLabel}>{prettyLabel(lot.ownership)}</span>
            <span className="mono">
              {lot.qty_on_hand} · {formatTaka(lot.unit_cost)}
            </span>
          </div>
        ))}
      </div>
      {receipt.bill ? (
        <p className={styles.muted}>
          Open bill {formatTaka(receipt.bill.amount)}, due {receipt.bill.due_on ?? "—"}.
        </p>
      ) : (
        <p className={styles.muted}>This consignment receipt has no bill.</p>
      )}
    </section>
  );
}
