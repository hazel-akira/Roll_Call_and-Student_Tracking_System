"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { TeacherAssignments } from "@/components/admin/teacher-assignments";
import { SummaryCard } from "@/components/dashboard/summary-card";
import { Card } from "@/components/ui/card";
import { useAuth } from "@/lib/auth/auth-context";
import { apiClient } from "@/lib/api/client";
import { useSchool } from "@/lib/tenant/school-context";
import { formatDate, roleHomePath } from "@/lib/utils";

type AdminDashboardResponse = {
  stats: {
    students: number;
    teachers: number;
    classes: number;
    today_sessions: number;
    attendance_rate_today: number;
    unresolved_absences: number;
  };
  daily_attendance_trends: Array<{
    session_date: string;
    present: number;
    absent: number;
  }>;
  recent_audit_logs: {
    id: number;
    event_type: string;
    description: string;
    created_at: string;
    actor?: { name: string | null } | null;
  }[];
  recent_sync_failures: {
    id: number;
    attendance_session_id: number;
    status: string;
    error_message?: string | null;
    created_at: string;
  }[];
};

const widthClassByPercent: Record<number, string> = {
  0: "w-0",
  10: "w-[10%]",
  20: "w-[20%]",
  30: "w-[30%]",
  40: "w-[40%]",
  50: "w-1/2",
  60: "w-[60%]",
  70: "w-[70%]",
  80: "w-[80%]",
  90: "w-[90%]",
  100: "w-full",
};

function trendWidthClass(value: number, maxValue: number): string {
  if (maxValue <= 0) {
    return widthClassByPercent[0];
  }

  const bucket = Math.round((value / maxValue) * 10) * 10;
  const boundedBucket = Math.max(0, Math.min(100, bucket));

  return widthClassByPercent[boundedBucket];
}

export default function AdminDashboardPage() {
  const router = useRouter();
  const { user, loading } = useAuth();
  const { currentSchool, viewingAllSchools, revision } = useSchool();
  const [data, setData] = useState<AdminDashboardResponse | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [dashboardLoading, setDashboardLoading] = useState(true);
  const canViewAdminDashboard =
    user?.role?.slug === "admin" || user?.role?.slug === "ict_staff";

  useEffect(() => {
    if (loading || !user) {
      return;
    }

    if (!canViewAdminDashboard) {
      router.replace(roleHomePath(user.role?.slug));
    }
  }, [canViewAdminDashboard, loading, router, user]);

  useEffect(() => {
    if (!canViewAdminDashboard) {
      return;
    }

    let active = true;

    const load = async () => {
      setDashboardLoading(true);
      try {
        const response = await apiClient.get<AdminDashboardResponse>("/dashboard/admin");
        if (active) {
          setData(response.data);
          setLoadError(null);
        }
      } catch {
        if (active) {
          setData(null);
          setLoadError("Unable to load admin dashboard data.");
        }
      } finally {
        if (active) {
          setDashboardLoading(false);
        }
      }
    };

    void load();
    const timer = window.setInterval(() => {
      void load();
    }, 10000);

    return () => {
      active = false;
      window.clearInterval(timer);
    };
  }, [canViewAdminDashboard, revision]);

  if (!canViewAdminDashboard) {
    return null;
  }

  const trends = data?.daily_attendance_trends ?? [];
  const maxTrendValue = trends.reduce((max, item) => Math.max(max, item.present, item.absent), 1);

  return (
    <div className="space-y-6">
      <section>
        <p className="page-eyebrow">
          Administrative oversight
        </p>
        <h1 className="page-title">Operations and attendance analytics</h1>
        {viewingAllSchools ? (
          <p className="mt-2 text-sm text-muted">
            Showing data across all schools. Pick a single school in the header to focus on one campus.
          </p>
        ) : currentSchool ? (
          <p className="mt-2 text-sm text-muted">
            Showing data for {currentSchool.name}. Use the school selector in the header to switch campus.
          </p>
        ) : null}
      </section>
      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
        <SummaryCard label="Students" value={data?.stats.students ?? 0} />
        <SummaryCard label="Teachers" value={data?.stats.teachers ?? 0} />
        <SummaryCard label="Classes" value={data?.stats.classes ?? 0} />
        <SummaryCard label="Today's sessions" value={data?.stats.today_sessions ?? 0} />
        <SummaryCard
          label="Attendance rate"
          value={`${data?.stats.attendance_rate_today ?? 0}%`}
        />
        <SummaryCard label="Unresolved absences" value={data?.stats.unresolved_absences ?? 0} />
      </section>
      {loadError ? (
        <Card className="border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
          {loadError}
        </Card>
      ) : null}
      <section className="grid gap-6 xl:grid-cols-2">
        <Card className="p-5">
          <h2 className="section-title">Present vs absent (last 7 days)</h2>
          <div className="mt-4 space-y-3">
            {dashboardLoading ? (
              <p className="text-sm text-muted">Loading trends…</p>
            ) : null}
            {!dashboardLoading && trends.length === 0 ? (
              <p className="text-sm text-muted">No attendance data for the last 7 days.</p>
            ) : null}
            {trends.map((trend) => (
              <div key={trend.session_date}>
                <div className="mb-1 flex items-center justify-between text-xs text-muted">
                  <span>{formatDate(trend.session_date)}</span>
                  <span>P: {trend.present} | A: {trend.absent}</span>
                </div>
                <div className="flex h-2 overflow-hidden rounded-full bg-(--surface-muted)">
                  <div
                    className={`bg-emerald-500 ${trendWidthClass(trend.present, maxTrendValue)}`}
                  />
                  <div
                    className={`bg-rose-500 ${trendWidthClass(trend.absent, maxTrendValue)}`}
                  />
                </div>
              </div>
            ))}
          </div>
        </Card>
        <Card className="p-5">
          <h2 className="section-title">Recent audit activity</h2>
          <div className="mt-4 space-y-3">
            {!dashboardLoading && (data?.recent_audit_logs?.length ?? 0) === 0 ? (
              <p className="text-sm text-muted">No recent audit activity.</p>
            ) : null}
            {data?.recent_audit_logs?.map((log) => (
              <div key={log.id} className="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                <p className="font-medium text-foreground">{log.description}</p>
                <p className="mt-1 text-sm text-muted">
                  {log.event_type} · {formatDate(log.created_at)}
                </p>
              </div>
            ))}
          </div>
        </Card>
        <Card className="p-5 xl:col-span-2">
          <h2 className="section-title">Dynamics sync exceptions</h2>
          <div className="mt-4 space-y-3">
            {!dashboardLoading && (data?.recent_sync_failures?.length ?? 0) === 0 ? (
              <p className="text-sm text-muted">No Dynamics sync failures.</p>
            ) : null}
            {data?.recent_sync_failures?.map((sync) => (
              <div key={sync.id} className="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                <p className="font-medium text-foreground">
                  Session #{sync.attendance_session_id}
                </p>
                <p className="mt-1 text-sm text-muted">
                  {sync.error_message ?? "Sync failed"}
                </p>
              </div>
            ))}
          </div>
        </Card>
      </section>
      <TeacherAssignments />
    </div>
  );
}
