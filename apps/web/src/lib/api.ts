import createClient from "openapi-fetch";
import type { paths } from "./api-types";

/** Empty string uses the Vite proxy in development (same-origin cookies). */
const API_BASE = import.meta.env.VITE_API_URL ?? "";

export type AuthUser = {
  id: string;
  name: string;
  email: string;
  roles: string[];
  permissions: string[];
  department: string | null;
  warehouse_id: string | null;
};

const client = createClient<paths>({
  baseUrl: `${API_BASE}/api`,
  credentials: "include",
});

client.use({
  onRequest({ request }) {
    request.headers.set("Accept", "application/json");
    request.headers.set("X-Requested-With", "XMLHttpRequest");

    const token = xsrfToken();
    if (token) {
      request.headers.set("X-XSRF-TOKEN", token);
    }

    return request;
  },
});

async function ensureCsrfCookie(): Promise<void> {
  await fetch(`${API_BASE}/sanctum/csrf-cookie`, {
    method: "GET",
    credentials: "include",
  });
}

function xsrfToken(): string | null {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : null;
}

function toAuthUser(user: {
  id: string;
  name: string;
  email: string;
  roles: string[];
  permissions: readonly string[];
  department: string | null;
  warehouse_id: string | null;
}): AuthUser {
  return {
    id: user.id,
    name: user.name,
    email: user.email,
    roles: [...user.roles],
    permissions: [...user.permissions],
    department: user.department,
    warehouse_id: user.warehouse_id,
  };
}

function problemMessage(error: unknown, status: number): string {
  if (error && typeof error === "object") {
    const record = error as { detail?: string; title?: string; message?: string };
    if (record.detail) {
      return record.detail;
    }
    if (record.title) {
      return record.title;
    }
    if (record.message) {
      return record.message;
    }
  }

  return `Request failed (${status})`;
}

export async function login(email: string, password: string): Promise<AuthUser> {
  await ensureCsrfCookie();

  const { data, error, response } = await client.POST("/v1/auth/login", {
    body: { email, password },
  });

  if (error || !data?.data) {
    throw new Error(problemMessage(error, response.status));
  }

  // Scramble emits narrow permission/null shapes; normalize at the boundary.
  return toAuthUser(data.data as unknown as AuthUser);
}

export async function logout(): Promise<void> {
  const { error, response } = await client.POST("/v1/auth/logout");

  if (error) {
    throw new Error(problemMessage(error, response.status));
  }
}

export async function fetchMe(): Promise<AuthUser> {
  const { data, error, response } = await client.GET("/v1/auth/me");

  if (error || !data?.data) {
    throw new Error(problemMessage(error, response.status));
  }

  return toAuthUser(data.data as unknown as AuthUser);
}
