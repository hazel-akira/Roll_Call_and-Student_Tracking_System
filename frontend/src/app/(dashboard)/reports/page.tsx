"use client";

import { useCallback, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { ReportExportsPanel } from "@/components/reports/report-exports-panel";
import { ReportFilters, type ReportFilters as FilterState } from "@/components/reports/report-filters";
import { SummaryCard } from "@/components/dashboard/summary-card";
import { Card } from "@/components/ui/card";
import { useAuth } from "@/lib/auth/auth-context";
import { apiClient } from "@/lib/api/client";
import { useSchool } from "@/lib/tenant/school-context";
import { roleHomePath, canViewReports } from "@/lib/utils";
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

type ExportResponse = {
  message: string;
  status?: "completed" | "queued";
};

function buildReportParams(filters: FilterState) {
  return {
    from: filters.from,
    to: filters.to,
    class_id: filters.class_id || undefined,
  };
}

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
  const [appliedFilters, setAppliedFilters] = useState<FilterState>(filters);
  const [summary, setSummary] = useState<ReportSummaryResponse | null>(null);
  const [exportMessage, setExportMessage] = useState<string | null>(null);
  const [exportError, setExportError] = useState<string | null>(null);
  const [exportPolling, setExportPolling] = useState(false);
  const [exportBusy, setExportBusy] = useState<"xlsx" | "pdf" | null>(null);
  const [exportsRefreshKey, setExportsRefreshKey] = useState(0);
  const canViewReportsAccess = canViewReports(user?.role?.slug);

  const loadSummary = useCallback(async (nextFilters: FilterState) => {
    try {
      const summaryResponse = await apiClient.get<ReportSummaryResponse>(
        "/reports/attendance-summary",
        { params: buildReportParams(nextFilters) },
      );

      setSummary(summaryResponse.data);
    } catch {
      setSummary(null);
    }
  }, []);

  const applyFilters = useCallback(async () => {
    setAppliedFilters(filters);
    await loadSummary(filters);
  }, [filters, loadSummary]);

  const queueExport = useCallback(
    async (format: "xlsx" | "pdf") => {
      setExportBusy(format);
      setExportError(null);
      setExportMessage(null);

      try {
        const response = await apiClient.get<ExportResponse>("/reports/export", {
          params: { ...buildReportParams(appliedFilters), format },
        });

        if (response.data.status === "completed") {
          setExportMessage(response.data.message);
          setExportsRefreshKey((value) => value + 1);
        } else {
          setExportMessage(
            `${format.toUpperCase()} export queued. It will appear below when ready.`,
          );
          setExportPolling(true);
        }
      } catch {
        setExportError("Unable to generate the export. Try again or confirm the queue worker is running.");
      } finally {
        setExportBusy(null);
      }
    },
    [appliedFilters],
  );

  useEffect(() => {
    if (loading || !user) {
      return;
    }

    if (!canViewReportsAccess) {
      router.replace(roleHomePath(user.role?.slug));
    }
  }, [canViewReportsAccess, loading, router, user]);

  useEffect(() => {
    if (!canViewReportsAccess) {
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
  }, [canViewReportsAccess, revision]);

  useEffect(() => {
    if (!canViewReportsAccess) {
      return;
    }

    void loadSummary(appliedFilters);
  }, [appliedFilters, canViewReportsAccess, loadSummary, revision]);

  if (!canViewReportsAccess) {
    return null;
  }

  return (
    <div className="space-y-6">
      <section>
        <p className="page-eyebrow">Reporting and analytics</p>
        <h1 className="page-title">Attendance insights and export tools</h1>
        {currentSchool ? (
          <p className="mt-2 text-sm text-muted">Reports for {currentSchool.name}</p>
        ) : (
          <p className="mt-2 text-sm text-muted">
            Select a school in the header to scope reports to one campus.
          </p>
        )}
      </section>
      <Card className="p-5">
        <ReportFilters
          classes={classes}
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
        <SummaryCard label="Late" value={summary?.totals.late ?? 0} />
        <SummaryCard label="Attendance rate" value={`${summary?.totals.attendance_rate ?? 0}%`} />
      </section>
      <ReportExportsPanel
        refreshKey={exportsRefreshKey}
        pollForNewExport={exportPolling}
        onPollComplete={() => setExportPolling(false)}
      />
    </div>
  );
}
