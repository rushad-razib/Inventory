import { useEffect, useRef, useState } from "react";
import { NavLink, Outlet } from "react-router-dom";
import { BrandMark } from "../components/BrandMark";
import { useAuth } from "../lib/auth";
import { can } from "../lib/permissions";
import { CommandPalette } from "./CommandPalette";
import styles from "./AppShell.module.css";

type NavItem = {
  to: string;
  label: string;
  visible: boolean;
};

export function AppShell() {
  const { user, logout } = useAuth();
  const [paletteOpen, setPaletteOpen] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  const [navOpen, setNavOpen] = useState(true);
  const profileRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!menuOpen) {
      return;
    }

    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setMenuOpen(false);
      }
    };

    const onPointer = (event: MouseEvent) => {
      if (profileRef.current && !profileRef.current.contains(event.target as Node)) {
        setMenuOpen(false);
      }
    };

    document.addEventListener("keydown", onKey);
    document.addEventListener("mousedown", onPointer);
    return () => {
      document.removeEventListener("keydown", onKey);
      document.removeEventListener("mousedown", onPointer);
    };
  }, [menuOpen]);

  if (!user) {
    return null;
  }

  const items: NavItem[] = [
    { to: "/", label: "Home", visible: true },
    {
      to: "/products",
      label: "Products",
      visible: can(user, "catalog.manage") || can(user, "inventory.view"),
    },
    {
      to: "/categories",
      label: "Categories",
      visible: can(user, "catalog.manage") || can(user, "inventory.view"),
    },
    {
      to: "/warehouses",
      label: "Warehouses",
      visible: can(user, "inventory.view") || can(user, "settings.manage"),
    },
    { to: "/stock", label: "Stock", visible: can(user, "inventory.view") },
    { to: "/lots", label: "Lots", visible: can(user, "inventory.view") },
    { to: "/movements", label: "Movements", visible: can(user, "inventory.view") },
    { to: "/transfers", label: "Transfers", visible: can(user, "inventory.transfer") },
    {
      to: "/adjustments",
      label: "Adjustments",
      visible: can(user, "inventory.adjust") || can(user, "purchasing.approve"),
    },
    {
      to: "/purchase-orders",
      label: "Purchase orders",
      visible: can(user, "purchasing.create") || can(user, "purchasing.approve") || can(user, "purchasing.receive"),
    },
    {
      to: "/goods-receipts",
      label: "Receipts",
      visible: can(user, "purchasing.receive") || can(user, "inventory.view"),
    },
    { to: "/reorder-alerts", label: "Reorder alerts", visible: can(user, "purchasing.create") },
    {
      to: "/approvals",
      label: "Approvals",
      visible: can(user, "purchasing.approve") || can(user, "sales.refund.approve"),
    },
    {
      to: "/supplier-bills",
      label: "Payables",
      visible: can(user, "purchasing.settle") || can(user, "reports.view"),
    },
    {
      to: "/supplier-returns",
      label: "Supplier returns",
      visible: can(user, "supplier_returns.manage") || can(user, "purchasing.settle"),
    },
    {
      to: "/cash-book",
      label: "Cash book",
      visible: can(user, "reports.view") || can(user, "purchasing.settle"),
    },
    { to: "/reports", label: "Reports", visible: can(user, "reports.view") },
    { to: "/audit", label: "Audit", visible: can(user, "audit.view") },
    { to: "/pos", label: "Till", visible: can(user, "sales.create") },
    {
      to: "/sales",
      label: "Sales",
      visible: can(user, "sales.create") || can(user, "sales.return") || can(user, "reports.view"),
    },
    {
      to: "/customer-returns",
      label: "Returns",
      visible: can(user, "sales.return") || can(user, "sales.refund.approve"),
    },
    {
      to: "/suppliers",
      label: "Suppliers",
      visible: can(user, "suppliers.manage") || can(user, "inventory.view"),
    },
    {
      to: "/customers",
      label: "Customers",
      visible: can(user, "customers.manage") || can(user, "sales.create"),
    },
    { to: "/users", label: "Users", visible: can(user, "users.manage") },
    { to: "/departments", label: "Departments", visible: can(user, "users.manage") },
    { to: "/settings", label: "Settings", visible: true },
  ];

  return (
    <div className={styles.layout}>
      <header className={styles.header}>
        <div className={styles.topBar}>
          <div className={styles.topLeft}>
            <BrandMark className={styles.brand} logoClassName={styles.brandLogo} />
            <button className={styles.searchButton} type="button" onClick={() => setPaletteOpen(true)}>
              Search
            </button>
          </div>
          <div className={styles.topRight}>
            <button
              className={styles.navToggle}
              type="button"
              aria-expanded={navOpen}
              aria-controls="app-nav"
              aria-label={navOpen ? "Hide menu" : "Show menu"}
              onClick={() => setNavOpen((open) => !open)}
            >
              <svg
                className={navOpen ? styles.chevron : `${styles.chevron} ${styles.chevronClosed}`}
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path
                  d="M6 14.5 12 8.5l6 6"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.8"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
              </svg>
            </button>
            <div className={styles.profile} ref={profileRef}>
              <button
                className={styles.profileButton}
                type="button"
                aria-label="Account"
                aria-haspopup="menu"
                aria-expanded={menuOpen}
                onClick={() => setMenuOpen((open) => !open)}
              >
                <svg className={styles.profileIcon} viewBox="0 0 24 24" aria-hidden="true">
                  <circle cx="12" cy="8" r="3.2" fill="none" stroke="currentColor" strokeWidth="1.8" />
                  <path
                    d="M5.5 19.2c1.4-2.8 3.6-4.2 6.5-4.2s5.1 1.4 6.5 4.2"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.8"
                    strokeLinecap="round"
                  />
                </svg>
              </button>
              {menuOpen ? (
                <div className={styles.profileMenu} role="menu">
                  <NavLink
                    className={styles.profileMenuItem}
                    role="menuitem"
                    to="/profile"
                    onClick={() => setMenuOpen(false)}
                  >
                    Profile
                  </NavLink>
                  <button
                    className={styles.profileMenuItem}
                    type="button"
                    role="menuitem"
                    onClick={() => {
                      setMenuOpen(false);
                      void logout();
                    }}
                  >
                    Log out
                  </button>
                </div>
              ) : null}
            </div>
          </div>
        </div>
        <div className={navOpen ? styles.navWrap : `${styles.navWrap} ${styles.navWrapClosed}`}>
          <div className={styles.navClip}>
            <nav id="app-nav" className={styles.nav} inert={navOpen ? undefined : true}>
              {items
                .filter((item) => item.visible)
                .map((item) => (
                  <NavLink
                    key={item.to}
                    to={item.to}
                    end={item.to === "/"}
                    className={({ isActive }) => (isActive ? styles.navItemActive : styles.navItem)}
                  >
                    {item.label}
                  </NavLink>
                ))}
            </nav>
          </div>
        </div>
      </header>
      <main className={styles.main}>
        <Outlet />
      </main>
      <CommandPalette user={user} open={paletteOpen} onOpenChange={setPaletteOpen} />
    </div>
  );
}
