import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import {
  closeDrawer,
  createCustomer,
  createSale,
  getCurrentDrawer,
  listAllProducts,
  listAllStockBalances,
  listWarehouses,
  lookupCustomer,
  lookupProduct,
  openDrawer,
  type Customer,
  type Product,
  type Sale,
} from "../lib/api";
import { useAuth } from "../lib/auth";
import { brandingLabel, useBranding } from "../lib/branding";
import { prettyLabel } from "../lib/labels";
import { cartShelfTotal, changeDue, formatTaka } from "../lib/money";
import styles from "./catalog.module.css";

type CartLine = { product: Product; quantity: number };

export function PosPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const pin = user?.warehouse_id ?? null;
  const [warehouseId, setWarehouseId] = useState(pin ?? "");
  const [barcode, setBarcode] = useState("");
  const [mobile, setMobile] = useState("");
  const [customer, setCustomer] = useState<Customer | null>(null);
  const [cart, setCart] = useState<CartLine[]>([]);
  const [pointsInput, setPointsInput] = useState("");
  const [cardInput, setCardInput] = useState("");
  const [cashInput, setCashInput] = useState("");
  const points = pointsInput === "" ? 0 : Number(pointsInput);
  const card = cardInput === "" ? 0 : Number(cardInput);
  const cashReceived = cashInput === "" ? 0 : Number(cashInput);
  const [posted, setPosted] = useState<Sale | null>(null);
  const [receiptChange, setReceiptChange] = useState(0);
  const { data: branding } = useBranding();
  const [error, setError] = useState<string | null>(null);
  const [openingBalance, setOpeningBalance] = useState("");
  const [counted, setCounted] = useState("");

  const drawerQuery = useQuery({ queryKey: ["drawer", "current"], queryFn: getCurrentDrawer });
  const drawer = drawerQuery.data ?? null;
  const warehousesQuery = useQuery({
    queryKey: ["warehouses"],
    queryFn: () => listWarehouses(),
    enabled: pin === null && drawer === null,
  });
  const stockQuery = useQuery({
    queryKey: ["stock-balances", "till", drawer?.warehouse_id],
    queryFn: () => listAllStockBalances(drawer!.warehouse_id),
    enabled: drawer != null,
  });
  const productsQuery = useQuery({
    queryKey: ["products", "till"],
    queryFn: listAllProducts,
    enabled: drawer != null,
  });
  const onHandByProduct = new Map(
    (stockQuery.data ?? []).filter((balance) => balance.on_hand > 0).map((balance) => [balance.product_id, balance.on_hand]),
  );
  const products = (productsQuery.data ?? [])
    .filter((product) => product.track_stock && product.is_active && (onHandByProduct.get(product.id) ?? 0) > 0)
    .sort((a, b) => a.sku.localeCompare(b.sku));
  const shelf = cartShelfTotal(cart.map((line) => ({ unitPrice: line.product.sell_price, quantity: line.quantity })));
  const pointCap = Math.min(customer?.points_balance ?? 0, shelf);
  const appliedPoints = customer ? Math.min(Math.max(points, 0), pointCap) : 0;
  const cashDue = Math.max(shelf - appliedPoints - card, 0);

  const addProduct = (product: Product) => {
    if (drawer != null && (stockQuery.isLoading || !stockQuery.isSuccess)) {
      setError("Wait for warehouse stock to load.");
      return;
    }
    const onHand = onHandByProduct.get(product.id) ?? 0;
    if (!product.track_stock || !product.is_active || onHand < 1) {
      setError("That product is not on hand at this warehouse.");
      return;
    }
    const existing = cart.find((line) => line.product.id === product.id);
    if ((existing?.quantity ?? 0) + 1 > onHand) {
      setError(`Only ${onHand} on hand`);
      return;
    }
    setError(null);
    setCart((current) => {
      const line = current.find((item) => item.product.id === product.id);
      if (!line) {
        return [...current, { product, quantity: 1 }];
      }
      return current.map((item) =>
        item.product.id === product.id ? { ...item, quantity: item.quantity + 1 } : item,
      );
    });
  };

  const barcodeMutation = useMutation({
    mutationFn: lookupProduct,
    onSuccess: (product) => {
      if (!product) {
        setError("No product has that barcode.");
        return;
      }
      addProduct(product);
      setBarcode("");
    },
    onError: (err: Error) => setError(err.message),
  });

  const customerMutation = useMutation({
    mutationFn: lookupCustomer,
    onSuccess: (found) => {
      setError(null);
      setCustomer(found);
      setPointsInput("");
      if (!found) {
        setError("No customer has that mobile. Register them to earn points.");
      }
    },
    onError: (err: Error) => setError(err.message),
  });

  const registerMutation = useMutation({
    mutationFn: () => createCustomer({ mobile, name: null }),
    onSuccess: (created) => {
      setError(null);
      setCustomer(created);
    },
    onError: (err: Error) => setError(err.message),
  });

  const saleMutation = useMutation({
    mutationFn: async () => {
      const tenders: { type: "cash" | "card" | "points"; amount: number }[] = [];
      if (appliedPoints > 0) {
        tenders.push({ type: "points", amount: appliedPoints });
      }
      if (card > 0) {
        tenders.push({ type: "card", amount: card });
      }
      if (cashDue > 0) {
        tenders.push({ type: "cash", amount: cashDue });
      }
      if (!drawer) {
        throw new Error("Open a drawer before posting a sale.");
      }
      const change = changeDue(cashReceived, cashDue);
      const sale = await createSale({
        warehouse_id: drawer.warehouse_id,
        customer_id: customer?.id ?? null,
        lines: cart.map((line) => ({ product_id: line.product.id, quantity: line.quantity })),
        tenders,
      });
      return { sale, change };
    },
    onSuccess: async ({ sale, change }) => {
      setPosted(sale);
      setReceiptChange(change);
      setError(null);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["sales"] }),
        queryClient.invalidateQueries({ queryKey: ["stock-balances"] }),
        queryClient.invalidateQueries({ queryKey: ["supplier-bills"] }),
        queryClient.invalidateQueries({ queryKey: ["drawer", "current"] }),
      ]);
    },
    onError: (err: Error) => setError(err.message),
  });

  const openMutation = useMutation({
    mutationFn: () =>
      openDrawer({
        warehouse_id: pin ?? warehouseId,
        opening_balance: Number(openingBalance),
      }),
    onSuccess: async () => {
      setOpeningBalance("");
      setError(null);
      await queryClient.invalidateQueries({ queryKey: ["drawer", "current"] });
    },
    onError: (err: Error) => setError(err.message),
  });

  const closeMutation = useMutation({
    mutationFn: () => closeDrawer(drawer?.id ?? "", Number(counted)),
    onSuccess: async () => {
      setCounted("");
      setCart([]);
      setError(null);
      await queryClient.invalidateQueries({ queryKey: ["drawer", "current"] });
    },
    onError: (err: Error) => setError(err.message),
  });

  const countedAmount = counted === "" ? null : Number(counted);
  const difference = drawer && countedAmount !== null ? countedAmount - drawer.balance : null;

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Till</h1>
          <p className={styles.subtitle}>Open a drawer, then scan a barcode and take cash, card, or points.</p>
        </div>
      </header>

      {drawerQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
      {drawerQuery.isError ? <p className={styles.error}>{(drawerQuery.error as Error).message}</p> : null}

      {drawerQuery.isSuccess && drawer === null ? (
        <section className={styles.panel}>
          <h2 className={styles.panelTitle}>Open drawer</h2>
          <p className={styles.muted}>The opening balance is taken from main cash and becomes the float for change.</p>
          <form
            className={styles.form}
            onSubmit={(event) => {
              event.preventDefault();
              openMutation.mutate();
            }}
          >
            {pin === null ? (
              <label className={styles.label}>
                Warehouse
                <select className={styles.select} value={warehouseId} onChange={(event) => setWarehouseId(event.target.value)}>
                  <option value="">Choose a warehouse</option>
                  {(warehousesQuery.data?.data ?? []).map((warehouse) => (
                    <option key={warehouse.id} value={warehouse.id}>
                      {warehouse.code} · {warehouse.name}
                    </option>
                  ))}
                </select>
              </label>
            ) : (
              <p className={styles.muted}>Opening at your pinned warehouse.</p>
            )}
            <label className={styles.label}>
              Opening balance
              <input
                className={`mono ${styles.input}`}
                type="number"
                min={1}
                value={openingBalance}
                onChange={(event) => setOpeningBalance(event.target.value)}
              />
            </label>
            {error ? <p className={styles.error}>{error}</p> : null}
            <button
              className={styles.button}
              type="submit"
              disabled={openMutation.isPending || (pin ?? warehouseId) === "" || Number(openingBalance) < 1}
            >
              {openMutation.isPending ? "Opening…" : "Open drawer"}
            </button>
          </form>
        </section>
      ) : null}

      {drawer ? (
      <section className={styles.panel}>
        <h2 className={styles.panelTitle}>Drawer</h2>
        <div className={`mono ${styles.tillTotals}`}>
          <p>Float {formatTaka(drawer.opening_amount)}</p>
          <p>Expected {formatTaka(drawer.balance)}</p>
        </div>
        <form
          className={styles.form}
          onSubmit={(event) => {
            event.preventDefault();
            closeMutation.mutate();
          }}
        >
          <label className={styles.label}>
            Counted cash
            <input
              className={`mono ${styles.input}`}
              type="number"
              min={0}
              value={counted}
              onChange={(event) => setCounted(event.target.value)}
            />
          </label>
          {difference !== null ? <p className={`mono ${styles.tillTotals}`}>Difference {formatTaka(difference)}</p> : null}
          {error ? <p className={styles.error}>{error}</p> : null}
          <button className={styles.button} type="submit" disabled={closeMutation.isPending || countedAmount === null}>
            {closeMutation.isPending ? "Closing…" : "Close drawer"}
          </button>
        </form>
      </section>
      ) : null}

      {drawer ? (
      <div className={styles.till}>
      <div className={styles.stack}>
      <section className={styles.panel}>
        <form
          className={styles.form}
          onSubmit={(event) => {
            event.preventDefault();
            if (barcode.trim() !== "") {
              barcodeMutation.mutate(barcode.trim());
            }
          }}
        >
          <p className={styles.muted}>Selling from your open drawer.</p>
          <label className={styles.label}>
            Barcode
            <input className={`${styles.input} ${styles.barcodeInput}`} value={barcode} onChange={(event) => setBarcode(event.target.value)} autoFocus />
          </label>
          <label className={styles.label}>
            Or choose a product
            <select
              className={styles.select}
              value=""
              disabled={stockQuery.isLoading || productsQuery.isLoading}
              onChange={(event) => {
                const product = products.find((item) => item.id === event.target.value);
                if (product) {
                  addProduct(product);
                }
              }}
            >
              <option value="">
                {stockQuery.isLoading || productsQuery.isLoading
                  ? "Loading stock…"
                  : products.length === 0
                    ? "No products on hand"
                    : "Add a product"}
              </option>
              {products.map((product) => (
                <option key={product.id} value={product.id}>
                  {product.sku} · {product.name} · {onHandByProduct.get(product.id)} on hand · {formatTaka(product.sell_price)}
                </option>
              ))}
            </select>
          </label>
        </form>
      </section>

      <section className={styles.panel}>
        <h2 className={styles.panelTitle}>Customer</h2>
        <form
          className={styles.toolbar}
          onSubmit={(event) => {
            event.preventDefault();
            customerMutation.mutate(mobile);
          }}
        >
          <label className={styles.label}>
            Mobile
            <input className={styles.input} value={mobile} onChange={(event) => setMobile(event.target.value)} />
          </label>
          <button className={styles.buttonSecondary} type="submit">
            Look up
          </button>
          <button className={styles.buttonSecondary} type="button" onClick={() => { setCustomer(null); setPointsInput(""); }}>
            Walk-in
          </button>
        </form>
        {customer ? (
          <p className={styles.muted}>
            {customer.name ?? customer.mobile} · <span className="mono">{customer.points_balance}</span> points
          </p>
        ) : (
          <p className={styles.muted}>Walk-in. No points.</p>
        )}
        {customer === null && mobile.trim() !== "" ? (
          <button className={styles.buttonSecondary} type="button" onClick={() => registerMutation.mutate()}>
            Register this mobile
          </button>
        ) : null}
      </section>
      </div>

      <section className={styles.panel}>
        <h2 className={styles.panelTitle}>Cart</h2>
        {cart.length === 0 ? <p className={styles.muted}>The cart is empty.</p> : null}
        {cart.map((line) => (
          <div className={styles.cartLine} key={line.product.id}>
            <div className={styles.cartLineBody}>
              <p className={styles.cartName}>{line.product.name}</p>
              <p className={styles.cartMeta}>
                <span className="mono">{line.product.sku}</span>
                <span>Qty {line.quantity}</span>
                <span>{formatTaka(line.product.sell_price)} each</span>
                <span>{formatTaka(line.product.sell_price * line.quantity)}</span>
              </p>
            </div>
            <div className={styles.cartControls}>
              <input
                className={`mono ${styles.input}`}
                type="number"
                min={1}
                max={onHandByProduct.get(line.product.id) ?? line.quantity}
                value={line.quantity}
                onChange={(event) => {
                  const onHand = onHandByProduct.get(line.product.id) ?? line.quantity;
                  const quantity = Number(event.target.value);
                  if (quantity > onHand) {
                    setError(`Only ${onHand} on hand`);
                  } else {
                    setError(null);
                  }
                  setCart((current) =>
                    current.map((item) =>
                      item.product.id === line.product.id
                        ? { ...item, quantity: Math.min(Math.max(quantity, 1), onHand) }
                        : item,
                    ),
                  );
                }}
              />
              <button
                className={styles.buttonSecondary}
                type="button"
                onClick={() =>
                  setCart((current) => current.filter((item) => item.product.id !== line.product.id))
                }
              >
                Clear
              </button>
            </div>
          </div>
        ))}
        <p className={`mono ${styles.tillTotals}`}>Shelf {formatTaka(shelf)}</p>
        <div className={styles.toolbar}>
          <label className={styles.label}>
            Points
            <input
              className={`mono ${styles.input}`}
              type="text"
              inputMode="numeric"
              placeholder="0"
              value={pointsInput}
              disabled={customer === null}
              onChange={(event) => setPointsInput(event.target.value.replace(/\D/g, ""))}
            />
          </label>
          <label className={styles.label}>
            Card
            <input
              className={`mono ${styles.input}`}
              type="text"
              inputMode="numeric"
              placeholder="0"
              value={cardInput}
              onChange={(event) => setCardInput(event.target.value.replace(/\D/g, ""))}
            />
          </label>
          <label className={styles.label}>
            Cash received
            <input
              className={`mono ${styles.input}`}
              type="text"
              inputMode="numeric"
              placeholder="0"
              value={cashInput}
              onChange={(event) => setCashInput(event.target.value.replace(/\D/g, ""))}
            />
          </label>
        </div>
        <div className={`mono ${styles.tillTotals}`}>
          <p>Cash due {formatTaka(cashDue)}</p>
          <p className={styles.changeDue}>Change due {formatTaka(changeDue(cashReceived, cashDue))}</p>
        </div>
        {error ? <p className={styles.error}>{error}</p> : null}
        <button
          className={styles.button}
          type="button"
          disabled={
            saleMutation.isPending ||
            cart.length === 0 ||
            cashDue + card + appliedPoints !== shelf ||
            cashReceived < cashDue
          }
          onClick={() => saleMutation.mutate()}
        >
          {saleMutation.isPending ? "Posting…" : "Post sale"}
        </button>
      </section>
      </div>
      ) : null}

      {posted ? (
        <div className={styles.receiptModal} role="dialog" aria-modal="true" aria-labelledby="sale-receipt-title">
          <div className={styles.receiptDialog}>
            <section className={`${styles.panel} ${styles.saleReceipt}`}>
              <h2 className={styles.receiptStore} id="sale-receipt-title">
                {brandingLabel(branding)}
              </h2>
              <p className={styles.receiptMeta}>
                <span className="mono">{posted.number}</span>
                {posted.created_at ? ` · ${new Date(posted.created_at).toLocaleString()}` : ""}
              </p>
              <p className={styles.receiptMeta}>{posted.warehouse.name}</p>
              <table className={styles.table}>
                <thead>
                  <tr>
                    <th>Product</th>
                    <th className={styles.money}>Qty</th>
                    <th className={styles.money}>Price</th>
                    <th className={styles.money}>Total</th>
                  </tr>
                </thead>
                <tbody>
                  {posted.lines.map((line) => (
                    <tr key={line.id}>
                      <td>
                        {line.product.name}
                        <span className={`mono ${styles.receiptSku}`}> {line.product.sku}</span>
                      </td>
                      <td className={`mono ${styles.money}`}>{line.quantity}</td>
                      <td className={`mono ${styles.money}`}>{formatTaka(line.unit_price)}</td>
                      <td className={`mono ${styles.money}`}>{formatTaka(line.shelf_total)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
              <div className={styles.receiptTotals}>
                {posted.tenders.map((tender) => (
                  <p key={tender.id}>
                    {prettyLabel(tender.type)} <span className="mono">{formatTaka(tender.amount)}</span>
                  </p>
                ))}
                <p>
                  Total <span className="mono">{formatTaka(posted.total)}</span>
                </p>
                <p>
                  Change <span className="mono">{formatTaka(receiptChange)}</span>
                </p>
                {posted.points_earned > 0 ? (
                  <p>
                    Points earned <span className="mono">{posted.points_earned}</span>
                  </p>
                ) : null}
              </div>
            </section>
            <div className={styles.actions}>
              <button className={styles.button} type="button" onClick={() => window.print()}>
                Print receipt
              </button>
              <button
                className={styles.buttonSecondary}
                type="button"
                onClick={() => {
                  setPosted(null);
                  setReceiptChange(0);
                  setCart([]);
                  setPointsInput("");
                  setCardInput("");
                  setCashInput("");
                  setMobile("");
                  setCustomer(null);
                  setBarcode("");
                }}
              >
                New sale
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
