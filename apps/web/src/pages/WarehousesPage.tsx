import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { createWarehouse, deleteWarehouse, listAllStockBalances, listWarehouses, updateWarehouse, type Warehouse } from "../lib/api";
import { confirmDelete, showWarning } from "../lib/dialogs";
import { useAuth } from "../lib/auth";
import { can } from "../lib/permissions";
import { EmptyState } from "./EmptyState";
import styles from "./catalog.module.css";

const schema = z.object({
  code: z.string().min(1, "Code is required"),
  name: z.string().min(1, "Name is required"),
  address: z.string().optional(),
  is_default: z.boolean(),
  is_active: z.boolean(),
});

type FormValues = z.infer<typeof schema>;

export function WarehousesPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [editError, setEditError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const canCreate = can(user, "settings.manage");
  const canViewStock = can(user, "inventory.view");

  const warehousesQuery = useQuery({
    queryKey: ["warehouses"],
    queryFn: listWarehouses,
  });

  const stockQuery = useQuery({
    queryKey: ["stock-balances", "warehouse", selectedId],
    queryFn: () => listAllStockBalances(selectedId!),
    enabled: canViewStock && selectedId !== null,
  });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { code: "", name: "", address: "", is_default: false, is_active: true },
  });

  const editForm = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { code: "", name: "", address: "", is_default: false, is_active: true },
  });

  const createMutation = useMutation({
    mutationFn: createWarehouse,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["warehouses"] });
      reset({ code: "", name: "", address: "", is_default: false, is_active: true });
      setFormError(null);
    },
    onError: (err: Error) => setFormError(err.message),
  });

  const updateMutation = useMutation({
    mutationFn: (values: FormValues) =>
      updateWarehouse(selectedId!, {
        code: values.code,
        name: values.name,
        address: values.address || null,
        is_default: values.is_default,
        is_active: values.is_active,
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["warehouses"] });
      setEditError(null);
      setSaved(true);
    },
    onError: (err: Error) => {
      setSaved(false);
      setEditError(err.message);
    },
  });

  const deleteMutation = useMutation({
    mutationFn: () => deleteWarehouse(selectedId!),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["warehouses"] });
      setSelectedId(null);
    },
    onError: (err: Error) => {
      void showWarning(err.message);
    },
  });

  const selected = warehousesQuery.data?.data.find((warehouse: Warehouse) => warehouse.id === selectedId) ?? null;
  const stockRows = (stockQuery.data ?? [])
    .filter((balance) => balance.on_hand > 0 || balance.damaged > 0 || balance.in_transit > 0)
    .sort((a, b) => a.product.sku.localeCompare(b.product.sku));

  useEffect(() => {
    if (!selected) {
      return;
    }
    editForm.reset({
      code: selected.code,
      name: selected.name,
      address: selected.address ?? "",
      is_default: selected.is_default,
      is_active: selected.is_active,
    });
    setSaved(false);
    setEditError(null);
  }, [selected, editForm]);

  const onSubmit = handleSubmit((values) => {
    setFormError(null);
    createMutation.mutate({
      code: values.code,
      name: values.name,
      address: values.address || null,
      is_default: values.is_default,
      is_active: values.is_active,
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
        <div className={styles.stack}>
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
                    <tr
                      key={warehouse.id}
                      className={[styles.clickable, selectedId === warehouse.id ? styles.selected : ""]
                        .filter(Boolean)
                        .join(" ")}
                      onClick={() => setSelectedId(warehouse.id)}
                    >
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

          {canViewStock && selected ? (
            <section>
              <h2 className={styles.panelTitle}>
                Stock in {selected.code} · {selected.name}
              </h2>
              {stockQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
              {stockQuery.isError ? <p className={styles.error}>{(stockQuery.error as Error).message}</p> : null}
              {stockQuery.data && stockRows.length === 0 ? (
                <EmptyState>No stock in this warehouse.</EmptyState>
              ) : null}
              {stockRows.length > 0 ? (
                <div className={styles.tableWrap}>
                  <table className={styles.table}>
                    <thead>
                      <tr>
                        <th>Product</th>
                        <th className={styles.money}>On hand</th>
                        <th className={styles.money}>Damaged</th>
                        <th className={styles.money}>In transit</th>
                      </tr>
                    </thead>
                    <tbody>
                      {stockRows.map((balance) => (
                        <tr key={balance.id}>
                          <td>
                            <span className="mono">{balance.product.sku}</span> {balance.product.name}
                          </td>
                          <td className={`mono ${styles.money}`}>{balance.on_hand}</td>
                          <td className={`mono ${styles.money}`}>{balance.damaged}</td>
                          <td className={`mono ${styles.money}`}>{balance.in_transit}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : null}
            </section>
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
              <label className={styles.check}>
                <input type="checkbox" {...register("is_default")} />
                Default warehouse
              </label>
              <label className={styles.check}>
                <input type="checkbox" {...register("is_active")} />
                Active
              </label>
              {formError ? <p className={styles.error}>{formError}</p> : null}
              <button className={styles.button} type="submit" disabled={isSubmitting || createMutation.isPending}>
                {createMutation.isPending ? "Creating…" : "Create"}
              </button>
            </form>
            {selected ? (
              <form
                className={styles.form}
                onSubmit={editForm.handleSubmit((values) => {
                  setSaved(false);
                  setEditError(null);
                  updateMutation.mutate(values);
                })}
              >
                <h2 className={styles.panelTitle}>Edit {selected.code}</h2>
                <label className={styles.label}>
                  Code
                  <input className={`${styles.input} mono`} {...editForm.register("code")} />
                </label>
                <label className={styles.label}>
                  Name
                  <input className={styles.input} {...editForm.register("name")} />
                </label>
                <label className={styles.label}>
                  Address
                  <input className={styles.input} {...editForm.register("address")} />
                </label>
                <label className={styles.check}>
                  <input type="checkbox" {...editForm.register("is_default")} />
                  Default warehouse
                </label>
                <label className={styles.check}>
                  <input type="checkbox" {...editForm.register("is_active")} />
                  Active
                </label>
                {editError ? <p className={styles.error}>{editError}</p> : null}
                {saved ? <p className={styles.muted}>Saved.</p> : null}
                <button className={styles.button} type="submit" disabled={updateMutation.isPending}>
                  {updateMutation.isPending ? "Saving…" : "Save"}
                </button>
                <button
                  className={styles.buttonDanger}
                  type="button"
                  disabled={deleteMutation.isPending}
                  onClick={async () => {
                    if (!(await confirmDelete(selected.name))) {
                      return;
                    }
                    deleteMutation.mutate();
                  }}
                >
                  {deleteMutation.isPending ? "Deleting…" : "Delete"}
                </button>
              </form>
            ) : (
              <p className={styles.muted}>Select a warehouse to edit it.</p>
            )}
          </section>
        ) : (
          <p className={styles.muted}>Creating warehouses requires settings.manage.</p>
        )}
      </div>
    </div>
  );
}
