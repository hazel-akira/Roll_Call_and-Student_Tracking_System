import { SELECTED_SCHOOL_STORAGE_KEY } from "@/lib/api/client";

/** Stored in localStorage when admin/ICT views aggregate data across all schools. */
export const ALL_SCHOOLS_VALUE = "__all__";

export function readSelectedSchoolId(): string | null {
  if (typeof window === "undefined") {
    return null;
  }

  const value = window.localStorage.getItem(SELECTED_SCHOOL_STORAGE_KEY);

  return value && value.length > 0 ? value : null;
}

export function writeSelectedSchoolId(schoolId: string) {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(SELECTED_SCHOOL_STORAGE_KEY, schoolId);
}

export function clearSelectedSchoolId() {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.removeItem(SELECTED_SCHOOL_STORAGE_KEY);
}

export function isAllSchoolsSelection(schoolId: string | null | undefined): boolean {
  return schoolId === ALL_SCHOOLS_VALUE || schoolId === null || schoolId === "";
}
