import styles from "./catalog.module.css";

export function EmptyState({ children }: { children: string }) {
  return (
    <div className={styles.empty}>
      <svg className={styles.emptyMark} viewBox="0 0 48 48" aria-hidden="true">
        <rect x="8" y="14" width="32" height="22" rx="2" fill="none" stroke="currentColor" strokeWidth="1.5" />
        <path d="M8 20h32" stroke="#0f6b6b" strokeWidth="1.5" />
        <path d="M16 28h16" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
      </svg>
      <p className={styles.emptyCopy}>{children}</p>
    </div>
  );
}
