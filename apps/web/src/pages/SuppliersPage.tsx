import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  createSupplier,
  createSupplierProduct,
  listProducts,
  listSupplierProducts,
  listSuppliers,
  updateSupplier,
  type Supplier,
} from "../lib/api";
import { useAuth } from "../lib/auth";
import { can } from "../lib/permissions";
import { formatTaka } from "../lib/money";
import styles from "./catalog.module.css";

const supplierSchema = z.object({
  code: z.string().min(1, "Code is required"),
  name: z.string().min(1, "Name is required"),
  supply_mode: z.enum(["outright", "consignment"]),
  status: z.enum(["active", "inactive"]),
  contact: z.string().optional(),
  email: z.string().optional(),
  phone: z.string().optional(),
  address: z.string().optional(),
  payment_terms_days: z.coerce.number().int().min(0, "Payment terms must be ≥ 0"),
  lead_time_days: z.coerce.number().int().min(0, "Lead time must be ≥ 0"),
  notes: z.string().optional(),
});

const linkSchema = z.object({
  product_id: z.string().uuid("Select a product"),
  agreed_unit_cost: z.coerce.number().int().min(0, "Cost must be ≥ 0"),
});

type SupplierForm = z.infer<typeof supplierSchema>;
type LinkForm = z.infer<typeof linkSchema>;

