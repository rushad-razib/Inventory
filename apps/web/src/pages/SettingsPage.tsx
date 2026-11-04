import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  deleteFavicon,
  deleteLogo,
  fetchSettings,
  updateSettings,
  uploadFavicon,
  uploadLogo,
  type CompanySettings,
} from "../lib/api";
import { brandingQueryKey } from "../lib/branding";
import { useAuth } from "../lib/auth";
import { can } from "../lib/permissions";
import { formatTaka } from "../lib/money";
import styles from "./catalog.module.css";

const generalSchema = z.object({
  po_approval_threshold: z.coerce.number().int().min(0),
  adjustment_approval_threshold: z.coerce.number().int().min(0),
  points_earn_per: z.coerce.number().int().min(0),
  point_redeem_value: z.coerce.number().int().min(0),
});

const brandingSchema = z.object({
  name: z.string().min(1, "Name is required"),
  title: z.string().max(255),
});

type GeneralValues = z.infer<typeof generalSchema>;
type BrandingValues = z.infer<typeof brandingSchema>;
type SettingsTab = "general" | "branding";

export function SettingsPage() {
  const { user } = useAuth();
  const [tab, setTab] = useState<SettingsTab>("general");
  const canEdit = can(user, "settings.manage");

  const settingsQuery = useQuery({
    queryKey: ["settings"],
    queryFn: fetchSettings,
  });

  const settings = settingsQuery.data;

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Settings</h1>
          <p className={styles.subtitle}>Company defaults, branding, and approval thresholds.</p>
        </div>
      </header>

      <div className={styles.tabs} role="tablist" aria-label="Settings sections">
        <button
          className={tab === "general" ? `${styles.tab} ${styles.tabActive}` : styles.tab}
          type="button"
          role="tab"
          id="settings-tab-general"
          aria-selected={tab === "general"}
          aria-controls="settings-panel-general"
          onClick={() => setTab("general")}
        >
          General
        </button>
        <button
          className={tab === "branding" ? `${styles.tab} ${styles.tabActive}` : styles.tab}
          type="button"
          role="tab"
          id="settings-tab-branding"
          aria-selected={tab === "branding"}
          aria-controls="settings-panel-branding"
          onClick={() => setTab("branding")}
        >
          Branding
        </button>
      </div>

      {settingsQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
      {settingsQuery.isError ? <p className={styles.error}>{(settingsQuery.error as Error).message}</p> : null}

      {settings && tab === "general" ? (
        <div role="tabpanel" id="settings-panel-general" aria-labelledby="settings-tab-general">
          <GeneralPanel settings={settings} canEdit={canEdit} />
        </div>
      ) : null}

      {settings && tab === "branding" ? (
        <div role="tabpanel" id="settings-panel-branding" aria-labelledby="settings-tab-branding">
          <BrandingPanel settings={settings} canEdit={canEdit} />
        </div>
      ) : null}
    </div>
  );
}

