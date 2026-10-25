import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import { createCategory, listCategories } from "../lib/api";
import { useAuth } from "../lib/auth";
import { can } from "../lib/permissions";
import styles from "./catalog.module.css";

const schema = z.object({
  name: z.string().min(1, "Name is required"),
});

type FormValues = z.infer<typeof schema>;

export function CategoriesPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [formError, setFormError] = useState<string | null>(null);
  const canManage = can(user, "catalog.manage");

  const categoriesQuery = useQuery({
    queryKey: ["categories"],
    queryFn: listCategories,
  });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { name: "" },
  });

  const createMutation = useMutation({
    mutationFn: createCategory,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["categories"] });
      reset();
      setFormError(null);
    },
    onError: (err: Error) => setFormError(err.message),
  });

  const onSubmit = handleSubmit((values) => {
    setFormError(null);
    createMutation.mutate({ name: values.name });
  });

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Categories</h1>
          <p className={styles.subtitle}>Product category tree roots and children.</p>
        </div>
      </header>

      <div className={styles.split}>
        <div className={styles.tableWrap}>
          {categoriesQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
          {categoriesQuery.isError ? (
            <p className={styles.error}>{(categoriesQuery.error as Error).message}</p>
          ) : null}
          {categoriesQuery.data ? (
            <table className={styles.table}>
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Parent</th>
                </tr>
              </thead>
              <tbody>
                {categoriesQuery.data.data.map((category) => (
                  <tr key={category.id}>
                    <td>{category.name}</td>
                    <td className="mono">{category.parent_id ?? "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : null}
        </div>

        {canManage ? (
          <section className={styles.panel}>
            <h2 className={styles.panelTitle}>Create category</h2>
            <form className={styles.form} onSubmit={onSubmit}>
              <label className={styles.label}>
                Name
                <input className={styles.input} {...register("name")} />
                {errors.name ? <span className={styles.fieldError}>{errors.name.message}</span> : null}
              </label>
              {formError ? <p className={styles.error}>{formError}</p> : null}
              <button className={styles.button} type="submit" disabled={isSubmitting || createMutation.isPending}>
                {createMutation.isPending ? "Creating…" : "Create"}
              </button>
            </form>
          </section>
        ) : null}
      </div>
    </div>
  );
}
