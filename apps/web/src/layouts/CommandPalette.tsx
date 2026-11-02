import { useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import {
  listCustomers,
  listProducts,
  listPurchaseOrders,
  listSuppliers,
  type AuthUser,
} from "../lib/api";
import { can } from "../lib/permissions";
import styles from "./AppShell.module.css";

type PaletteHit = {
  group: string;
  label: string;
  detail: string;
  to: string;
};

export function CommandPalette({
  user,
  open,
  onOpenChange,
}: {
  user: AuthUser;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const navigate = useNavigate();
  const [query, setQuery] = useState("");
  const [debounced, setDebounced] = useState("");
  const [active, setActive] = useState(0);

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
        event.preventDefault();
        onOpenChange(!open);
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open, onOpenChange]);

  useEffect(() => {
    const timer = window.setTimeout(() => setDebounced(query.trim()), 150);
    return () => window.clearTimeout(timer);
  }, [query]);

  const search = useQuery({
    queryKey: ["palette", debounced],
    enabled: open && debounced.length > 0,
    queryFn: () => searchRecords(user, debounced),
  });
  const hits = search.data ?? [];

  useEffect(() => {
    setActive(0);
  }, [debounced]);

  const choose = (hit: PaletteHit) => {
    onOpenChange(false);
    setQuery("");
    navigate(hit.to);
  };

  if (!open) {
    return null;
  }

  return (
    <div className={styles.paletteBackdrop} onMouseDown={() => onOpenChange(false)}>
      <div
        className={styles.palette}
        role="dialog"
        aria-label="Search records"
        onMouseDown={(event) => event.stopPropagation()}
      >
        <input
          className={styles.paletteInput}
          autoFocus
          placeholder="SKU, supplier, order, or customer"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === "Escape") {
              onOpenChange(false);
            }
            if (event.key === "ArrowDown") {
              event.preventDefault();
              setActive((current) => Math.min(current + 1, Math.max(hits.length - 1, 0)));
            }
            if (event.key === "ArrowUp") {
              event.preventDefault();
              setActive((current) => Math.max(current - 1, 0));
            }
            if (event.key === "Enter" && hits[active]) {
              event.preventDefault();
              choose(hits[active]);
            }
          }}
        />
        {debounced === "" ? <p className={styles.paletteEmpty}>Type to search.</p> : null}
        {debounced !== "" && search.isFetching ? <p className={styles.paletteEmpty}>Searching…</p> : null}
        {debounced !== "" && !search.isFetching && hits.length === 0 ? (
          <p className={styles.paletteEmpty}>No matches.</p>
        ) : null}
        <ul className={styles.paletteList}>
          {hits.map((hit, index) => (
            <li key={`${hit.to}-${hit.label}`}>
              <button
                type="button"
                className={index === active ? styles.paletteHitActive : styles.paletteHit}
                onMouseEnter={() => setActive(index)}
                onClick={() => choose(hit)}
              >
                <span className={styles.paletteGroup}>{hit.group}</span>
                <span className="mono">{hit.label}</span>
                <span className={styles.paletteDetail}>{hit.detail}</span>
              </button>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}

async function searchRecords(user: AuthUser, q: string): Promise<PaletteHit[]> {
  const hits: PaletteHit[] = [];

  if (can(user, "inventory.view") || can(user, "catalog.manage")) {
    const products = await listProducts(q);
    for (const product of products.data) {
      hits.push({
        group: "Product",
        label: product.sku,
        detail: product.name,
        to: `/products?q=${encodeURIComponent(product.sku)}`,
      });
    }
  }

  if (can(user, "inventory.view") || can(user, "suppliers.manage")) {
    const suppliers = await listSuppliers(q);
    for (const supplier of suppliers.data) {
      hits.push({
        group: "Supplier",
        label: supplier.code,
        detail: supplier.name,
        to: `/suppliers?q=${encodeURIComponent(supplier.code)}`,
      });
    }
  }

  if (can(user, "purchasing.create") || can(user, "purchasing.approve") || can(user, "purchasing.receive")) {
    const orders = await listPurchaseOrders(q);
    for (const order of orders.data) {
      hits.push({
        group: "Purchase order",
        label: order.number,
        detail: order.supplier.name,
        to: `/purchase-orders?q=${encodeURIComponent(order.number)}`,
      });
    }
  }

  if (can(user, "customers.manage")) {
    const customers = await listCustomers(q);
    for (const customer of customers.data) {
      hits.push({
        group: "Customer",
        label: customer.mobile,
        detail: customer.name ?? "No name",
        to: `/customers?q=${encodeURIComponent(customer.mobile)}`,
      });
    }
  }

  return hits;
}
