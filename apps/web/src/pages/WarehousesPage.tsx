import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import { createWarehouse, listWarehouses } from "../lib/api";
import { useAuth } from "../lib/auth";
import { can } from "../lib/permissions";
import styles from "./catalog.module.css";

const schema = z.object({
  code: z.string().min(1, "Code is required"),
  name: z.string().min(1, "Name is required"),
  address: z.string().optional(),
});

type FormValues = z.infer<typeof schema>;

export function WarehousesPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [formError, setFormError] = useState<string | null>(null);
  const canCreate = can(user, "settings.manage");

  const warehousesQuery = useQuery({
    queryKey: ["warehouses"],
    queryFn: listWarehouses,
  });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { code: "", name: "", address: "" },
  });

  const createMutation = useMutation({
    mutationFn: createWarehouse,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["warehouses"] });
      reset();
      setFormError(null);
    },
    onError: (err: Error) => setFormError(err.message),
  });

  const onSubmit = handleSubmit((values) => {
    setFormError(null);
    createMutation.mutate({
      code: values.code,
      name: values.name,
      address: values.address || null,
    });
  });

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Warehouses</h1>
          <p className={styles.subtitle}>Store and stock locations.</p>
        </div>
      </header>

      <div className={styles.split}>
        <div className={styles.tableWrap}>
          {warehousesQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
          {warehousesQuery.isError ? (
            <p className={styles.error}>{(warehousesQuery.error as Error).message}</p>
          ) : null}
          {warehousesQuery.data ? (
            <table className={styles.table}>
              <thead>
                <tr>
                  <th>Code</th>
                  <th>Name</th>
                  <th>Address</th>
                  <th>Default</th>
                  <th>Active</th>
                </tr>
              </thead>
              <tbody>
                {warehousesQuery.data.data.map((warehouse) => (
                  <tr key={warehouse.id}>
                    <td className="mono">{warehouse.code}</td>
                    <td>{warehouse.name}</td>
                    <td>{warehouse.address ?? "—"}</td>
                    <td>{warehouse.is_default ? "Yes" : "No"}</td>
                    <td>{warehouse.is_active ? "Yes" : "No"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : null}
        </div>

        {canCreate ? (
          <section className={styles.panel}>
            <h2 className={styles.panelTitle}>Create warehouse</h2>
            <form className={styles.form} onSubmit={onSubmit}>
              <label className={styles.label}>
                Code
                <input className={`${styles.input} mono`} {...register("code")} />
                {errors.code ? <span className={styles.fieldError}>{errors.code.message}</span> : null}
              </label>
              <label className={styles.label}>
                Name
                <input className={styles.input} {...register("name")} />
                {errors.name ? <span className={styles.fieldError}>{errors.name.message}</span> : null}
              </label>
              <label className={styles.label}>
                Address
                <input className={styles.input} {...register("address")} />
              </label>
              {formError ? <p className={styles.error}>{formError}</p> : null}
              <button className={styles.button} type="submit" disabled={isSubmitting || createMutation.isPending}>
                {createMutation.isPending ? "Creating…" : "Create"}
              </button>
            </form>
          </section>
        ) : (
          <p className={styles.muted}>Creating warehouses requires settings.manage.</p>
        )}
      </div>
    </div>
  );
}
