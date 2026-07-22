"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Eye, FileDown } from "lucide-react";
import {
  AttendanceReportFiltersForm,
  type AttendanceReportFilters,
} from "@/components/reports/attendance-report-filters";
import { ReportExportsPanel } from "@/components/reports/report-exports-panel";
import { ReportSchoolHeading } from "@/components/reports/report-school-heading";
import { SummaryCard } from "@/components/dashboard/summary-card";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
import { useAuth } from "@/lib/auth/auth-context";
import { apiClient } from "@/lib/api/client";
import { useSchool } from "@/lib/tenant/school-context";
import { canViewReports, formatDate, roleHomePath } from "@/lib/utils";
import type { SchoolClass } from "@/types";

type AttendanceWeekRow = {
  week_start: string;
  week_end: string;
  week_label: string;
  school_id: number;
  school_name: string;
  academic_year?: string | null;
  term: number;
  teacher_on_duty: string;
  present: number;
  absent: number;
  excused: number;
  late: number;
  records: number;
  generated_on?: string | null;
};

type ReportSummaryResponse = {
  totals: {
    records: number;
    present: number;
    absent: number;
    late: number;
    excused: number;
    attendance_rate: number;
  };
};

type ExportResponse = {
  message: string;
  status?: "completed" | "queued";
};

function defaultFilters(): AttendanceReportFilters {
  const to = new Date();
  const from = new Date();
  from.setDate(from.getDate() - 28);

  return {
    academic_year: "",
    term: "",
    week_start: "",
    class_id: "",
    from: from.toISOString().slice(0, 10),
    to: to.toISOString().slice(0, 10),
  };
}

function buildParams(filters: AttendanceReportFilters) {
  return {
    from: filters.from || undefined,
    to: filters.to || undefined,
    class_id: filters.class_id || undefined,
    academic_year: filters.academic_year || undefined,
    term: filters.term ? Number(filters.term) : undefined,
    week_start: filters.week_start || undefined,
  };
}

