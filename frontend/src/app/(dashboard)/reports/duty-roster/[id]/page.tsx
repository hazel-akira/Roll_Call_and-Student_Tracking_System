"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import {
  ArrowLeft,
  ClipboardList,
  Download,
  FileSpreadsheet,
  Printer,
  UserRoundCheck,
  UserRoundX,
  Users,
} from "lucide-react";
import { SummaryCard } from "@/components/dashboard/summary-card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
import { useAuth } from "@/lib/auth/auth-context";
import { apiClient } from "@/lib/api/client";
import { downloadBlobFile } from "@/lib/reports/blob-file";
import { schoolLogoSrc } from "@/lib/reports/school-logo";
import { useSchool } from "@/lib/tenant/school-context";
import { canManageDutyRoster, canViewReports, cn, formatDate, roleHomePath } from "@/lib/utils";
import type { WeeklyDutyRoster } from "@/types";

async function downloadDutyRosterExport(rosterId: number, format: "pdf" | "xlsx") {
  const response = await apiClient.get<Blob>(`/reports/duty-rosters/${rosterId}/export`, {
    params: { format },
    responseType: "blob",
  });

  const mimeType =
    format === "pdf"
      ? "application/pdf"
      : "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";

  const disposition = response.headers["content-disposition"] as string | undefined;
  const match = disposition ? /filename="?([^";\n]+)"?/i.exec(disposition) : null;

  downloadBlobFile({
    blob: new Blob([response.data], { type: mimeType }),
    filename: match?.[1] ?? `duty-roster.${format}`,
    mimeType,
  });
}

