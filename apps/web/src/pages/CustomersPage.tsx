import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useSearchParams } from "react-router-dom";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import { createCustomer, deleteCustomer, listCustomers, lookupCustomer, type Customer } from "../lib/api";
import { confirmDelete, showWarning } from "../lib/dialogs";
import { useAuth } from "../lib/auth";
import { can } from "../lib/permissions";
import { EmptyState } from "./EmptyState";
import styles from "./catalog.module.css";

const createSchema = z.object({
  mobile: z.string().min(1, "Mobile is required"),
  name: z.string().optional(),
});

const lookupSchema = z.object({
  mobile: z.string().min(1, "Mobile is required"),
});

type CreateForm = z.infer<typeof createSchema>;
type LookupForm = z.infer<typeof lookupSchema>;

export function CustomersPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [lookupError, setLookupError] = useState<string | null>(null);
  const [lookupResult, setLookupResult] = useState<Customer | null | undefined>(undefined);
  const canManage = can(user, "customers.manage");
  const canLookup = can(user, "sales.create");
  const [searchParams] = useSearchParams();
  const q = searchParams.get("q") ?? "";

  const customersQuery = useQuery({
    queryKey: ["customers", q],
    queryFn: () => listCustomers(q || undefined),
    enabled: canManage,
  });

  const createForm = useForm<CreateForm>({
    resolver: zodResolver(createSchema),
    defaultValues: { mobile: "", name: "" },
  });

  const lookupForm = useForm<LookupForm>({
    resolver: zodResolver(lookupSchema),
    defaultValues: { mobile: "" },
  });

  const createMutation = useMutation({
    mutationFn: createCustomer,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["customers"] });
      createForm.reset();
      setFormError(null);
    },
    onError: (err: Error) => setFormError(err.message),
  });

  const deleteMutation = useMutation({
    mutationFn: () => deleteCustomer(selectedId!),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["customers"] });
      setSelectedId(null);
    },
    onError: (err: Error) => {
      void showWarning(err.message);
    },
  });

  const selected = customersQuery.data?.data.find((customer) => customer.id === selectedId) ?? null;

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Customers</h1>
          <p className={styles.subtitle}>Loyalty accounts and mobile lookup.</p>
        </div>
      </header>

      <div className={styles.stack}>
        {canLookup ? (
          <section className={styles.panel}>
            <h2 className={styles.panelTitle}>Mobile lookup</h2>
            <form
              className={styles.form}
              onSubmit={lookupForm.handleSubmit(async (values) => {
                setLookupError(null);
                setLookupResult(undefined);
                try {
                  const result = await lookupCustomer(values.mobile);
                  setLookupResult(result);
                } catch (err) {
                  setLookupError(err instanceof Error ? err.message : "Lookup failed");
                }
              })}
            >
              <label className={styles.label}>
                Mobile
                <input className={`${styles.input} mono`} {...lookupForm.register("mobile")} />
                {lookupForm.formState.errors.mobile ? (
                  <span className={styles.fieldError}>{lookupForm.formState.errors.mobile.message}</span>
                ) : null}
              </label>
              {lookupError ? <p className={styles.error}>{lookupError}</p> : null}
              <button className={styles.button} type="submit">
                Look up
              </button>
            </form>
            {lookupResult === null ? <p className={styles.muted}>No customer found.</p> : null}
            {lookupResult ? (
              <div className={styles.detailGrid}>
                <div className={styles.detailRow}>
                  <span className={styles.detailLabel}>Mobile</span>
                  <span className="mono">{lookupResult.mobile}</span>
                </div>
                <div className={styles.detailRow}>
                  <span className={styles.detailLabel}>Name</span>
                  <span>{lookupResult.name ?? "—"}</span>
                </div>
                <div className={styles.detailRow}>
                  <span className={styles.detailLabel}>Points</span>
                  <span className="mono">{lookupResult.points_balance}</span>
                </div>
              </div>
            ) : null}
          </section>
        ) : null}

        <div className={styles.split}>
          {canManage ? (
            <div className={styles.tableWrap}>
              {customersQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
              {customersQuery.isError ? (
                <p className={styles.error}>{(customersQuery.error as Error).message}</p>
              ) : null}
              {customersQuery.data && customersQuery.data.data.length === 0 ? (
                <EmptyState>No customers yet.</EmptyState>
              ) : null}
              {customersQuery.data && customersQuery.data.data.length > 0 ? (
                <table className={styles.table}>
                  <thead>
                    <tr>
                      <th>Mobile</th>
                      <th>Name</th>
                      <th className={styles.money}>Points</th>
                    </tr>
                  </thead>
                  <tbody>
                    {customersQuery.data.data.map((customer) => (
                      <tr
                        key={customer.id}
                        className={[styles.clickable, selectedId === customer.id ? styles.selected : ""]
                          .filter(Boolean)
                          .join(" ")}
                        onClick={() => setSelectedId(customer.id)}
                      >
                        <td className="mono">{customer.mobile}</td>
                        <td>{customer.name ?? "—"}</td>
                        <td className={`mono ${styles.money}`}>{customer.points_balance}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              ) : null}
            </div>
          ) : (
            <p className={styles.muted}>Listing customers requires customers.manage.</p>
          )}

          {canManage ? (
            <section className={styles.panel}>
              {selected ? (
                <div className={styles.form}>
                  <h2 className={styles.panelTitle}>Customer detail</h2>
                  <div className={styles.detailGrid}>
                    <div className={styles.detailRow}>
                      <span className={styles.detailLabel}>Mobile</span>
                      <span className="mono">{selected.mobile}</span>
                    </div>
                    <div className={styles.detailRow}>
                      <span className={styles.detailLabel}>Name</span>
                      <span>{selected.name ?? "—"}</span>
                    </div>
                  </div>
                  <button
                    className={styles.buttonDanger}
                    type="button"
                    disabled={deleteMutation.isPending}
                    onClick={async () => {
                      if (!(await confirmDelete(selected.name ?? selected.mobile))) {
                        return;
                      }
                      deleteMutation.mutate();
                    }}
                  >
                    {deleteMutation.isPending ? "Deleting…" : "Delete"}
                  </button>
                </div>
              ) : (
                <p className={styles.muted}>Select a customer to delete it.</p>
              )}
              <h2 className={styles.panelTitle}>Create customer</h2>
              <form
                className={styles.form}
                onSubmit={createForm.handleSubmit((values) => {
                  setFormError(null);
                  createMutation.mutate({
                    mobile: values.mobile,
                    name: values.name || null,
                  });
                })}
              >
                <label className={styles.label}>
                  Mobile
                  <input className={`${styles.input} mono`} {...createForm.register("mobile")} />
                  {createForm.formState.errors.mobile ? (
                    <span className={styles.fieldError}>{createForm.formState.errors.mobile.message}</span>
                  ) : null}
                </label>
                <label className={styles.label}>
                  Name
                  <input className={styles.input} {...createForm.register("name")} />
                </label>
                {formError ? <p className={styles.error}>{formError}</p> : null}
                <button className={styles.button} type="submit" disabled={createMutation.isPending}>
                  {createMutation.isPending ? "Creating…" : "Create"}
                </button>
              </form>
            </section>
          ) : null}
        </div>
      </div>
    </div>
  );
}