export default function AttendanceReportsPage() {
  const router = useRouter();
  const { user, loading } = useAuth();
  const { currentSchool, revision } = useSchool();
  const canView = canViewReports(user?.role?.slug);
  const [classes, setClasses] = useState<SchoolClass[]>([]);
  const [filters, setFilters] = useState<AttendanceReportFilters>(defaultFilters);
  const [appliedFilters, setAppliedFilters] = useState<AttendanceReportFilters>(filters);
  const [weeks, setWeeks] = useState<AttendanceWeekRow[]>([]);
  const [summary, setSummary] = useState<ReportSummaryResponse | null>(null);
  const [loadingRows, setLoadingRows] = useState(true);
  const [exportMessage, setExportMessage] = useState<string | null>(null);
  const [exportError, setExportError] = useState<string | null>(null);
  const [exportPolling, setExportPolling] = useState(false);
  const [exportBusy, setExportBusy] = useState<"xlsx" | "pdf" | null>(null);
  const [exportsRefreshKey, setExportsRefreshKey] = useState(0);

  const loadData = useCallback(async (nextFilters: AttendanceReportFilters) => {
    setLoadingRows(true);
    try {
      const params = buildParams(nextFilters);
      const [weeksRes, summaryRes] = await Promise.all([
        apiClient.get<{ data: AttendanceWeekRow[] }>("/reports/attendance-weeks", { params }),
        apiClient.get<ReportSummaryResponse>("/reports/attendance-summary", { params }),
      ]);
      setWeeks(weeksRes.data.data);
      setSummary(summaryRes.data);
    } catch {
      setWeeks([]);
      setSummary(null);
    } finally {
      setLoadingRows(false);
    }
  }, []);

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
    void apiClient
      .get<{ data: SchoolClass[] }>("/classes")
      .then((response) => setClasses(response.data.data))
      .catch(() => setClasses([]));
  }, [canView, revision]);

  useEffect(() => {
    if (!canView) {
      return;
    }
    void loadData(appliedFilters);
  }, [appliedFilters, canView, loadData, revision]);

  const academicYears = useMemo(() => {
    const years = new Set<string>();
    for (const item of classes) {
      if (item.academic_year) {
        years.add(item.academic_year);
      }
    }
    for (const week of weeks) {
      if (week.academic_year) {
        years.add(week.academic_year);
      }
    }
    return Array.from(years).sort().reverse();
  }, [classes, weeks]);

  const weekOptions = useMemo(() => {
    const byStart = new Map<string, { value: string; label: string }>();
    for (const week of weeks) {
      if (!byStart.has(week.week_start)) {
        byStart.set(week.week_start, {
          value: week.week_start,
          label: week.week_label,
        });
      }
    }
    return Array.from(byStart.values());
  }, [weeks]);

  const applyFilters = async () => {
    setAppliedFilters(filters);
    await loadData(filters);
  };

  const queueExport = async (format: "xlsx" | "pdf") => {
    setExportBusy(format);
    setExportError(null);
    setExportMessage(null);

    try {
      const response = await apiClient.get<ExportResponse>("/reports/export", {
        params: { ...buildParams(appliedFilters), format },
      });

      if (response.data.status === "completed") {
        setExportMessage(response.data.message);
        setExportsRefreshKey((value) => value + 1);
      } else {
        setExportMessage(`${format.toUpperCase()} export queued. It will appear below when ready.`);
        setExportPolling(true);
      }
    } catch {
      setExportError("Unable to generate the export. Try again or confirm the queue worker is running.");
    } finally {
      setExportBusy(null);
    }
  };

  if (!canView) {
    return null;
  }

  return (
    <div className="space-y-6">
      <section>
        <ReportSchoolHeading
          school={currentSchool}
          title="Attendance Reports"
          subtitle={`Review weekly roll call history and export daily or weekly attendance as PDF or Excel${
            currentSchool
              ? ` for ${currentSchool.name}`
              : ". Select a school in the header to scope results"
          }.`}
        />
      </section>

      <Card className="p-5">
        <AttendanceReportFiltersForm
          classes={classes}
          academicYears={academicYears}
          weeks={weekOptions}
          value={filters}
          onChange={setFilters}
          onApply={() => void applyFilters()}
          exportBusy={exportBusy}
          onExport={(format) => void queueExport(format)}
        />
        {exportMessage ? (
          <p className="mt-3 text-sm text-emerald-700 dark:text-emerald-300">{exportMessage}</p>
        ) : null}
        {exportError ? (
          <p className="mt-3 rounded-lg border border-amber-300/60 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
            {exportError}
          </p>
        ) : null}
      </Card>

      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <SummaryCard label="Records" value={summary?.totals.records ?? 0} />
        <SummaryCard label="Present" value={summary?.totals.present ?? 0} />
        <SummaryCard label="Absent" value={summary?.totals.absent ?? 0} />
        <SummaryCard label="Excused" value={summary?.totals.excused ?? 0} />
        <SummaryCard label="Attendance rate" value={`${summary?.totals.attendance_rate ?? 0}%`} />
      </section>

      <Card className="overflow-hidden">
        <div className="border-b border-[rgba(148,163,184,0.18)] px-5 py-4">
          <h2 className="text-base font-semibold text-foreground">Weekly roll call history</h2>
          <p className="mt-1 text-sm text-(--text-muted)">
            Daily and weekly roll call summaries generated from closed attendance sessions.
          </p>
        </div>

        {loadingRows ? (
          <div className="flex items-center justify-center gap-3 p-10">
            <Spinner />
            <span className="text-sm text-(--text-muted)">Loading attendance weeks…</span>
          </div>
        ) : weeks.length === 0 ? (
          <p className="p-8 text-center text-sm text-(--text-muted)">
            No attendance weeks match these filters.
          </p>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="bg-(--surface-muted) text-xs uppercase tracking-wide text-(--text-muted)">
                <tr>
                  <th className="px-4 py-3 font-medium">Week</th>
                  <th className="px-4 py-3 font-medium">School</th>
                  <th className="px-4 py-3 font-medium">Teacher on duty</th>
                  <th className="px-4 py-3 font-medium">Present</th>
                  <th className="px-4 py-3 font-medium">Absent</th>
                  <th className="px-4 py-3 font-medium">Excused</th>
                  <th className="px-4 py-3 font-medium">Generated on</th>
                  <th className="px-4 py-3 font-medium">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[rgba(148,163,184,0.12)]">
                {weeks.map((week) => (
                  <tr key={`${week.week_start}-${week.school_id}`}>
                    <td className="px-4 py-3 font-medium text-foreground">{week.week_label}</td>
                    <td className="px-4 py-3 text-(--text-muted)">{week.school_name}</td>
                    <td className="px-4 py-3 text-foreground">{week.teacher_on_duty}</td>
                    <td className="px-4 py-3">{week.present}</td>
                    <td className="px-4 py-3">{week.absent}</td>
                    <td className="px-4 py-3">{week.excused}</td>
                    <td className="px-4 py-3 text-(--text-muted)">{formatDate(week.generated_on)}</td>
                    <td className="px-4 py-3">
                      <div className="flex flex-wrap gap-2">
                        <Button
                          type="button"
                          size="sm"
                          variant="outline"
                          onClick={() => {
                            setFilters((current) => ({
                              ...current,
                              from: week.week_start,
                              to: week.week_end,
                              week_start: week.week_start,
                            }));
                            setAppliedFilters((current) => ({
                              ...current,
                              from: week.week_start,
                              to: week.week_end,
                              week_start: week.week_start,
                            }));
                          }}
                        >
                          <Eye size={14} className="mr-1" />
                          View
                        </Button>
                        <Button
                          type="button"
                          size="sm"
                          variant="ghost"
                          onClick={() => {
                            const weekFilters = {
                              ...appliedFilters,
                              from: week.week_start,
                              to: week.week_end,
                              week_start: week.week_start,
                            };
                            setFilters(weekFilters);
                            setAppliedFilters(weekFilters);
                            void (async () => {
                              setExportBusy("pdf");
                              setExportError(null);
                              setExportMessage(null);
                              try {
                                const response = await apiClient.get<ExportResponse>("/reports/export", {
                                  params: { ...buildParams(weekFilters), format: "pdf" },
                                });
                                if (response.data.status === "completed") {
                                  setExportMessage(response.data.message);
                                  setExportsRefreshKey((value) => value + 1);
                                } else {
                                  setExportMessage("PDF export queued. It will appear below when ready.");
                                  setExportPolling(true);
                                }
                              } catch {
                                setExportError("Unable to generate the export.");
                              } finally {
                                setExportBusy(null);
                              }
                            })();
                          }}
                        >
                          <FileDown size={14} className="mr-1" />
                          PDF
                        </Button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      <ReportExportsPanel
        refreshKey={exportsRefreshKey}
        pollForNewExport={exportPolling}
        onPollComplete={() => setExportPolling(false)}
      />

      <p className="text-xs text-(--text-muted)">
        Looking for published weekly duty assignments? Open{" "}
        <Link href="/reports/duty-roster" className="font-medium text-(--color-primary) hover:underline">
          Duty Roster Reports
        </Link>
        .
      </p>
    </div>
  );
}