export default function DutyRosterReportDetailPage() {
  const params = useParams<{ id: string }>();
  const router = useRouter();
  const { user, loading } = useAuth();
  const { currentSchool, revision } = useSchool();
  const canView = canViewReports(user?.role?.slug);
  const canEdit = canManageDutyRoster(user?.role?.slug);
  const [roster, setRoster] = useState<WeeklyDutyRoster | null>(null);
  const [loadingRoster, setLoadingRoster] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [exportBusy, setExportBusy] = useState<"pdf" | "xlsx" | null>(null);

  const loadRoster = useCallback(async () => {
    if (!params.id) {
      return;
    }

    setLoadingRoster(true);
    setError(null);

    try {
      const response = await apiClient.get<{ data: WeeklyDutyRoster }>(
        `/reports/duty-rosters/${params.id}`,
      );
      setRoster(response.data.data);
    } catch {
      setRoster(null);
      setError("Unable to load this duty roster report.");
    } finally {
      setLoadingRoster(false);
    }
  }, [params.id]);

  useEffect(() => {
    if (loading || !user) {
      return;
    }
    if (!canView) {
      router.replace(roleHomePath(user.role?.slug));
    }
  }, [canView, loading, router, user]);

  useEffect(() => {
    if (!canView) {
      return;
    }
    void loadRoster();
  }, [canView, loadRoster, revision]);

  const sections = useMemo(() => {
    if (!roster) {
      return [];
    }

    return (
      roster.sections ??
      Object.entries(
        roster.entries.reduce<Record<string, typeof roster.entries>>((acc, entry) => {
          const label = entry.category_label ?? entry.category;
          acc[label] = [...(acc[label] ?? []), entry];
          return acc;
        }, {}),
      ).map(([title, entries]) => ({
        title,
        rows: entries.map((entry) => ({
          location: entry.location,
          time_slot: entry.time_slot,
          staff: entry.staff.map((member) => member.name).join(", "),
        })),
      }))
    );
  }, [roster]);

  const stats = useMemo(() => {
    const rows = sections.flatMap((section) => section.rows);
    const total = rows.length;
    const assigned = rows.filter((row) => row.staff.trim() !== "").length;
    const unassigned = total - assigned;
    const uniqueStaff = new Set(
      rows
        .flatMap((row) => row.staff.split(","))
        .map((name) => name.trim())
        .filter(Boolean),
    ).size;
    const sectionsComplete = sections.filter((section) =>
      section.rows.every((row) => row.staff.trim() !== ""),
    ).length;

    return {
      total,
      assigned,
      unassigned,
      uniqueStaff,
      sectionsComplete,
      sectionsTotal: sections.length,
      percent: total > 0 ? Math.round((assigned / total) * 100) : 0,
    };
  }, [sections]);

  const exportRoster = async (format: "pdf" | "xlsx") => {
    if (!roster) {
      return;
    }

    setExportBusy(format);
    setError(null);

    try {
      await downloadDutyRosterExport(roster.id, format);
    } catch {
      setError(`Unable to export ${format.toUpperCase()}.`);
    } finally {
      setExportBusy(null);
    }
  };

  if (!canView) {
    return null;
  }

  if (loadingRoster) {
    return (
      <Card className="flex items-center justify-center gap-3 p-10">
        <Spinner />
        <span className="text-sm text-(--text-muted)">Loading duty roster report…</span>
      </Card>
    );
  }

  if (!roster) {
    return (
      <Card className="space-y-4 p-8 text-center">
        <p className="text-sm text-(--text-muted)">{error ?? "Roster not found."}</p>
        <Button type="button" variant="outline" onClick={() => router.push("/reports/duty-roster")}>
          Back to duty roster reports
        </Button>
      </Card>
    );
  }

  const schoolName = currentSchool?.name ?? "School";
  const logoSrc = schoolLogoSrc(currentSchool);

  return (
    <div className="space-y-6">
      <section className="print:hidden">
        <Link
          href="/reports/duty-roster"
          className="mb-4 inline-flex items-center gap-1 text-sm font-medium text-(--text-muted) transition hover:text-foreground"
        >
          <ArrowLeft size={14} />
          Duty Roster Reports
        </Link>

        <Card className="overflow-hidden border border-[rgba(148,163,184,0.16)]">
          <div className="flex flex-wrap items-start justify-between gap-5 border-b border-[rgba(148,163,184,0.14)] bg-[linear-gradient(135deg,color-mix(in_srgb,var(--color-primary)_12%,transparent),transparent_55%)] px-5 py-5">
            <div className="flex items-start gap-4">
              <div className="flex h-16 w-16 items-center justify-center rounded-2xl border border-[rgba(148,163,184,0.2)] bg-(--surface-muted) p-2">
                <img
                  src={logoSrc}
                  alt={`${schoolName} logo`}
                  className="h-full w-full object-contain"
                />
              </div>
              <div>
                <p className="page-eyebrow">Weekly duty roster</p>
                <div className="mt-1 flex flex-wrap items-center gap-3">
                  <h1 className="page-title mt-0">{roster.week_label}</h1>
                  <Badge value={roster.status} />
                </div>
                <p className="mt-2 text-sm text-(--text-muted)">
                  {schoolName}
                  {roster.published_by_name ? ` · Published by ${roster.published_by_name}` : ""}
                  {roster.published_at ? ` on ${formatDate(roster.published_at)}` : ""}
                </p>
              </div>
            </div>

            <div className="flex flex-wrap gap-2">
              <Button type="button" variant="outline" onClick={() => window.print()}>
                <Printer size={16} className="mr-2" />
                Print
              </Button>
              <Button
                type="button"
                variant="outline"
                disabled={exportBusy !== null}
                onClick={() => void exportRoster("pdf")}
              >
                <Download size={16} className="mr-2" />
                {exportBusy === "pdf" ? "Downloading…" : "Download PDF"}
              </Button>
              <Button
                type="button"
                variant="outline"
                disabled={exportBusy !== null}
                onClick={() => void exportRoster("xlsx")}
              >
                <FileSpreadsheet size={16} className="mr-2" />
                {exportBusy === "xlsx" ? "Exporting…" : "Export Excel"}
              </Button>
              {canEdit ? (
                <Button type="button" variant="secondary" onClick={() => router.push("/duty-roster")}>
                  {roster.status === "published" ? "Edit in module" : "Continue editing"}
                </Button>
              ) : null}
            </div>
          </div>

          <div className="px-5 py-4">
            <div className="mb-3 flex items-center justify-between gap-3">
              <p className="text-sm font-medium text-foreground">Assignment overview</p>
              <p className="text-sm text-(--text-muted)">{stats.percent}% filled</p>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-(--surface-muted)">
              <div
                className={cn(
                  "h-full rounded-full transition-all",
                  stats.percent === 100 ? "bg-emerald-500" : "bg-(--color-primary)",
                )}
                style={{ width: `${stats.percent}%` }}
              />
            </div>
          </div>
        </Card>
      </section>

      {error ? (
        <p className="rounded-lg border border-amber-300/60 bg-amber-50 px-3 py-2 text-sm text-amber-900 print:hidden dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
          {error}
        </p>
      ) : null}

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 print:hidden">
        <SummaryCard
          label="Duty slots"
          value={stats.total}
          helper={`${stats.sectionsTotal} sections in this week`}
        />
        <SummaryCard
          label="Assigned"
          value={stats.assigned}
          helper={`${stats.percent}% of slots filled`}
        />
        <SummaryCard
          label="Unassigned"
          value={stats.unassigned}
          helper={stats.unassigned === 0 ? "All slots covered" : "Still need staff"}
        />
        <SummaryCard
          label="Staff on roster"
          value={stats.uniqueStaff}
          helper={`${stats.sectionsComplete}/${stats.sectionsTotal} sections complete`}
        />
      </section>

      <div className="hidden print:mb-4 print:block">
        <div className="mb-4 flex items-center gap-4 border-b-2 border-[#1e3a5f] pb-3">
          <img src={logoSrc} alt={`${schoolName} logo`} className="h-16 w-16 object-contain" />
          <div>
            <p className="text-sm font-bold uppercase tracking-wide text-slate-900">{schoolName}</p>
            <h1 className="text-lg font-semibold uppercase text-[#1e3a5f]">Weekly Duty Roster</h1>
            <p className="text-sm text-slate-600">{roster.week_label}</p>
          </div>
        </div>
      </div>

      <section className="space-y-4">
        <div className="flex flex-wrap items-end justify-between gap-3 print:hidden">
          <div>
            <h2 className="section-title">Duty assignments</h2>
            <p className="mt-1 text-sm text-(--text-muted)">
              Staff coverage by section, location, and time slot.
            </p>
          </div>
          <div className="flex flex-wrap gap-3 text-xs text-(--text-muted)">
            <span className="inline-flex items-center gap-1.5">
              <UserRoundCheck size={14} className="text-emerald-500" />
              Assigned
            </span>
            <span className="inline-flex items-center gap-1.5">
              <UserRoundX size={14} className="text-amber-500" />
              Unassigned
            </span>
          </div>
        </div>

        <div className="space-y-4">
          {sections.map((section) => {
            const sectionAssigned = section.rows.filter((row) => row.staff.trim() !== "").length;
            const sectionComplete = sectionAssigned === section.rows.length;

            return (
              <Card
                key={section.title}
                className="overflow-hidden border border-[rgba(148,163,184,0.16)] print:border print:border-slate-300 print:shadow-none"
              >
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[rgba(148,163,184,0.14)] bg-(--surface-muted)/70 px-5 py-3">
                  <div className="flex items-center gap-2">
                    <span className="flex h-8 w-8 items-center justify-center rounded-xl bg-(--surface-solid) text-(--color-primary) shadow-sm">
                      <ClipboardList size={15} />
                    </span>
                    <div>
                      <h3 className="text-sm font-semibold uppercase tracking-[0.08em] text-foreground">
                        {section.title}
                      </h3>
                      <p className="text-xs text-(--text-muted)">
                        {sectionAssigned}/{section.rows.length} slots assigned
                      </p>
                    </div>
                  </div>
                  <span
                    className={cn(
                      "rounded-full px-2.5 py-1 text-xs font-medium",
                      sectionComplete
                        ? "bg-emerald-500/15 text-emerald-700 dark:text-emerald-300"
                        : "bg-amber-500/15 text-amber-800 dark:text-amber-200",
                    )}
                  >
                    {sectionComplete ? "Complete" : "In progress"}
                  </span>
                </div>

                <div className="overflow-x-auto">
                  <table className="min-w-full text-left text-sm">
                    <thead>
                      <tr className="border-b border-[rgba(148,163,184,0.14)] text-xs uppercase tracking-wide text-(--text-muted)">
                        <th className="px-5 py-3 font-semibold">Location</th>
                        <th className="px-5 py-3 font-semibold">Time slot</th>
                        <th className="px-5 py-3 font-semibold">
                          <span className="inline-flex items-center gap-1.5">
                            <Users size={13} />
                            Staff
                          </span>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      {section.rows.map((row, index) => {
                        const assigned = row.staff.trim() !== "";

                        return (
                          <tr
                            key={`${section.title}-${index}`}
                            className={cn(
                              "border-b border-[rgba(148,163,184,0.1)] last:border-b-0",
                              index % 2 === 1 ? "bg-(--surface-muted)/45" : "bg-transparent",
                            )}
                          >
                            <td className="px-5 py-3 font-medium text-foreground">
                              {row.location || "General"}
                            </td>
                            <td className="px-5 py-3 text-(--text-muted)">
                              {row.time_slot || "All day"}
                            </td>
                            <td className="px-5 py-3">
                              {assigned ? (
                                <span className="font-medium text-foreground">{row.staff}</span>
                              ) : (
                                <span className="inline-flex items-center gap-1.5 rounded-lg bg-amber-500/10 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">
                                  <UserRoundX size={12} />
                                  Unassigned
                                </span>
                              )}
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              </Card>
            );
          })}
        </div>
      </section>

      {roster.status === "published" ? (
        <p className="text-xs text-(--text-muted) print:hidden">
          This published roster is read-only here. To change assignments, open the Duty roster module, edit, save
          as draft, then publish again.
        </p>
      ) : (
        <p className="text-xs text-(--text-muted) print:hidden">
          This roster is still a draft. Finish assignments in the Duty roster module before publishing.
        </p>
      )}
    </div>
  );
}
