import { useQuery } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useNavigate } from "react-router-dom";
import { useState } from "react";
import { BrandMark } from "../components/BrandMark";
import { PasswordInput } from "../components/PasswordInput";
import { listDemoLogins } from "../lib/api";
import { useAuth } from "../lib/auth";
import { useBranding } from "../lib/branding";
import styles from "./LoginPage.module.css";

const schema = z.object({
  email: z.email(),
  password: z.string().min(1, "Password is required"),
});

type FormValues = z.infer<typeof schema>;

export function LoginPage() {
  const { login } = useAuth();
  const { data: branding } = useBranding();
  const navigate = useNavigate();
  const [error, setError] = useState<string | null>(null);
  const loginsQuery = useQuery({ queryKey: ["demo-logins"], queryFn: listDemoLogins });
  const {
    register,
    handleSubmit,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      email: "",
      password: "",
    },
  });

  const onSubmit = handleSubmit(async (values) => {
    setError(null);
    try {
      await login(values.email, values.password);
      navigate("/", { replace: true });
    } catch (err) {
      setError(err instanceof Error ? err.message : "Login failed");
    }
  });

  return (
    <div className={styles.page}>
      <form className={styles.card} onSubmit={onSubmit}>
        <BrandMark className={styles.brand} logoClassName={styles.brandLogo} />
        <h1 className={styles.title}>Sign in</h1>
        <p className={styles.lead}>
          {branding?.name ? `Inventory and procurement for ${branding.name}.` : "Inventory and procurement."}
        </p>

        <label className={styles.label}>
          Email
          <input className={styles.input} type="email" autoComplete="username" {...register("email")} />
          {errors.email ? <span className={styles.fieldError}>{errors.email.message}</span> : null}
        </label>

        <div className={styles.label}>
          <label htmlFor="login-password">Password</label>
          <PasswordInput
            id="login-password"
            className={styles.input}
            autoComplete="current-password"
            {...register("password")}
          />
          {errors.password ? <span className={styles.fieldError}>{errors.password.message}</span> : null}
        </div>

        {error ? <p className={styles.error}>{error}</p> : null}

        <button className={styles.button} type="submit" disabled={isSubmitting}>
          {isSubmitting ? "Signing in…" : "Sign in"}
        </button>
      </form>

      <section className={styles.roster} aria-label="Demo accounts">
        <h2 className={styles.rosterTitle}>Demo accounts</h2>
        {loginsQuery.isLoading ? <p className={styles.rosterMuted}>Loading accounts…</p> : null}
        {loginsQuery.isError ? <p className={styles.error}>Could not load accounts.</p> : null}
        {loginsQuery.data ? (
          <ul className={styles.rosterList}>
            {loginsQuery.data.map((account) => (
              <li key={account.email}>
                <button
                  className={styles.rosterRow}
                  type="button"
                  onClick={() => {
                    setValue("email", account.email, { shouldValidate: true });
                    setValue("password", account.password ?? "", { shouldValidate: true });
                  }}
                >
                  <span className={styles.rosterName}>{account.name}</span>
                  <span className={styles.rosterMeta}>
                    {[account.role, account.warehouse].filter(Boolean).join(" · ")}
                  </span>
                  <span className={styles.rosterEmail}>{account.email}</span>
                  <span className={styles.rosterPassword}>
                    {account.password ?? "Password changed"}
                  </span>
                </button>
              </li>
            ))}
          </ul>
        ) : null}
      </section>
    </div>
  );
}
