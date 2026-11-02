import { useEffect, useMemo, useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { updateProfile } from "../lib/api";
import { useAuth } from "../lib/auth";
import { PasswordInput } from "../components/PasswordInput";
import styles from "./catalog.module.css";

function profileSchema(currentEmail: string) {
  return z
    .object({
      name: z.string().min(1, "Name is required"),
      email: z.email("Valid email required"),
      current_password: z.string(),
      password: z.string(),
      password_confirmation: z.string(),
    })
    .superRefine((values, ctx) => {
      const changingPassword = values.password !== "" || values.password_confirmation !== "";
      const emailChanged = values.email !== currentEmail;
      if ((changingPassword || emailChanged) && values.current_password === "") {
        ctx.addIssue({
          code: "custom",
          message: "Current password is required",
          path: ["current_password"],
        });
      }
      if (values.password !== "" && values.password.length < 8) {
        ctx.addIssue({
          code: "custom",
          message: "Password must be at least 8 characters",
          path: ["password"],
        });
      }
      if (values.password !== values.password_confirmation) {
        ctx.addIssue({
          code: "custom",
          message: "Passwords do not match",
          path: ["password_confirmation"],
        });
      }
    });
}

type FormValues = z.infer<ReturnType<typeof profileSchema>>;

export function ProfilePage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [formError, setFormError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const resolver = useMemo(() => zodResolver(profileSchema(user?.email ?? "")), [user?.email]);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver,
    defaultValues: {
      name: user?.name ?? "",
      email: user?.email ?? "",
      current_password: "",
      password: "",
      password_confirmation: "",
    },
  });

  useEffect(() => {
    if (!user) {
      return;
    }
    reset({
      name: user.name,
      email: user.email,
      current_password: "",
      password: "",
      password_confirmation: "",
    });
  }, [reset, user]);

  const updateMutation = useMutation({
    mutationFn: updateProfile,
    onSuccess: (updated) => {
      queryClient.setQueryData(["auth", "me"], updated);
      reset({
        name: updated.name,
        email: updated.email,
        current_password: "",
        password: "",
        password_confirmation: "",
      });
      setFormError(null);
      setSaved(true);
    },
    onError: (err: Error) => {
      setSaved(false);
      setFormError(err.message);
    },
  });

  if (!user) {
    return null;
  }

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Profile</h1>
          <p className={styles.subtitle}>Update your name, email, and password.</p>
        </div>
      </header>

      <div className={styles.split}>
        <section className={styles.panel}>
          <h2 className={styles.panelTitle}>Your account</h2>
          <form
            className={styles.form}
            onSubmit={handleSubmit((values) => {
              setSaved(false);
              setFormError(null);
              updateMutation.mutate({
                name: values.name,
                email: values.email,
                ...(values.current_password ? { current_password: values.current_password } : {}),
                ...(values.password
                  ? {
                      password: values.password,
                      password_confirmation: values.password_confirmation,
                    }
                  : {}),
              });
            })}
          >
            <label className={styles.label}>
              Name
              <input className={styles.input} {...register("name")} />
              {errors.name ? <span className={styles.fieldError}>{errors.name.message}</span> : null}
            </label>
            <label className={styles.label}>
              Email
              <input className={styles.input} type="email" autoComplete="email" {...register("email")} />
              {errors.email ? <span className={styles.fieldError}>{errors.email.message}</span> : null}
            </label>
            <label className={styles.label}>
              Current password
              <PasswordInput
                className={styles.input}
                autoComplete="current-password"
                {...register("current_password")}
              />
              {errors.current_password ? (
                <span className={styles.fieldError}>{errors.current_password.message}</span>
              ) : null}
            </label>
            <label className={styles.label}>
              New password
              <PasswordInput className={styles.input} autoComplete="new-password" {...register("password")} />
              {errors.password ? <span className={styles.fieldError}>{errors.password.message}</span> : null}
            </label>
            <label className={styles.label}>
              Confirm new password
              <PasswordInput
                className={styles.input}
                autoComplete="new-password"
                {...register("password_confirmation")}
              />
              {errors.password_confirmation ? (
                <span className={styles.fieldError}>{errors.password_confirmation.message}</span>
              ) : null}
            </label>
            {formError ? <p className={styles.error}>{formError}</p> : null}
            {saved ? <p className={styles.muted}>Saved.</p> : null}
            <button className={styles.button} type="submit" disabled={isSubmitting || updateMutation.isPending}>
              {updateMutation.isPending ? "Saving…" : "Save"}
            </button>
          </form>
        </section>
      </div>
    </div>
  );
}
