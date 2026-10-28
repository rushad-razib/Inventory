import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import {
  createProduct,
  listCategories,
  listProductImages,
  listProducts,
  listSuppliers,
  updateProduct,
  uploadProductImage,
  type Product,
} from "../lib/api";
import { useAuth } from "../lib/auth";
import { can } from "../lib/permissions";
import { formatTaka } from "../lib/money";
import styles from "./catalog.module.css";

const schema = z.object({
  sku: z.string().min(1, "SKU is required"),
  name: z.string().min(1, "Name is required"),
  barcode: z.string().optional(),
  description: z.string().optional(),
  category_id: z.string().optional(),
  unit_of_measure: z.string().min(1, "Unit is required"),
  sell_price: z.coerce.number().int().min(0, "Sell price must be ≥ 0"),
  reorder_point: z.coerce.number().int().min(0, "Reorder point must be ≥ 0"),
  reorder_quantity: z.coerce.number().int().min(0, "Reorder quantity must be ≥ 0"),
  preferred_supplier_id: z.string().optional(),
  track_stock: z.boolean(),
  is_active: z.boolean(),
});

type FormValues = z.infer<typeof schema>;

const emptyProduct: FormValues = {
  sku: "",
  name: "",
  barcode: "",
  description: "",
  category_id: "",
  unit_of_measure: "each",
  sell_price: 0,
  reorder_point: 0,
  reorder_quantity: 0,
  preferred_supplier_id: "",
  track_stock: true,
  is_active: true,
};

function toPayload(values: FormValues) {
  return {
    sku: values.sku,
    name: values.name,
    barcode: values.barcode || null,
    description: values.description || null,
    category_id: values.category_id || null,
    unit_of_measure: values.unit_of_measure,
    sell_price: values.sell_price,
    reorder_point: values.reorder_point,
    reorder_quantity: values.reorder_quantity,
    preferred_supplier_id: values.preferred_supplier_id || null,
    track_stock: values.track_stock,
    is_active: values.is_active,
  };
}

