"use client";

import { useCallback, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { ReportExportsPanel } from "@/components/reports/report-exports-panel";
import { ReportFilters, type ReportFilters as FilterState } from "@/components/reports/report-filters";
import { SummaryCard } from "@/components/dashboard/summary-card";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { useAuth } from "@/lib/auth/auth-context";
import { apiClient } from "@/lib/api/client";
import { useSchool } from "@/lib/tenant/school-context";
import { roleHomePath } from "@/lib/utils";
import type { SchoolClass } from "@/types";

type ReportSummaryResponse = {
  totals: {
    records: number;
    present: number;
    absent: number;
    late: number;
    excused: number;
    attendance_rate: number;
  };
  daily_breakdown: Array<{
    session_date: string;
    status: string;
    aggregate: number;
  }>;
};

export default function ReportsPage() {
  const router = useRouter();
  const { user, loading } = useAuth();
  const { currentSchool, revision } = useSchool();
  const [classes, setClasses] = useState<SchoolClass[]>([]);
  const [filters, setFilters] = useState<FilterState>({
    from: new Date().toISOString().slice(0, 10),
    to: new Date().toISOString().slice(0, 10),
    class_id: "",
  });
  const [summary, setSummary] = useState<ReportSummaryResponse | null>(null);
  const [exportMessage, setExportMessage] = useState<string | null>(null);
  const [exportError, setExportError] = useState<string | null>(null);
  const [exportPolling, setExportPolling] = useState(false);
  const [exportBusy, setExportBusy] = useState<"xlsx" | "pdf" | null>(null);
  const canViewReports = user?.role?.slug === "admin" || user?.role?.slug === "ict_staff";

  const reportParams = useCallback(
    () => ({
      from: filters.from,
      to: filters.to,
      class_id: filters.class_id || undefined,
    }),
    [filters],
  );

  const queueExport = useCallback(
    async (format: "xlsx" | "pdf") => {
      setExportBusy(format);
      setExportError(null);
      setExportMessage(null);

      try {
        await apiClient.get("/reports/export", {
          params: { ...reportParams(), format },
        });
        setExportMessage(
          `${format.toUpperCase()} export queued. It will appear below when ready.`,
        );
        setExportPolling(true);
      } catch {
        setExportError("Unable to queue the export. Confirm the queue worker is running.");
      } finally {
        setExportBusy(null);
      }
    },
    [reportParams],
  );

  useEffect(() => {
    if (loading || !user) {
      return;
    }

    if (!canViewReports) {
      router.replace(roleHomePath(user.role?.slug));
    }
  }, [canViewReports, loading, router, user]);

  useEffect(() => {
    if (!canViewReports) {
      return;
    }

    void apiClient
      .get<{ data: SchoolClass[] }>("/classes")
      .then((response) => {
        setClasses(response.data.data);
      })
      .catch(() => {
        setClasses([]);
      });
  }, [canViewReports, revision]);

  const loadReports = useCallback(async () => {
    const params = reportParams();

    try {
      const summaryResponse = await apiClient.get<ReportSummaryResponse>(
        "/reports/attendance-summary",
        { params },
      );

      setSummary(summaryResponse.data);
    } catch {
      setSummary(null);
    }
  }, [reportParams]);

  useEffect(() => {
    if (!canViewReports) {
      return;
    }

    let cancelled = false;

    async function bootstrap() {
      try {
        const summaryResponse = await apiClient.get<ReportSummaryResponse>(
          "/reports/attendance-summary",
          { params: reportParams() },
        );

        if (cancelled) {
          return;
        }

        setSummary(summaryResponse.data);
      } catch {
        if (!cancelled) {
          setSummary(null);
        }
      }
    }

    void bootstrap();

    return () => {
      cancelled = true;
    };
  }, [canViewReports, filters, reportParams, revision]);

  if (!canViewReports) {
    return null;
  }

  return (
    <div className="space-y-6">
      <section>
        <p className="text-sm font-semibold uppercase tracking-[0.2em] text-sky-600">
          Reporting and analytics
        </p>
        <h1 className="mt-2 text-3xl font-semibold">Attendance insights and export tools</h1>
        {currentSchool ? (
          <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
            Reports for {currentSchool.name}
          </p>
        ) : (
          <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
            Select a school in the header to scope reports to one campus.
          </p>
        )}
      </section>
      <Card className="p-5">
        <ReportFilters classes={classes} value={filters} onChange={setFilters} onApply={() => void loadReports()} />
        <div className="mt-4 flex flex-wrap gap-3">
          <Button
            variant="outline"
            disabled={exportBusy !== null}
            onClick={() => void queueExport("xlsx")}
          >
            {exportBusy === "xlsx" ? "Queueing Excel…" : "Queue Excel export"}
          </Button>
          <Button
            variant="outline"
            disabled={exportBusy !== null}
            onClick={() => void queueExport("pdf")}
          >
            {exportBusy === "pdf" ? "Queueing PDF…" : "Queue PDF export"}
          </Button>
        </div>
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
        <SummaryCard label="Late" value={summary?.totals.late ?? 0} />
        <SummaryCard label="Attendance rate" value={`${summary?.totals.attendance_rate ?? 0}%`} />
      </section>
      <ReportExportsPanel
        pollForNewExport={exportPolling}
        onPollComplete={() => setExportPolling(false)}
      />
    </div>
  );
}
