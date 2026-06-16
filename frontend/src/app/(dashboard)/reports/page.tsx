"use client";

import { useCallback, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
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
  const [classTrends, setClassTrends] = useState<Array<Record<string, unknown>>>([]);
  const [studentTrends, setStudentTrends] = useState<Array<Record<string, unknown>>>([]);
  const canViewReports = user?.role?.slug === "admin" || user?.role?.slug === "ict_staff";

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
    const params = {
      ...filters,
      class_id: filters.class_id || undefined,
    };

    try {
      const [summaryResponse, classResponse, studentResponse] = await Promise.all([
        apiClient.get<ReportSummaryResponse>("/reports/attendance-summary", { params }),
        apiClient.get<{ data: Array<Record<string, unknown>> }>("/reports/class-trends", {
          params,
        }),
        apiClient.get<{ data: Array<Record<string, unknown>> }>("/reports/student-trends", {
          params,
        }),
      ]);

      setSummary(summaryResponse.data);
      setClassTrends(classResponse.data.data);
      setStudentTrends(studentResponse.data.data);
    } catch {
      setSummary(null);
      setClassTrends([]);
      setStudentTrends([]);
    }
  }, [filters]);

  useEffect(() => {
    if (!canViewReports) {
      return;
    }

    let cancelled = false;

    async function bootstrap() {
      try {
        const [summaryResponse, classResponse, studentResponse] = await Promise.all([
          apiClient.get<ReportSummaryResponse>("/reports/attendance-summary", {
            params: {
              ...filters,
              class_id: filters.class_id || undefined,
            },
          }),
          apiClient.get<{ data: Array<Record<string, unknown>> }>(
            "/reports/class-trends",
            {
              params: {
                ...filters,
                class_id: filters.class_id || undefined,
              },
            },
          ),
          apiClient.get<{ data: Array<Record<string, unknown>> }>(
            "/reports/student-trends",
            {
              params: {
                ...filters,
                class_id: filters.class_id || undefined,
              },
            },
          ),
        ]);

        if (cancelled) {
          return;
        }

        setSummary(summaryResponse.data);
        setClassTrends(classResponse.data.data);
        setStudentTrends(studentResponse.data.data);
      } catch {
        if (!cancelled) {
          setSummary(null);
          setClassTrends([]);
          setStudentTrends([]);
        }
      }
    }

    void bootstrap();

    return () => {
      cancelled = true;
    };
  }, [canViewReports, filters, revision]);

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
            onClick={() =>
              void apiClient
                .get("/reports/export", { params: { ...filters, format: "xlsx" } })
                .catch(() => undefined)
            }
          >
            Queue Excel export
          </Button>
          <Button
            variant="outline"
            onClick={() =>
              void apiClient
                .get("/reports/export", { params: { ...filters, format: "pdf" } })
                .catch(() => undefined)
            }
          >
            Queue PDF export
          </Button>
        </div>
      </Card>
      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <SummaryCard label="Records" value={summary?.totals.records ?? 0} />
        <SummaryCard label="Present" value={summary?.totals.present ?? 0} />
        <SummaryCard label="Absent" value={summary?.totals.absent ?? 0} />
        <SummaryCard label="Late" value={summary?.totals.late ?? 0} />
        <SummaryCard label="Attendance rate" value={`${summary?.totals.attendance_rate ?? 0}%`} />
      </section>
      <section className="grid gap-6 xl:grid-cols-2">
        <Card className="p-5">
          <h2 className="text-lg font-semibold">Class trends</h2>
          <pre className="mt-4 overflow-x-auto rounded-xl bg-slate-50 p-4 text-xs dark:bg-slate-900">
            {JSON.stringify(classTrends, null, 2)}
          </pre>
        </Card>
        <Card className="p-5">
          <h2 className="text-lg font-semibold">Student trends</h2>
          <pre className="mt-4 overflow-x-auto rounded-xl bg-slate-50 p-4 text-xs dark:bg-slate-900">
            {JSON.stringify(studentTrends, null, 2)}
          </pre>
        </Card>
      </section>
    </div>
  );
}
