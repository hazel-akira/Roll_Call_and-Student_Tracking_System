"use client";

import { schoolLogoSrc } from "@/lib/reports/school-logo";
import type { School } from "@/types";

export function ReportSchoolHeading({
  school,
  title,
  subtitle,
}: {
  school?: School | null;
  title: string;
  subtitle?: string | null;
}) {
  const logoSrc = schoolLogoSrc(school);
  const schoolName = school?.name ?? "All schools";

  return (
    <div className="flex items-start gap-4">
      <img
        src={logoSrc}
        alt={`${schoolName} logo`}
        className="h-14 w-14 shrink-0 object-contain"
      />
      <div className="min-w-0">
        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-(--text-muted)">
          {schoolName}
        </p>
        <h1 className="page-title mt-1">{title}</h1>
        {subtitle ? <p className="mt-2 max-w-3xl text-sm text-(--text-muted)">{subtitle}</p> : null}
      </div>
    </div>
  );
}
