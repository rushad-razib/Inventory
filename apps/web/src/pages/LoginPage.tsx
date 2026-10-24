import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useNavigate } from "react-router-dom";
import { useState } from "react";
import { useAuth } from "../lib/auth";
import styles from "./LoginPage.module.css";

const schema = z.object({
  email: z.email(),
  password: z.string().min(1, "Password is required"),
});

type FormValues = z.infer<typeof schema>;

export function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [error, setError] = useState<string | null>(null);
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      email: "admin@meridian.test",
      password: "password",
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
        <p className={styles.brand}>Meridian</p>
        <h1 className={styles.title}>Sign in</h1>
        <p className={styles.lead}>Inventory and procurement for Hart &amp; Field.</p>

        <label className={styles.label}>
          Email
          <input className={styles.input} type="email" autoComplete="username" {...register("email")} />
          {errors.email ? <span className={styles.fieldError}>{errors.email.message}</span> : null}
        </label>

        <label className={styles.label}>
          Password
          <input
            className={styles.input}
            type="password"
            autoComplete="current-password"
            {...register("password")}
          />
          {errors.password ? <span className={styles.fieldError}>{errors.password.message}</span> : null}
        </label>

        {error ? <p className={styles.error}>{error}</p> : null}

        <button className={styles.button} type="submit" disabled={isSubmitting}>
          {isSubmitting ? "Signing in…" : "Sign in"}
        </button>
      </form>
    </div>
  );
}
