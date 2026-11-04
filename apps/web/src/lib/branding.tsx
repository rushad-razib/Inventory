import { useQuery } from "@tanstack/react-query";
import { useEffect } from "react";
import { fetchBranding, type Branding } from "./api";

export const brandingQueryKey = ["branding"] as const;

export function brandingLabel(branding: Branding | undefined): string {
  const title = branding?.title?.trim();
  if (title) {
    return title;
  }
  if (branding?.name) {
    return branding.name;
  }
  return "Meridian";
}

export function useBranding() {
  return useQuery({
    queryKey: brandingQueryKey,
    queryFn: fetchBranding,
    staleTime: 5 * 60 * 1000,
  });
}

export function BrandingEffect() {
  const brandingQuery = useBranding();

  useEffect(() => {
    const branding = brandingQuery.data;
    if (!branding) {
      return;
    }

    document.title = brandingLabel(branding);

    let link = document.querySelector<HTMLLinkElement>("link[rel='icon']");
    if (!link) {
      link = document.createElement("link");
      link.rel = "icon";
      document.head.appendChild(link);
    }

    if (branding.favicon_url) {
      link.href = branding.favicon_url;
      link.removeAttribute("type");
      return;
    }

    link.type = "image/svg+xml";
    link.href = "/favicon.svg";
  }, [brandingQuery.data]);

  return null;
}
