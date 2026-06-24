"use client";

import { Building2 } from "lucide-react";
import { Spinner } from "@/components/ui/spinner";
import { ALL_SCHOOLS_VALUE } from "@/lib/tenant/school-storage";
import { useSchool } from "@/lib/tenant/school-context";

export function SchoolSelector() {
  const {
    schools,
    schoolId,
    currentSchool,
    viewingAllSchools,
    canSelectAllSchools,
    loading,
    error,
    canSwitchSchool,
    selectSchool,
  } = useSchool();

  if (loading) {
    return (
      <div className="flex h-9 items-center gap-2 rounded-xl border border-[rgba(148,163,184,0.28)] bg-(--surface-solid) px-3 text-sm text-(--text-muted)">
        <Spinner />
        Loading schools...
      </div>
    );
  }

  if (error) {
    return (
      <div
        className="max-w-xs rounded-xl border border-amber-300/60 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100"
        title={error}
      >
        {error}
      </div>
    );
  }

  if (schools.length === 0) {
    return null;
  }

  if (!canSwitchSchool) {
    const label = viewingAllSchools ? "All schools" : currentSchool?.name;
    if (!label) {
      return null;
    }

    return (
      <div className="flex h-9 max-w-xs items-center gap-2 rounded-xl border border-[rgba(148,163,184,0.28)] bg-(--surface-solid) px-3 text-sm text-foreground">
        <Building2 size={16} className="shrink-0 text-(--color-accent-dark)" />
        <span className="truncate font-medium">{label}</span>
      </div>
    );
  }

  const selectValue = viewingAllSchools ? ALL_SCHOOLS_VALUE : (schoolId ?? "");

  return (
    <div className="flex items-center gap-2">
      <Building2 size={16} className="hidden text-(--color-accent-dark) sm:block" />
      <select
        aria-label="Select school"
        className="h-9 max-w-[16rem] field-control"
        value={selectValue}
        onChange={(event) => {
          void selectSchool(event.target.value);
        }}
      >
        {canSelectAllSchools ? (
          <option value={ALL_SCHOOLS_VALUE}>All schools</option>
        ) : null}
        {schools.map((school) => (
          <option key={school.id} value={school.id}>
            {school.name}
          </option>
        ))}
      </select>
    </div>
  );
}
