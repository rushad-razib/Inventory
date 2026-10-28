import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { createCategory, listCategories, updateCategory } from "../lib/api";
import { useAuth } from "../lib/auth";
import { can } from "../lib/permissions";
import styles from "./catalog.module.css";

const schema = z.object({
  name: z.string().min(1, "Name is required"),
  parent_id: z.string().optional(),
});

type FormValues = z.infer<typeof schema>;

export function CategoriesPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [editError, setEditError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
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
    defaultValues: { name: "", parent_id: "" },
  });

  const editForm = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { name: "", parent_id: "" },
  });

  const createMutation = useMutation({
    mutationFn: createCategory,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["categories"] });
      reset({ name: "", parent_id: "" });
      setFormError(null);
    },
    onError: (err: Error) => setFormError(err.message),
  });

  const updateMutation = useMutation({
    mutationFn: (values: FormValues) =>
      updateCategory(selectedId!, {
        name: values.name,
        parent_id: values.parent_id || null,
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["categories"] });
      setEditError(null);
      setSaved(true);
    },
    onError: (err: Error) => {
      setSaved(false);
      setEditError(err.message);
    },
  });

  const onSubmit = handleSubmit((values) => {
    setFormError(null);
    createMutation.mutate({ name: values.name, parent_id: values.parent_id || null });
  });

  const categories = categoriesQuery.data?.data ?? [];
  const selected = categories.find((category) => category.id === selectedId) ?? null;
  const parentName = (id: string | null) => categories.find((category) => category.id === id)?.name ?? "—";

  useEffect(() => {
    if (!selected) {
      return;
    }
    editForm.reset({
      name: selected.name,
      parent_id: selected.parent_id ?? "",
    });
    setSaved(false);
    setEditError(null);
  }, [selected, editForm]);

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
                  <tr
                    key={category.id}
                    className={[styles.clickable, selectedId === category.id ? styles.selected : ""]
                      .filter(Boolean)
                      .join(" ")}
                    onClick={() => setSelectedId(category.id)}
                  >
                    <td>{category.name}</td>
                    <td>{parentName(category.parent_id)}</td>
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
              <label className={styles.label}>
                Parent
                <select className={styles.select} {...register("parent_id")}>
                  <option value="">None</option>
                  {categories.map((category) => (
                    <option key={category.id} value={category.id}>
                      {category.name}
                    </option>
                  ))}
                </select>
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
                <h2 className={styles.panelTitle}>Edit category</h2>
                <label className={styles.label}>
                  Name
                  <input className={styles.input} {...editForm.register("name")} />
                </label>
                <label className={styles.label}>
                  Parent
                  <select className={styles.select} {...editForm.register("parent_id")}>
                    <option value="">None</option>
                    {categories
                      .filter((category) => category.id !== selected.id)
                      .map((category) => (
                        <option key={category.id} value={category.id}>
                          {category.name}
                        </option>
                      ))}
                  </select>
                </label>
                {editError ? <p className={styles.error}>{editError}</p> : null}
                {saved ? <p className={styles.muted}>Saved.</p> : null}
                <button className={styles.button} type="submit" disabled={updateMutation.isPending}>
                  {updateMutation.isPending ? "Saving…" : "Save"}
                </button>
              </form>
            ) : null}
          </section>
        ) : null}
      </div>
    </div>
  );
}
