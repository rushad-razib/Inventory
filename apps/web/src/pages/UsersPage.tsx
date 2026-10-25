import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import {
  createUser,
  listUserImages,
  listUsers,
  uploadUserImage,
  type CatalogUser,
} from "../lib/api";
import styles from "./catalog.module.css";

const ROLES = ["Admin", "Manager", "Procurement", "Warehouse", "Cashier"] as const;

const schema = z.object({
  name: z.string().min(1, "Name is required"),
  email: z.email("Valid email required"),
  password: z.string().min(8, "Password must be at least 8 characters"),
  role: z.enum(ROLES),
});

type FormValues = z.infer<typeof schema>;

export function UsersPage() {
  const queryClient = useQueryClient();
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [uploadError, setUploadError] = useState<string | null>(null);

  const usersQuery = useQuery({
    queryKey: ["users"],
    queryFn: listUsers,
  });

  const imagesQuery = useQuery({
    queryKey: ["users", selectedId, "images"],
    queryFn: () => listUserImages(selectedId!),
    enabled: Boolean(selectedId),
  });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: "",
      email: "",
      password: "",
      role: "Cashier",
    },
  });

  const createMutation = useMutation({
    mutationFn: createUser,
    onSuccess: async (created) => {
      await queryClient.invalidateQueries({ queryKey: ["users"] });
      reset();
      setSelectedId(created.id);
      setFormError(null);
    },
    onError: (err: Error) => setFormError(err.message),
  });

  const uploadMutation = useMutation({
    mutationFn: ({ userId, file }: { userId: string; file: File }) => uploadUserImage(userId, file),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["users", selectedId, "images"] });
      setUploadError(null);
    },
    onError: (err: Error) => setUploadError(err.message),
  });

  const selected = usersQuery.data?.data.find((u: CatalogUser) => u.id === selectedId) ?? null;

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Users</h1>
          <p className={styles.subtitle}>Accounts, roles, and profile images.</p>
        </div>
      </header>

      <div className={styles.split}>
        <div className={styles.stack}>
          <div className={styles.tableWrap}>
            {usersQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
            {usersQuery.isError ? (
              <p className={styles.error}>{(usersQuery.error as Error).message}</p>
            ) : null}
            {usersQuery.data ? (
              <table className={styles.table}>
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Roles</th>
                    <th>Department</th>
                    <th>Active</th>
                  </tr>
                </thead>
                <tbody>
                  {usersQuery.data.data.map((catalogUser) => (
                    <tr
                      key={catalogUser.id}
                      className={[styles.clickable, selectedId === catalogUser.id ? styles.selected : ""]
                        .filter(Boolean)
                        .join(" ")}
                      onClick={() => setSelectedId(catalogUser.id)}
                    >
                      <td>{catalogUser.name}</td>
                      <td className="mono">{catalogUser.email}</td>
                      <td>{catalogUser.roles.join(", ")}</td>
                      <td>{catalogUser.department?.name ?? "—"}</td>
                      <td>{catalogUser.is_active ? "Yes" : "No"}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : null}
          </div>

          <section className={styles.panel}>
            <h2 className={styles.panelTitle}>Create user</h2>
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
              <label className={styles.label}>
                Email
                <input className={styles.input} type="email" {...register("email")} />
                {errors.email ? <span className={styles.fieldError}>{errors.email.message}</span> : null}
              </label>
              <label className={styles.label}>
                Password
                <input className={styles.input} type="password" {...register("password")} />
                {errors.password ? (
                  <span className={styles.fieldError}>{errors.password.message}</span>
                ) : null}
              </label>
              <label className={styles.label}>
                Role
                <select className={styles.select} {...register("role")}>
                  {ROLES.map((role) => (
                    <option key={role} value={role}>
                      {role}
                    </option>
                  ))}
                </select>
              </label>
              {formError ? <p className={styles.error}>{formError}</p> : null}
              <button className={styles.button} type="submit" disabled={isSubmitting || createMutation.isPending}>
                {createMutation.isPending ? "Creating…" : "Create"}
              </button>
            </form>
          </section>
        </div>

        <section className={styles.panel}>
          <h2 className={styles.panelTitle}>User detail</h2>
          {!selected ? <p className={styles.muted}>Select a user row.</p> : null}
          {selected ? (
            <>
              <div className={styles.detailGrid}>
                <div className={styles.detailRow}>
                  <span className={styles.detailLabel}>Name</span>
                  <span>{selected.name}</span>
                </div>
                <div className={styles.detailRow}>
                  <span className={styles.detailLabel}>Email</span>
                  <span className="mono">{selected.email}</span>
                </div>
                <div className={styles.detailRow}>
                  <span className={styles.detailLabel}>Roles</span>
                  <span>{selected.roles.join(", ")}</span>
                </div>
                <div className={styles.detailRow}>
                  <span className={styles.detailLabel}>Department</span>
                  <span>{selected.department?.name ?? "—"}</span>
                </div>
              </div>

              <h3 className={styles.panelTitle}>Images</h3>
              {imagesQuery.isLoading ? <p className={styles.muted}>Loading images…</p> : null}
              <div className={styles.images}>
                {(imagesQuery.data ?? []).map((image) => (
                  <img key={image.id} className={styles.thumb} src={image.url} alt="" />
                ))}
              </div>
              {(imagesQuery.data?.length ?? 0) === 0 && !imagesQuery.isLoading ? (
                <p className={styles.muted}>No images yet.</p>
              ) : null}

              <label className={styles.label}>
                Upload image
                <input
                  className={styles.input}
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  onChange={(event) => {
                    const file = event.target.files?.[0];
                    event.target.value = "";
                    if (!file || !selectedId) {
                      return;
                    }
                    setUploadError(null);
                    uploadMutation.mutate({ userId: selectedId, file });
                  }}
                />
              </label>
              {uploadError ? <p className={styles.error}>{uploadError}</p> : null}
            </>
          ) : null}
        </section>
      </div>
    </div>
  );
}
