import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  createUser,
  listDepartments,
  listUserImages,
  listUsers,
  listWarehouses,
  updateUser,
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
  department_id: z.string().optional(),
  warehouse_id: z.string().optional(),
  is_active: z.boolean(),
});

const editSchema = schema.extend({
  password: z.string().refine((value) => value === "" || value.length >= 8, {
    message: "Password must be at least 8 characters",
  }),
});

type FormValues = z.infer<typeof schema>;

export function UsersPage() {
  const queryClient = useQueryClient();
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [editError, setEditError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);

  const usersQuery = useQuery({
    queryKey: ["users"],
    queryFn: listUsers,
  });

  const departmentsQuery = useQuery({
    queryKey: ["departments"],
    queryFn: listDepartments,
  });

  const warehousesQuery = useQuery({
    queryKey: ["warehouses"],
    queryFn: listWarehouses,
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
      department_id: "",
      warehouse_id: "",
      is_active: true,
    },
  });

  const editForm = useForm<FormValues>({
    resolver: zodResolver(editSchema),
    defaultValues: {
      name: "",
      email: "",
      password: "",
      role: "Cashier",
      department_id: "",
      warehouse_id: "",
      is_active: true,
    },
  });

  const createMutation = useMutation({
    mutationFn: createUser,
    onSuccess: async (created) => {
      await queryClient.invalidateQueries({ queryKey: ["users"] });
      reset({
        name: "",
        email: "",
        password: "",
        role: "Cashier",
        department_id: "",
        warehouse_id: "",
        is_active: true,
      });
      setSelectedId(created.id);
      setFormError(null);
    },
    onError: (err: Error) => setFormError(err.message),
  });

  const updateMutation = useMutation({
    mutationFn: (values: FormValues) =>
      updateUser(selectedId!, {
        name: values.name,
        email: values.email,
        role: values.role,
        department_id: values.department_id || null,
        warehouse_id: values.warehouse_id || null,
        is_active: values.is_active,
        ...(values.password ? { password: values.password } : {}),
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["users"] });
      editForm.setValue("password", "");
      setEditError(null);
      setSaved(true);
    },
    onError: (err: Error) => {
      setSaved(false);
      setEditError(err.message);
    },
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
  const departments = departmentsQuery.data?.data ?? [];
  const warehouses = warehousesQuery.data?.data ?? [];
  const warehouseName = (id: string | null) => warehouses.find((warehouse) => warehouse.id === id)?.name ?? "—";

  useEffect(() => {
    if (!selected) {
      return;
    }
    editForm.reset({
      name: selected.name,
      email: selected.email,
      password: "",
      role: (selected.roles[0] as FormValues["role"]) ?? "Cashier",
      department_id: selected.department?.id ?? "",
      warehouse_id: selected.warehouse_id ?? "",
      is_active: selected.is_active,
    });
    setSaved(false);
    setEditError(null);
  }, [selected, editForm]);

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Users</h1>
          <p className={styles.subtitle}>Accounts, roles, departments, and warehouse pins.</p>
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
                    <th>Warehouse</th>
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
                      <td>{warehouseName(catalogUser.warehouse_id)}</td>
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
                createMutation.mutate({
                  name: values.name,
                  email: values.email,
                  password: values.password,
                  role: values.role,
                  department_id: values.department_id || null,
                  warehouse_id: values.warehouse_id || null,
                  is_active: values.is_active,
                });
              })}
            >
              <UserFields
                register={register}
                errors={errors}
                departments={departments}
                warehouses={warehouses}
                passwordLabel="Password"
              />
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
              <form
                className={styles.form}
                onSubmit={editForm.handleSubmit((values) => {
                  setSaved(false);
                  setEditError(null);
                  updateMutation.mutate(values);
                })}
              >
                <UserFields
                  register={editForm.register}
                  errors={editForm.formState.errors}
                  departments={departments}
                  warehouses={warehouses}
                  passwordLabel="New password"
                />
                {editError ? <p className={styles.error}>{editError}</p> : null}
                {saved ? <p className={styles.muted}>Saved.</p> : null}
                <button
                  className={styles.button}
                  type="submit"
                  disabled={editForm.formState.isSubmitting || updateMutation.isPending}
                >
                  {updateMutation.isPending ? "Saving…" : "Save"}
                </button>
              </form>

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

function UserFields({
  register,
  errors,
  departments,
  warehouses,
  passwordLabel,
}: {
  register: ReturnType<typeof useForm<FormValues>>["register"];
  errors: ReturnType<typeof useForm<FormValues>>["formState"]["errors"];
  departments: { id: string; name: string }[];
  warehouses: { id: string; code: string; name: string }[];
  passwordLabel: string;
}) {
  return (
    <>
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
        {passwordLabel}
        <input className={styles.input} type="password" {...register("password")} />
        {errors.password ? <span className={styles.fieldError}>{errors.password.message}</span> : null}
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
      <label className={styles.label}>
        Department
        <select className={styles.select} {...register("department_id")}>
          <option value="">None</option>
          {departments.map((department) => (
            <option key={department.id} value={department.id}>
              {department.name}
            </option>
          ))}
        </select>
      </label>
      <label className={styles.label}>
        Warehouse pin
        <select className={styles.select} {...register("warehouse_id")}>
          <option value="">None</option>
          {warehouses.map((warehouse) => (
            <option key={warehouse.id} value={warehouse.id}>
              {warehouse.code} — {warehouse.name}
            </option>
          ))}
        </select>
      </label>
      <label className={styles.check}>
        <input type="checkbox" {...register("is_active")} />
        Active
      </label>
    </>
  );
}
