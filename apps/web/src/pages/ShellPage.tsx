import { useAuth } from "../lib/auth";
import styles from "./ShellPage.module.css";

export function ShellPage() {
  const { user, logout } = useAuth();

  if (!user) {
    return null;
  }

  return (
    <div className={styles.layout}>
      <aside className={styles.sidebar}>
        <div className={styles.brand}>Meridian</div>
        <nav className={styles.nav}>
          <span className={styles.navItemActive}>Home</span>
          <span className={styles.navHint}>Catalog arrives in Phase 2</span>
        </nav>
      </aside>
      <div className={styles.main}>
        <header className={styles.header}>
          <div>
            <h1 className={styles.title}>Welcome, {user.name}</h1>
            <p className={styles.subtitle}>
              Signed in as <span className="mono">{user.email}</span>
            </p>
          </div>
          <button className={styles.logout} type="button" onClick={() => void logout()}>
            Sign out
          </button>
        </header>
        <section className={styles.panel}>
          <h2 className={styles.panelTitle}>Roles</h2>
          <ul className={styles.list}>
            {user.roles.map((role) => (
              <li key={role}>{role}</li>
            ))}
          </ul>
          <h2 className={styles.panelTitle}>Permissions</h2>
          <div className={styles.permissions}>
            {user.permissions.map((permission) => (
              <code key={permission} className={styles.chip}>
                {permission}
              </code>
            ))}
          </div>
        </section>
      </div>
    </div>
  );
}
