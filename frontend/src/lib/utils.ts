import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function formatDate(value?: string | null) {
  if (!value) return "-";

  return new Intl.DateTimeFormat("en", {
    dateStyle: "medium",
    timeStyle: value.includes("T") ? "short" : undefined,
  }).format(new Date(value));
}

export function roleHomePath(roleSlug?: string | null) {
  if (roleSlug === "admin" || roleSlug === "ict_staff") {
    return "/admin";
  }

  if (roleSlug === "dean_of_students" || roleSlug === "deputy_dean") {
    return "/duty-roster";
  }

  return "/teacher";
}

export function isDeanRole(roleSlug?: string | null) {
  return roleSlug === "dean_of_students" || roleSlug === "deputy_dean";
}

export function canManageDutyRoster(roleSlug?: string | null) {
  return (
    roleSlug === "admin" ||
    roleSlug === "ict_staff" ||
    isDeanRole(roleSlug)
  );
}

export function canViewReports(roleSlug?: string | null) {
  return (
    roleSlug === "admin" ||
    roleSlug === "ict_staff" ||
    isDeanRole(roleSlug)
  );
}