function GeneralPanel({ settings, canEdit }: { settings: CompanySettings; canEdit: boolean }) {
  const queryClient = useQueryClient();
  const [formError, setFormError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<GeneralValues>({
    resolver: zodResolver(generalSchema),
  });

  useEffect(() => {
    reset({
      po_approval_threshold: settings.po_approval_threshold,
      adjustment_approval_threshold: settings.adjustment_approval_threshold,
      points_earn_per: settings.points_earn_per,
      point_redeem_value: settings.point_redeem_value,
    });
  }, [settings, reset]);

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

  return (
    <div className={styles.split}>
      <section className={styles.panel}>
        <h2 className={styles.panelTitle}>Current</h2>
        <div className={styles.detailGrid}>
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
              PO approval threshold (BDT)
              <input className={`${styles.input} mono`} type="number" step={1} {...register("po_approval_threshold")} />
              {errors.po_approval_threshold ? (
                <span className={styles.fieldError}>{errors.po_approval_threshold.message}</span>
              ) : null}
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
              <input className={`${styles.input} mono`} type="number" step={1} {...register("points_earn_per")} />
            </label>
            <label className={styles.label}>
              Point redeem value (BDT)
              <input className={`${styles.input} mono`} type="number" step={1} {...register("point_redeem_value")} />
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
  );
}

function BrandingPanel({ settings, canEdit }: { settings: CompanySettings; canEdit: boolean }) {
  const queryClient = useQueryClient();
  const [formError, setFormError] = useState<string | null>(null);
  const [assetError, setAssetError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<BrandingValues>({
    resolver: zodResolver(brandingSchema),
  });

  useEffect(() => {
    reset({
      name: settings.name,
      title: settings.title ?? "",
    });
  }, [settings, reset]);

  const refresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ["settings"] });
    await queryClient.invalidateQueries({ queryKey: brandingQueryKey });
  };

  const updateMutation = useMutation({
    mutationFn: updateSettings,
    onSuccess: async () => {
      await refresh();
      setFormError(null);
      setSaved(true);
    },
    onError: (err: Error) => {
      setSaved(false);
      setFormError(err.message);
    },
  });

  const logoMutation = useMutation({
    mutationFn: uploadLogo,
    onSuccess: refresh,
    onError: (err: Error) => setAssetError(err.message),
  });

  const faviconMutation = useMutation({
    mutationFn: uploadFavicon,
    onSuccess: refresh,
    onError: (err: Error) => setAssetError(err.message),
  });

  const deleteLogoMutation = useMutation({
    mutationFn: deleteLogo,
    onSuccess: refresh,
    onError: (err: Error) => setAssetError(err.message),
  });

  const deleteFaviconMutation = useMutation({
    mutationFn: deleteFavicon,
    onSuccess: refresh,
    onError: (err: Error) => setAssetError(err.message),
  });

  const assetsBusy =
    logoMutation.isPending || faviconMutation.isPending || deleteLogoMutation.isPending || deleteFaviconMutation.isPending;

  return (
    <div className={styles.split}>
      <section className={styles.panel}>
        <h2 className={styles.panelTitle}>Current</h2>
        <div className={styles.detailGrid}>
          <div className={styles.detailRow}>
            <span className={styles.detailLabel}>Name</span>
            <span>{settings.name}</span>
          </div>
          <div className={styles.detailRow}>
            <span className={styles.detailLabel}>Title</span>
            <span>{settings.title?.trim() ? settings.title : "Uses company name"}</span>
          </div>
          <div className={styles.detailRow}>
            <span className={styles.detailLabel}>Logo</span>
            {settings.logo_url ? (
              <img className={styles.thumb} src={settings.logo_url} alt="Company logo" />
            ) : (
              <span className={styles.muted}>None</span>
            )}
          </div>
          <div className={styles.detailRow}>
            <span className={styles.detailLabel}>Site icon</span>
            {settings.favicon_url ? (
              <img className={styles.thumb} src={settings.favicon_url} alt="Site icon" />
            ) : (
              <span className={styles.muted}>None</span>
            )}
          </div>
        </div>
      </section>

      {canEdit ? (
        <section className={styles.panel}>
          <h2 className={styles.panelTitle}>Edit</h2>
          <div className={styles.stack}>
          <form
            className={styles.form}
            onSubmit={handleSubmit((values) => {
              setSaved(false);
              setFormError(null);
              updateMutation.mutate({
                name: values.name,
                title: values.title.trim() === "" ? null : values.title.trim(),
              });
            })}
          >
            <label className={styles.label}>
              Company name
              <input className={styles.input} {...register("name")} />
              {errors.name ? <span className={styles.fieldError}>{errors.name.message}</span> : null}
            </label>
            <label className={styles.label}>
              Site title
              <input className={styles.input} placeholder="Uses company name when empty" {...register("title")} />
              {errors.title ? <span className={styles.fieldError}>{errors.title.message}</span> : null}
            </label>
            {formError ? <p className={styles.error}>{formError}</p> : null}
            {saved ? <p className={styles.muted}>Saved.</p> : null}
            <button className={styles.button} type="submit" disabled={isSubmitting || updateMutation.isPending}>
              {updateMutation.isPending ? "Saving…" : "Save"}
            </button>
          </form>

          <div className={styles.form}>
            <label className={styles.label}>
              Logo
              <div className={styles.assetRow}>
                {settings.logo_url ? <img className={styles.thumb} src={settings.logo_url} alt="" /> : null}
                <input
                  className={styles.input}
                  type="file"
                  accept="image/jpeg,image/png,image/webp,image/svg+xml,.svg"
                  disabled={assetsBusy}
                  onChange={(event) => {
                    const file = event.target.files?.[0];
                    event.target.value = "";
                    if (!file) {
                      return;
                    }
                    setAssetError(null);
                    logoMutation.mutate(file);
                  }}
                />
                {settings.logo_url ? (
                  <button
                    className={styles.buttonSecondary}
                    type="button"
                    disabled={assetsBusy}
                    onClick={() => {
                      setAssetError(null);
                      deleteLogoMutation.mutate();
                    }}
                  >
                    Remove
                  </button>
                ) : null}
              </div>
            </label>
            <label className={styles.label}>
              Site icon
              <div className={styles.assetRow}>
                {settings.favicon_url ? <img className={styles.thumb} src={settings.favicon_url} alt="" /> : null}
                <input
                  className={styles.input}
                  type="file"
                  accept="image/jpeg,image/png,image/webp,image/svg+xml,.svg,.ico,image/x-icon"
                  disabled={assetsBusy}
                  onChange={(event) => {
                    const file = event.target.files?.[0];
                    event.target.value = "";
                    if (!file) {
                      return;
                    }
                    setAssetError(null);
                    faviconMutation.mutate(file);
                  }}
                />
                {settings.favicon_url ? (
                  <button
                    className={styles.buttonSecondary}
                    type="button"
                    disabled={assetsBusy}
                    onClick={() => {
                      setAssetError(null);
                      deleteFaviconMutation.mutate();
                    }}
                  >
                    Remove
                  </button>
                ) : null}
              </div>
            </label>
            {assetError ? <p className={styles.error}>{assetError}</p> : null}
          </div>
          </div>
        </section>
      ) : (
        <p className={styles.muted}>Editing settings requires settings.manage.</p>
      )}
    </div>
  );
}
