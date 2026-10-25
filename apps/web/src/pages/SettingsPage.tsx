import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { fetchSettings, updateSettings } from "../lib/api";
import { useAuth } from "../lib/auth";
import { can } from "../lib/permissions";
import { formatTaka } from "../lib/money";
import styles from "./catalog.module.css";

const schema = z.object({
  name: z.string().min(1, "Name is required"),
  po_approval_threshold: z.coerce.number().int().min(0),
  adjustment_approval_threshold: z.coerce.number().int().min(0),
  points_earn_per: z.coerce.number().int().min(0),
  point_redeem_value: z.coerce.number().int().min(0),
});

type FormValues = z.infer<typeof schema>;

export function SettingsPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [formError, setFormError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const canEdit = can(user, "settings.manage");

  const settingsQuery = useQuery({
    queryKey: ["settings"],
    queryFn: fetchSettings,
  });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
  });

  useEffect(() => {
    if (settingsQuery.data) {
      reset({
        name: settingsQuery.data.name,
        po_approval_threshold: settingsQuery.data.po_approval_threshold,
        adjustment_approval_threshold: settingsQuery.data.adjustment_approval_threshold,
        points_earn_per: settingsQuery.data.points_earn_per,
        point_redeem_value: settingsQuery.data.point_redeem_value,
      });
    }
  }, [settingsQuery.data, reset]);

  const updateMutation = useMutation({
    mutationFn: updateSettings,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["settings"] });
      setFormError(null);
      setSaved(true);
    },
    onError: (err: Error) => {
      setSaved(false);
      setFormError(err.message);
    },
  });

  const settings = settingsQuery.data;

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Settings</h1>
          <p className={styles.subtitle}>Company defaults and approval thresholds.</p>
        </div>
      </header>

      {settingsQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
      {settingsQuery.isError ? (
        <p className={styles.error}>{(settingsQuery.error as Error).message}</p>
      ) : null}

      {settings ? (
        <div className={styles.split}>
          <section className={styles.panel}>
            <h2 className={styles.panelTitle}>Current</h2>
            <div className={styles.detailGrid}>
              <div className={styles.detailRow}>
                <span className={styles.detailLabel}>Name</span>
                <span>{settings.name}</span>
              </div>
              <div className={styles.detailRow}>
                <span className={styles.detailLabel}>Currency</span>
                <span className="mono">{settings.currency}</span>
              </div>
              <div className={styles.detailRow}>
                <span className={styles.detailLabel}>PO approval</span>
                <span className="mono">{formatTaka(settings.po_approval_threshold)}</span>
              </div>
              <div className={styles.detailRow}>
                <span className={styles.detailLabel}>Adj. approval</span>
                <span className="mono">{formatTaka(settings.adjustment_approval_threshold)}</span>
              </div>
              <div className={styles.detailRow}>
                <span className={styles.detailLabel}>Points earn /</span>
                <span className="mono">{settings.points_earn_per}</span>
              </div>
              <div className={styles.detailRow}>
                <span className={styles.detailLabel}>Point value</span>
                <span className="mono">{formatTaka(settings.point_redeem_value)}</span>
              </div>
            </div>
          </section>

          {canEdit ? (
            <section className={styles.panel}>
              <h2 className={styles.panelTitle}>Edit</h2>
              <form
                className={styles.form}
                onSubmit={handleSubmit((values) => {
                  setSaved(false);
                  setFormError(null);
                  updateMutation.mutate(values);
                })}
              >
                <label className={styles.label}>
                  Company name
                  <input className={styles.input} {...register("name")} />
                  {errors.name ? <span className={styles.fieldError}>{errors.name.message}</span> : null}
                </label>
                <label className={styles.label}>
                  PO approval threshold (BDT)
                  <input
                    className={`${styles.input} mono`}
                    type="number"
                    step={1}
                    {...register("po_approval_threshold")}
                  />
                </label>
                <label className={styles.label}>
                  Adjustment approval threshold (BDT)
                  <input
                    className={`${styles.input} mono`}
                    type="number"
                    step={1}
                    {...register("adjustment_approval_threshold")}
                  />
                </label>
                <label className={styles.label}>
                  Points earn per
                  <input
                    className={`${styles.input} mono`}
                    type="number"
                    step={1}
                    {...register("points_earn_per")}
                  />
                </label>
                <label className={styles.label}>
                  Point redeem value (BDT)
                  <input
                    className={`${styles.input} mono`}
                    type="number"
                    step={1}
                    {...register("point_redeem_value")}
                  />
                </label>
                {formError ? <p className={styles.error}>{formError}</p> : null}
                {saved ? <p className={styles.muted}>Saved.</p> : null}
                <button className={styles.button} type="submit" disabled={isSubmitting || updateMutation.isPending}>
                  {updateMutation.isPending ? "Saving…" : "Save"}
                </button>
              </form>
            </section>
          ) : (
            <p className={styles.muted}>Editing settings requires settings.manage.</p>
          )}
        </div>
      ) : null}
    </div>
  );
}
