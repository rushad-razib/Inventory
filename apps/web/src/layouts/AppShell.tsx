import { NavLink, Outlet } from "react-router-dom";
import { useAuth } from "../lib/auth";
import { can } from "../lib/permissions";
import styles from "./AppShell.module.css";

type NavItem = {
  to: string;
  label: string;
  visible: boolean;
};

export function AppShell() {
  const { user, logout } = useAuth();

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
      <aside className={styles.sidebar}>
        <div className={styles.brand}>Meridian</div>
        <nav className={styles.nav}>
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
        <div className={styles.sidebarFooter}>
          <div className={styles.userMeta} title={user.email}>
            {user.name}
          </div>
          <button className={styles.logout} type="button" onClick={() => void logout()}>
            Sign out
          </button>
        </div>
      </aside>
      <main className={styles.main}>
        <Outlet />
      </main>
    </div>
  );
}
