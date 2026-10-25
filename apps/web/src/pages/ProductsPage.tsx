import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useState } from "react";
import {
  createProduct,
  listProductImages,
  listProducts,
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
  sell_price: z.coerce.number().int().min(0, "Sell price must be ≥ 0"),
});

type FormValues = z.infer<typeof schema>;

export function ProductsPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [uploadError, setUploadError] = useState<string | null>(null);
  const canManage = can(user, "catalog.manage");

  const productsQuery = useQuery({
    queryKey: ["products"],
    queryFn: listProducts,
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
    defaultValues: { sku: "", name: "", barcode: "", sell_price: 0 },
  });

  const createMutation = useMutation({
    mutationFn: createProduct,
    onSuccess: async (product) => {
      await queryClient.invalidateQueries({ queryKey: ["products"] });
      reset();
      setSelectedId(product.id);
      setFormError(null);
    },
    onError: (err: Error) => setFormError(err.message),
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

  const onSubmit = handleSubmit((values) => {
    setFormError(null);
    createMutation.mutate({
      sku: values.sku,
      name: values.name,
      barcode: values.barcode || null,
      sell_price: values.sell_price,
    });
  });

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Products</h1>
          <p className={styles.subtitle}>Catalog SKUs, barcodes, and sell prices.</p>
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
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : null}
          </div>

          {canManage ? (
            <section className={styles.panel}>
              <h2 className={styles.panelTitle}>Create product</h2>
              <form className={styles.form} onSubmit={onSubmit}>
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
                  Sell price (BDT)
                  <input className={`${styles.input} mono`} type="number" step={1} {...register("sell_price")} />
                  {errors.sell_price ? (
                    <span className={styles.fieldError}>{errors.sell_price.message}</span>
                  ) : null}
                </label>
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
                  <span className={styles.detailLabel}>Sell price</span>
                  <span className="mono">{formatTaka(selected.sell_price)}</span>
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
