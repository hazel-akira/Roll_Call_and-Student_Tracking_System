import type { AppUser } from "@/types";

export function needsSchoolOnboarding(user: AppUser | null | undefined): boolean {
  if (!user || user.status !== "active") {
    return false;
  }

  if (user.role?.slug !== "teacher") {
    return false;
  }

  return !user.schools || user.schools.length === 0;
}
