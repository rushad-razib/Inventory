import type { AuthUser } from "./api";

export function can(user: AuthUser | null | undefined, permission: string): boolean {
  if (!user) {
    return false;
  }

  return user.permissions.includes(permission);
}