export function ProductsPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [editError, setEditError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);
  const canManage = can(user, "catalog.manage");

  const productsQuery = useQuery({
    queryKey: ["products"],
    queryFn: listProducts,
  });

  const categoriesQuery = useQuery({
    queryKey: ["categories"],
    queryFn: listCategories,
  });

  const suppliersQuery = useQuery({
    queryKey: ["suppliers"],
    queryFn: listSuppliers,
    enabled: canManage || can(user, "inventory.view") || can(user, "suppliers.manage"),
  });

  const imagesQuery = useQuery({
    queryKey: ["products", selectedId, "images"],
    queryFn: () => listProductImages(selectedId!),
    enabled: Boolean(selectedId),
  });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: emptyProduct,
  });

  const editForm = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: emptyProduct,
  });

  const createMutation = useMutation({
    mutationFn: createProduct,
    onSuccess: async (product) => {
      await queryClient.invalidateQueries({ queryKey: ["products"] });
      reset(emptyProduct);
      setSelectedId(product.id);
      setFormError(null);
    },
    onError: (err: Error) => setFormError(err.message),
  });

  const updateMutation = useMutation({
    mutationFn: (values: FormValues) => updateProduct(selectedId!, toPayload(values)),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["products"] });
      setEditError(null);
      setSaved(true);
    },
    onError: (err: Error) => {
      setSaved(false);
      setEditError(err.message);
    },
  });

  const uploadMutation = useMutation({
    mutationFn: ({ productId, file }: { productId: string; file: File }) =>
      uploadProductImage(productId, file),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["products", selectedId, "images"] });
      setUploadError(null);
    },
    onError: (err: Error) => setUploadError(err.message),
  });

  const selected = productsQuery.data?.data.find((p) => p.id === selectedId) ?? null;
  const categories = categoriesQuery.data?.data ?? [];
  const suppliers = suppliersQuery.data?.data ?? [];

  useEffect(() => {
    if (!selected) {
      return;
    }
    editForm.reset({
      sku: selected.sku,
      name: selected.name,
      barcode: selected.barcode ?? "",
      description: selected.description ?? "",
      category_id: selected.category_id ?? "",
      unit_of_measure: selected.unit_of_measure,
      sell_price: selected.sell_price,
      reorder_point: selected.reorder_point,
      reorder_quantity: selected.reorder_quantity,
      preferred_supplier_id: selected.preferred_supplier_id ?? "",
      track_stock: selected.track_stock,
      is_active: selected.is_active,
    });
    setSaved(false);
    setEditError(null);
  }, [selected, editForm]);

  const categoryName = (id: string | null) => categories.find((category) => category.id === id)?.name ?? "—";
  const supplierName = (id: string | null) => {
    const supplier = suppliers.find((item) => item.id === id);
    return supplier ? supplier.name : "—";
  };

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Products</h1>
          <p className={styles.subtitle}>Catalog SKUs, barcodes, reorder levels, and sell prices.</p>
        </div>
      </header>

      <div className={styles.split}>
        <div className={styles.stack}>
          <div className={styles.tableWrap}>
            {productsQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
            {productsQuery.isError ? (
              <p className={styles.error}>{(productsQuery.error as Error).message}</p>
            ) : null}
            {productsQuery.data ? (
              <table className={styles.table}>
                <thead>
                  <tr>
                    <th>SKU</th>
                    <th>Name</th>
                    <th>Barcode</th>
                    <th className={styles.money}>Sell price</th>
                    <th className={styles.money}>Reorder</th>
                  </tr>
                </thead>
                <tbody>
                  {productsQuery.data.data.map((product: Product) => (
                    <tr
                      key={product.id}
                      className={[styles.clickable, selectedId === product.id ? styles.selected : ""]
                        .filter(Boolean)
                        .join(" ")}
                      onClick={() => setSelectedId(product.id)}
                    >
                      <td className="mono">{product.sku}</td>
                      <td>{product.name}</td>
                      <td className="mono">{product.barcode ?? "—"}</td>
                      <td className={`mono ${styles.money}`}>{formatTaka(product.sell_price)}</td>
                      <td className={`mono ${styles.money}`}>{product.reorder_point}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : null}
          </div>

          {canManage ? (
            <section className={styles.panel}>
              <h2 className={styles.panelTitle}>Create product</h2>
              <form
                className={styles.form}
                onSubmit={handleSubmit((values) => {
                  setFormError(null);
                  createMutation.mutate(toPayload(values));
                })}
              >
                <ProductFields
                  register={register}
                  errors={errors}
                  categories={categories}
                  suppliers={suppliers}
                />
                {formError ? <p className={styles.error}>{formError}</p> : null}
                <button className={styles.button} type="submit" disabled={isSubmitting || createMutation.isPending}>
                  {createMutation.isPending ? "Creating…" : "Create"}
                </button>
              </form>
            </section>
          ) : null}
        </div>

        <section className={styles.panel}>
          <h2 className={styles.panelTitle}>Product detail</h2>
          {!selected ? <p className={styles.muted}>Select a product row.</p> : null}
          {selected ? (
            <>
              {canManage ? (
                <form
                  className={styles.form}
                  onSubmit={editForm.handleSubmit((values) => {
                    setSaved(false);
                    setEditError(null);
                    updateMutation.mutate(values);
                  })}
                >
                  <ProductFields
                    register={editForm.register}
                    errors={editForm.formState.errors}
                    categories={categories}
                    suppliers={suppliers}
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
              ) : (
                <div className={styles.detailGrid}>
                  <div className={styles.detailRow}>
                    <span className={styles.detailLabel}>SKU</span>
                    <span className="mono">{selected.sku}</span>
                  </div>
                  <div className={styles.detailRow}>
                    <span className={styles.detailLabel}>Name</span>
                    <span>{selected.name}</span>
                  </div>
                  <div className={styles.detailRow}>
                    <span className={styles.detailLabel}>Barcode</span>
                    <span className="mono">{selected.barcode ?? "—"}</span>
                  </div>
                  <div className={styles.detailRow}>
                    <span className={styles.detailLabel}>Category</span>
                    <span>{categoryName(selected.category_id)}</span>
                  </div>
                  <div className={styles.detailRow}>
                    <span className={styles.detailLabel}>Sell price</span>
                    <span className="mono">{formatTaka(selected.sell_price)}</span>
                  </div>
                  <div className={styles.detailRow}>
                    <span className={styles.detailLabel}>Reorder at</span>
                    <span className="mono">{selected.reorder_point}</span>
                  </div>
                  <div className={styles.detailRow}>
                    <span className={styles.detailLabel}>Reorder qty</span>
                    <span className="mono">{selected.reorder_quantity}</span>
                  </div>
                  <div className={styles.detailRow}>
                    <span className={styles.detailLabel}>Supplier</span>
                    <span>{supplierName(selected.preferred_supplier_id)}</span>
                  </div>
                </div>
              )}

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

              {canManage ? (
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
                      uploadMutation.mutate({ productId: selectedId, file });
                    }}
                  />
                </label>
              ) : null}
              {uploadError ? <p className={styles.error}>{uploadError}</p> : null}
            </>
          ) : null}
        </section>
      </div>
    </div>
  );
}

function ProductFields({
  register,
  errors,
  categories,
  suppliers,
}: {
  register: ReturnType<typeof useForm<FormValues>>["register"];
  errors: ReturnType<typeof useForm<FormValues>>["formState"]["errors"];
  categories: { id: string; name: string }[];
  suppliers: { id: string; name: string; code: string }[];
}) {
  return (
    <>
      <label className={styles.label}>
        SKU
        <input className={`${styles.input} mono`} {...register("sku")} />
        {errors.sku ? <span className={styles.fieldError}>{errors.sku.message}</span> : null}
      </label>
      <label className={styles.label}>
        Name
        <input className={styles.input} {...register("name")} />
        {errors.name ? <span className={styles.fieldError}>{errors.name.message}</span> : null}
      </label>
      <label className={styles.label}>
        Barcode
        <input className={`${styles.input} mono`} {...register("barcode")} />
      </label>
      <label className={styles.label}>
        Description
        <input className={styles.input} {...register("description")} />
      </label>
      <label className={styles.label}>
        Category
        <select className={styles.select} {...register("category_id")}>
          <option value="">None</option>
          {categories.map((category) => (
            <option key={category.id} value={category.id}>
              {category.name}
            </option>
          ))}
        </select>
      </label>
      <label className={styles.label}>
        Unit
        <input className={styles.input} {...register("unit_of_measure")} />
        {errors.unit_of_measure ? (
          <span className={styles.fieldError}>{errors.unit_of_measure.message}</span>
        ) : null}
      </label>
      <label className={styles.label}>
        Sell price (BDT)
        <input className={`${styles.input} mono`} type="number" step={1} {...register("sell_price")} />
        {errors.sell_price ? <span className={styles.fieldError}>{errors.sell_price.message}</span> : null}
      </label>
      <label className={styles.label}>
        Reorder point
        <input className={`${styles.input} mono`} type="number" step={1} {...register("reorder_point")} />
        {errors.reorder_point ? <span className={styles.fieldError}>{errors.reorder_point.message}</span> : null}
      </label>
      <label className={styles.label}>
        Reorder quantity
        <input className={`${styles.input} mono`} type="number" step={1} {...register("reorder_quantity")} />
        {errors.reorder_quantity ? (
          <span className={styles.fieldError}>{errors.reorder_quantity.message}</span>
        ) : null}
      </label>
      <label className={styles.label}>
        Preferred supplier
        <select className={styles.select} {...register("preferred_supplier_id")}>
          <option value="">None</option>
          {suppliers.map((supplier) => (
            <option key={supplier.id} value={supplier.id}>
              {supplier.code} — {supplier.name}
            </option>
          ))}
        </select>
      </label>
      <label className={styles.check}>
        <input type="checkbox" {...register("track_stock")} />
        Track stock
      </label>
      <label className={styles.check}>
        <input type="checkbox" {...register("is_active")} />
        Active
      </label>
    </>
  );
}
