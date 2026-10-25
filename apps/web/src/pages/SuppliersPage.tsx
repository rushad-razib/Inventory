import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import {
  createSupplier,
  createSupplierProduct,
  listProducts,
  listSupplierProducts,
  listSuppliers,
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
  const [linkError, setLinkError] = useState<string | null>(null);
  const canManage = can(user, "suppliers.manage");

  const suppliersQuery = useQuery({
    queryKey: ["suppliers"],
    queryFn: listSuppliers,
  });

  const productsQuery = useQuery({
    queryKey: ["products"],
    queryFn: listProducts,
    enabled: canManage && Boolean(selectedId),
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

  const selected = suppliersQuery.data?.data.find((s: Supplier) => s.id === selectedId) ?? null;

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
                          <td className="mono">{link.product_id}</td>
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
