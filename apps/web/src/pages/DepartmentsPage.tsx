import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import { createDepartment, listDepartments } from "../lib/api";
import styles from "./catalog.module.css";

const schema = z.object({
  name: z.string().min(1, "Name is required"),
});

type FormValues = z.infer<typeof schema>;

export function DepartmentsPage() {
  const queryClient = useQueryClient();
  const [formError, setFormError] = useState<string | null>(null);

  const departmentsQuery = useQuery({
    queryKey: ["departments"],
    queryFn: listDepartments,
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
    mutationFn: createDepartment,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["departments"] });
      reset();
      setFormError(null);
    },
    onError: (err: Error) => setFormError(err.message),
  });

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Departments</h1>
          <p className={styles.subtitle}>Org units for staff assignment.</p>
        </div>
      </header>

      <div className={styles.split}>
        <div className={styles.tableWrap}>
          {departmentsQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
          {departmentsQuery.isError ? (
            <p className={styles.error}>{(departmentsQuery.error as Error).message}</p>
          ) : null}
          {departmentsQuery.data ? (
            <table className={styles.table}>
              <thead>
                <tr>
                  <th>Name</th>
                </tr>
              </thead>
              <tbody>
                {departmentsQuery.data.data.map((department) => (
                  <tr key={department.id}>
                    <td>{department.name}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : null}
        </div>

        <section className={styles.panel}>
          <h2 className={styles.panelTitle}>Create department</h2>
          <form
            className={styles.form}
            onSubmit={handleSubmit((values) => {
              setFormError(null);
              createMutation.mutate(values);
            })}
          >
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
      </div>
    </div>
  );
}
