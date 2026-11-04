import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import {
  closeDrawer,
  createCustomer,
  createSale,
  getCurrentDrawer,
  listProducts,
  listWarehouses,
  lookupCustomer,
  lookupProduct,
  openDrawer,
  type Customer,
  type Product,
  type Sale,
} from "../lib/api";
import { useAuth } from "../lib/auth";
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
  const [points, setPoints] = useState(0);
  const [card, setCard] = useState(0);
  const [cashReceived, setCashReceived] = useState(0);
  const [posted, setPosted] = useState<Sale | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [openingBalance, setOpeningBalance] = useState("");
  const [counted, setCounted] = useState("");

  const drawerQuery = useQuery({ queryKey: ["drawer", "current"], queryFn: getCurrentDrawer });
  const drawer = drawerQuery.data ?? null;
  const warehousesQuery = useQuery({
    queryKey: ["warehouses"],
    queryFn: listWarehouses,
    enabled: pin === null && drawer === null,
  });
  const productsQuery = useQuery({ queryKey: ["products"], queryFn: () => listProducts() });
  const products = (productsQuery.data?.data ?? []).filter((product) => product.track_stock && product.is_active);
  const shelf = cartShelfTotal(cart.map((line) => ({ unitPrice: line.product.sell_price, quantity: line.quantity })));
  const pointCap = Math.min(customer?.points_balance ?? 0, shelf);
  const appliedPoints = customer ? Math.min(Math.max(points, 0), pointCap) : 0;
  const cashDue = Math.max(shelf - appliedPoints - card, 0);

  const addProduct = (product: Product) => {
    if (!product.track_stock || !product.is_active) {
      setError("That product cannot be sold.");
      return;
    }
    setError(null);
    setPosted(null);
    setCart((current) => {
      const existing = current.find((line) => line.product.id === product.id);
      if (!existing) {
        return [...current, { product, quantity: 1 }];
      }
      return current.map((line) =>
        line.product.id === product.id ? { ...line, quantity: line.quantity + 1 } : line,
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
      setPoints(0);
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
    mutationFn: () => {
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
      return createSale({
        warehouse_id: drawer.warehouse_id,
        customer_id: customer?.id ?? null,
        lines: cart.map((line) => ({ product_id: line.product.id, quantity: line.quantity })),
        tenders,
      });
    },
    onSuccess: async (sale) => {
      setPosted(sale);
      setCart([]);
      setPoints(0);
      setCard(0);
      setCashReceived(0);
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
              onChange={(event) => {
                const product = products.find((item) => item.id === event.target.value);
                if (product) {
                  addProduct(product);
                }
              }}
            >
              <option value="">Add a tracked product</option>
              {products.map((product) => (
                <option key={product.id} value={product.id}>
                  {product.sku} · {product.name} · {formatTaka(product.sell_price)}
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
          <button className={styles.buttonSecondary} type="button" onClick={() => { setCustomer(null); setPoints(0); }}>
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
          <div className={styles.detailRow} key={line.product.id}>
            <span>
              <span className="mono">{line.product.sku}</span> · {formatTaka(line.product.sell_price)}
            </span>
            <input
              className={`mono ${styles.input}`}
              type="number"
              min={1}
              value={line.quantity}
              onChange={(event) => {
                const quantity = Number(event.target.value);
                setCart((current) =>
                  current.map((item) => (item.product.id === line.product.id ? { ...item, quantity } : item)),
                );
              }}
            />
          </div>
        ))}
        <p className={`mono ${styles.tillTotals}`}>Shelf {formatTaka(shelf)}</p>
        <div className={styles.toolbar}>
          <label className={styles.label}>
            Points
            <input
              className={`mono ${styles.input}`}
              type="number"
              min={0}
              max={pointCap}
              value={appliedPoints}
              disabled={customer === null}
              onChange={(event) => setPoints(Number(event.target.value))}
            />
          </label>
          <label className={styles.label}>
            Card
            <input
              className={`mono ${styles.input}`}
              type="number"
              min={0}
              value={card}
              onChange={(event) => setCard(Number(event.target.value))}
            />
          </label>
          <label className={styles.label}>
            Cash received
            <input
              className={`mono ${styles.input}`}
              type="number"
              min={0}
              value={cashReceived}
              onChange={(event) => setCashReceived(Number(event.target.value))}
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
          disabled={saleMutation.isPending || cart.length === 0 || cashDue + card + appliedPoints !== shelf}
          onClick={() => saleMutation.mutate()}
        >
          {saleMutation.isPending ? "Posting…" : "Post sale"}
        </button>
      </section>
      </div>
      ) : null}

      {posted ? (
        <section className={styles.panel}>
          <h2 className={styles.panelTitle}>
            {posted.number} · {posted.status}
          </h2>
          {posted.lines.map((line) => (
            <p key={line.id}>
              <span className="mono">{line.product.sku}</span> {line.cash_paid}/{line.points_redeemed}/{line.points_earned}
              {line.allocations.some((allocation) => allocation.payable) ? " · consignment payable" : ""}
            </p>
          ))}
          <p className={styles.muted}>
            Earned <span className="mono">{posted.points_earned}</span>
            {posted.points_balance_after !== null ? ` · balance ${posted.points_balance_after}` : ""}
          </p>
        </section>
      ) : null}
    </div>
  );
}
