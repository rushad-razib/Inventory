import { useAuth } from "../lib/auth";
import styles from "./catalog.module.css";

export function HomePage() {
  const { user } = useAuth();

  if (!user) {
    return null;
  }

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Welcome, {user.name}</h1>
          <p className={styles.subtitle}>
            Signed in as <span className="mono">{user.email}</span>
            {user.department ? (
              <>
                {" "}
                · {user.department.name}
              </>
            ) : null}
          </p>
        </div>
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
  );
}