export function SuppliersPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [editError, setEditError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const [linkError, setLinkError] = useState<string | null>(null);
  const canManage = can(user, "suppliers.manage");

  const suppliersQuery = useQuery({
    queryKey: ["suppliers"],
    queryFn: listSuppliers,
  });

  const productsQuery = useQuery({
    queryKey: ["products"],
    queryFn: listProducts,
    enabled: Boolean(selectedId),
  });

  const linksQuery = useQuery({
    queryKey: ["suppliers", selectedId, "products"],
    queryFn: () => listSupplierProducts(selectedId!),
    enabled: Boolean(selectedId),
  });

  const supplierForm = useForm<SupplierForm>({
    resolver: zodResolver(supplierSchema),
    defaultValues: {
      code: "",
      name: "",
      supply_mode: "outright",
      status: "active",
      contact: "",
      email: "",
      phone: "",
      address: "",
      payment_terms_days: 0,
      lead_time_days: 0,
      notes: "",
    },
  });

  const editForm = useForm<SupplierForm>({
    resolver: zodResolver(supplierSchema),
    defaultValues: {
      code: "",
      name: "",
      supply_mode: "outright",
      status: "active",
      contact: "",
      email: "",
      phone: "",
      address: "",
      payment_terms_days: 0,
      lead_time_days: 0,
      notes: "",
    },
  });

  const linkForm = useForm<LinkForm>({
    resolver: zodResolver(linkSchema),
    defaultValues: { product_id: "", agreed_unit_cost: 0 },
  });

  const createMutation = useMutation({
    mutationFn: createSupplier,
    onSuccess: async (supplier) => {
      await queryClient.invalidateQueries({ queryKey: ["suppliers"] });
      supplierForm.reset();
      setSelectedId(supplier.id);
      setFormError(null);
    },
    onError: (err: Error) => setFormError(err.message),
  });

  const linkMutation = useMutation({
    mutationFn: (values: LinkForm) =>
      createSupplierProduct(selectedId!, {
        product_id: values.product_id,
        agreed_unit_cost: values.agreed_unit_cost,
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["suppliers", selectedId, "products"] });
      linkForm.reset({ product_id: "", agreed_unit_cost: 0 });
      setLinkError(null);
    },
    onError: (err: Error) => setLinkError(err.message),
  });

  const updateMutation = useMutation({
    mutationFn: (values: SupplierForm) =>
      updateSupplier(selectedId!, {
        code: values.code,
        name: values.name,
        supply_mode: values.supply_mode,
        status: values.status,
        contact: values.contact || null,
        email: values.email || null,
        phone: values.phone || null,
        address: values.address || null,
        payment_terms_days: values.payment_terms_days,
        lead_time_days: values.lead_time_days,
        notes: values.notes || null,
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["suppliers"] });
      setEditError(null);
      setSaved(true);
    },
    onError: (err: Error) => {
      setSaved(false);
      setEditError(err.message);
    },
  });

  const selected = suppliersQuery.data?.data.find((s: Supplier) => s.id === selectedId) ?? null;
  const products = productsQuery.data?.data ?? [];

  useEffect(() => {
    if (!selected) {
      return;
    }
    editForm.reset({
      code: selected.code,
      name: selected.name,
      supply_mode: selected.supply_mode === "consignment" ? "consignment" : "outright",
      status: selected.status === "inactive" ? "inactive" : "active",
      contact: selected.contact ?? "",
      email: selected.email ?? "",
      phone: selected.phone ?? "",
      address: selected.address ?? "",
      payment_terms_days: selected.payment_terms_days,
      lead_time_days: selected.lead_time_days,
      notes: selected.notes ?? "",
    });
    setSaved(false);
    setEditError(null);
  }, [selected, editForm]);

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Suppliers</h1>
          <p className={styles.subtitle}>Vendors and agreed unit costs.</p>
        </div>
      </header>

      <div className={styles.split}>
        <div className={styles.stack}>
          <div className={styles.tableWrap}>
            {suppliersQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
            {suppliersQuery.isError ? (
              <p className={styles.error}>{(suppliersQuery.error as Error).message}</p>
            ) : null}
            {suppliersQuery.data ? (
              <table className={styles.table}>
                <thead>
                  <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Mode</th>
                    <th>Terms</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  {suppliersQuery.data.data.map((supplier) => (
                    <tr
                      key={supplier.id}
                      className={[styles.clickable, selectedId === supplier.id ? styles.selected : ""]
                        .filter(Boolean)
                        .join(" ")}
                      onClick={() => setSelectedId(supplier.id)}
                    >
                      <td className="mono">{supplier.code}</td>
                      <td>{supplier.name}</td>
                      <td>{supplier.supply_mode}</td>
                      <td className="mono">{supplier.payment_terms_days}d</td>
                      <td>{supplier.status}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : null}
          </div>

          {canManage ? (
            <section className={styles.panel}>
              <h2 className={styles.panelTitle}>Create supplier</h2>
              <form
                className={styles.form}
                onSubmit={supplierForm.handleSubmit((values) => {
                  setFormError(null);
                  createMutation.mutate({
                    code: values.code,
                    name: values.name,
                    supply_mode: values.supply_mode,
                    status: values.status,
                    contact: values.contact || null,
                    email: values.email || null,
                    phone: values.phone || null,
                    address: values.address || null,
                    payment_terms_days: values.payment_terms_days,
                    lead_time_days: values.lead_time_days,
                    notes: values.notes || null,
                  });
                })}
              >
                <label className={styles.label}>
                  Code
                  <input className={`${styles.input} mono`} {...supplierForm.register("code")} />
                  {supplierForm.formState.errors.code ? (
                    <span className={styles.fieldError}>{supplierForm.formState.errors.code.message}</span>
                  ) : null}
                </label>
                <label className={styles.label}>
                  Name
                  <input className={styles.input} {...supplierForm.register("name")} />
                  {supplierForm.formState.errors.name ? (
                    <span className={styles.fieldError}>{supplierForm.formState.errors.name.message}</span>
                  ) : null}
                </label>
                <label className={styles.label}>
                  Contact
                  <input className={styles.input} {...supplierForm.register("contact")} />
                </label>
                <label className={styles.label}>
                  Email
                  <input className={styles.input} type="email" {...supplierForm.register("email")} />
                </label>
                <label className={styles.label}>
                  Phone
                  <input className={styles.input} {...supplierForm.register("phone")} />
                </label>
                <label className={styles.label}>
                  Supply mode
                  <select className={styles.select} {...supplierForm.register("supply_mode")}>
                    <option value="outright">outright</option>
                    <option value="consignment">consignment</option>
                  </select>
                </label>
                <label className={styles.label}>
                  Status
                  <select className={styles.select} {...supplierForm.register("status")}>
                    <option value="active">active</option>
                    <option value="inactive">inactive</option>
                  </select>
                </label>
                <label className={styles.label}>
                  Payment terms (days)
                  <input
                    className={`${styles.input} mono`}
                    type="number"
                    step={1}
                    {...supplierForm.register("payment_terms_days")}
                  />
                  {supplierForm.formState.errors.payment_terms_days ? (
                    <span className={styles.fieldError}>
                      {supplierForm.formState.errors.payment_terms_days.message}
                    </span>
                  ) : null}
                </label>
                <label className={styles.label}>
                  Lead time (days)
                  <input
                    className={`${styles.input} mono`}
                    type="number"
                    step={1}
                    {...supplierForm.register("lead_time_days")}
                  />
                </label>
                <label className={styles.label}>
                  Address
                  <input className={styles.input} {...supplierForm.register("address")} />
                </label>
                <label className={styles.label}>
                  Notes
                  <input className={styles.input} {...supplierForm.register("notes")} />
                </label>
                {formError ? <p className={styles.error}>{formError}</p> : null}
                <button className={styles.button} type="submit" disabled={createMutation.isPending}>
                  {createMutation.isPending ? "Creating…" : "Create"}
                </button>
              </form>
            </section>
          ) : null}
        </div>

        <section className={styles.panel}>
          <h2 className={styles.panelTitle}>Supplier products</h2>
          {!selected ? <p className={styles.muted}>Select a supplier row.</p> : null}
          {selected ? (
            <>
              <p className={styles.subtitle}>
                {selected.name} <span className="mono">({selected.code})</span>
              </p>
              {canManage ? (
                <form
                  className={styles.form}
                  onSubmit={editForm.handleSubmit((values) => {
                    setSaved(false);
                    setEditError(null);
                    updateMutation.mutate(values);
                  })}
                >
                  <label className={styles.label}>
                    Code
                    <input className={`${styles.input} mono`} {...editForm.register("code")} />
                  </label>
                  <label className={styles.label}>
                    Name
                    <input className={styles.input} {...editForm.register("name")} />
                  </label>
                  <label className={styles.label}>
                    Supply mode
                    <select className={styles.select} {...editForm.register("supply_mode")}>
                      <option value="outright">outright</option>
                      <option value="consignment">consignment</option>
                    </select>
                  </label>
                  <label className={styles.label}>
                    Status
                    <select className={styles.select} {...editForm.register("status")}>
                      <option value="active">active</option>
                      <option value="inactive">inactive</option>
                    </select>
                  </label>
                  <label className={styles.label}>
                    Payment terms (days)
                    <input
                      className={`${styles.input} mono`}
                      type="number"
                      step={1}
                      {...editForm.register("payment_terms_days")}
                    />
                  </label>
                  <label className={styles.label}>
                    Lead time (days)
                    <input
                      className={`${styles.input} mono`}
                      type="number"
                      step={1}
                      {...editForm.register("lead_time_days")}
                    />
                  </label>
                  <label className={styles.label}>
                    Contact
                    <input className={styles.input} {...editForm.register("contact")} />
                  </label>
                  <label className={styles.label}>
                    Email
                    <input className={styles.input} type="email" {...editForm.register("email")} />
                  </label>
                  <label className={styles.label}>
                    Phone
                    <input className={styles.input} {...editForm.register("phone")} />
                  </label>
                  <label className={styles.label}>
                    Address
                    <input className={styles.input} {...editForm.register("address")} />
                  </label>
                  <label className={styles.label}>
                    Notes
                    <input className={styles.input} {...editForm.register("notes")} />
                  </label>
                  {editError ? <p className={styles.error}>{editError}</p> : null}
                  {saved ? <p className={styles.muted}>Saved.</p> : null}
                  <button className={styles.button} type="submit" disabled={updateMutation.isPending}>
                    {updateMutation.isPending ? "Saving…" : "Save supplier"}
                  </button>
                </form>
              ) : (
                <div className={styles.detailGrid}>
                  <div className={styles.detailRow}>
                    <span className={styles.detailLabel}>Terms</span>
                    <span className="mono">{selected.payment_terms_days} days</span>
                  </div>
                  <div className={styles.detailRow}>
                    <span className={styles.detailLabel}>Lead time</span>
                    <span className="mono">{selected.lead_time_days} days</span>
                  </div>
                </div>
              )}
              <div className={styles.tableWrap}>
                {linksQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
                {linksQuery.data ? (
                  <table className={styles.table}>
                    <thead>
                      <tr>
                        <th>Product</th>
                        <th className={styles.money}>Agreed cost</th>
                      </tr>
                    </thead>
                    <tbody>
                      {linksQuery.data.data.map((link) => (
                        <tr key={link.id}>
                          <td className="mono">
                            {products.find((product) => product.id === link.product_id)?.sku ?? link.product_id}
                          </td>
                          <td className={`mono ${styles.money}`}>{formatTaka(link.agreed_unit_cost)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                ) : null}
              </div>

              {canManage ? (
                <form
                  className={styles.form}
                  onSubmit={linkForm.handleSubmit((values) => {
                    setLinkError(null);
                    linkMutation.mutate(values);
                  })}
                >
                  <h3 className={styles.panelTitle}>Add agreed cost</h3>
                  <label className={styles.label}>
                    Product
                    <select className={styles.select} {...linkForm.register("product_id")}>
                      <option value="">Select…</option>
                      {(productsQuery.data?.data ?? []).map((product) => (
                        <option key={product.id} value={product.id}>
                          {product.sku} — {product.name}
                        </option>
                      ))}
                    </select>
                    {linkForm.formState.errors.product_id ? (
                      <span className={styles.fieldError}>{linkForm.formState.errors.product_id.message}</span>
                    ) : null}
                  </label>
                  <label className={styles.label}>
                    Agreed unit cost (BDT)
                    <input
                      className={`${styles.input} mono`}
                      type="number"
                      step={1}
                      {...linkForm.register("agreed_unit_cost")}
                    />
                    {linkForm.formState.errors.agreed_unit_cost ? (
                      <span className={styles.fieldError}>
                        {linkForm.formState.errors.agreed_unit_cost.message}
                      </span>
                    ) : null}
                  </label>
                  {linkError ? <p className={styles.error}>{linkError}</p> : null}
                  <button className={styles.button} type="submit" disabled={linkMutation.isPending}>
                    {linkMutation.isPending ? "Saving…" : "Add"}
                  </button>
                </form>
              ) : null}
            </>
          ) : null}
        </section>
      </div>
    </div>
  );
}
