"use client";

import { useEffect, useState } from "react";
import { Card } from "@/components/ui/card";
import { SummaryCard } from "@/components/dashboard/summary-card";
import { apiClient } from "@/lib/api/client";
import { useSchool } from "@/lib/tenant/school-context";
import { formatDate } from "@/lib/utils";
import type { AttendanceSession, NotificationItem } from "@/types";

type TeacherDashboardResponse = {
  stats: {
    today_sessions: number;
    open_sessions: number;
    students_marked_today: number;
    assigned_streams: number;
  };
  today_sessions: AttendanceSession[];
  notifications: NotificationItem[];
};

export default function TeacherDashboardPage() {
  const { currentSchool, revision } = useSchool();
  const [data, setData] = useState<TeacherDashboardResponse | null>(null);

  useEffect(() => {
    let active = true;

    const load = async () => {
      try {
        const response = await apiClient.get<TeacherDashboardResponse>("/dashboard/teacher");
        if (active) {
          setData(response.data);
        }
      } catch {
        if (active) {
          setData(null);
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
  }, [revision]);

  return (
    <div className="space-y-6">
      <section>
        <p className="text-sm font-semibold uppercase tracking-[0.2em] text-sky-600">
          Teacher workspace
        </p>
        <h1 className="mt-2 text-3xl font-semibold">Today&apos;s attendance operations</h1>
        {currentSchool ? (
          <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
            {currentSchool.name}
          </p>
        ) : null}
      </section>
      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <SummaryCard label="Today&apos;s sessions" value={data?.stats.today_sessions ?? 0} />
        <SummaryCard label="Open sessions" value={data?.stats.open_sessions ?? 0} />
        <SummaryCard label="Students marked" value={data?.stats.students_marked_today ?? 0} />
        <SummaryCard label="Assigned streams" value={data?.stats.assigned_streams ?? 0} />
      </section>
      <section className="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <Card className="p-5">
          <h2 className="text-lg font-semibold">Today&apos;s sessions</h2>
          <div className="mt-4 space-y-3">
            {data?.today_sessions?.map((session) => (
              <div key={session.id} className="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <p className="font-medium text-slate-900 dark:text-white">{session.title}</p>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                      {session.class?.grade_level ?? session.class?.name} · {session.class?.section ?? "Stream"}
                    </p>
                  </div>
                  <p className="text-sm text-slate-500 dark:text-slate-400">
                    {formatDate(session.started_at)}
                  </p>
                </div>
              </div>
            ))}
          </div>
        </Card>
        <Card className="p-5">
          <h2 className="text-lg font-semibold">Unread notifications</h2>
          <div className="mt-4 space-y-3">
            {data?.notifications?.map((notification) => (
              <div key={notification.id} className="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                <p className="font-medium text-slate-900 dark:text-white">{notification.title}</p>
                <p className="mt-1 text-sm text-slate-500 dark:text-white">{notification.body}</p>
              </div>
            ))}
          </div>
        </Card>
      </section>
    </div>
  );
}
