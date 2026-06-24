"use client";

import { useMemo, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import type { AttendanceSession, Student } from "@/types";

const statuses = ["present", "missing", "sick", "on_leave"] as const;

type AttendanceUiStatus = (typeof statuses)[number];

const statusSelectedStyles: Record<AttendanceUiStatus, string> = {
  present:
    "border-emerald-600 bg-emerald-500 text-white shadow-sm dark:border-emerald-500 dark:bg-emerald-600",
  missing:
    "border-red-900 bg-red-800 text-white shadow-sm dark:border-red-800 dark:bg-red-900",
  sick: "border-amber-500 bg-amber-500 text-white shadow-sm dark:border-amber-500 dark:bg-amber-600",
  on_leave:
    "border-orange-500 bg-orange-500 text-white shadow-sm dark:border-orange-500 dark:bg-orange-600",
};

function statusLabel(status: AttendanceUiStatus): string {
  if (status === "on_leave") {
    return "On Leave";
  }

  return status.charAt(0).toUpperCase() + status.slice(1);
}

export function AttendanceRecordsTable({
  session,
  students,
  onSave,
  onCloseSession,
}: {
  session: AttendanceSession | null;
  students: Student[];
  onSave: (records: {
    student_id: number;
    status: "present" | "missing" | "sick" | "on_leave";
    remark?: string;
  }[]) => Promise<void>;
  onCloseSession: () => Promise<void>;
}) {
  const [busy, setBusy] = useState(false);
  const [remarks, setRemarks] = useState<Record<number, string>>({});
  const [statusMap, setStatusMap] = useState<Record<number, typeof statuses[number]>>({});

  const rows = useMemo(() => {
    if (!session) return [];

    return students
      .filter((student) => Number.isFinite(student.id) && student.id > 0)
      .map((student) => {
        const existing = session.records?.find((record) => record.student?.id === student.id);
        return {
          student,
          status: statusMap[student.id] ?? existing?.status ?? "present",
          remark: remarks[student.id] ?? existing?.remark ?? "",
        };
      });
  }, [remarks, session, statusMap, students]);

  const hasSyncableStudents = rows.length > 0;

  if (!session) {
    return (
      <Card className="p-6 text-sm text-muted">
        Select an attendance session to capture roll call records.
      </Card>
    );
  }

  return (
    <Card className="overflow-hidden">
      <div className="flex items-center justify-between border-b px-5 py-4">
        <div>
          <h3 className="section-title">{session.title}</h3>
          <p className="mt-1 text-sm text-muted">
            {(session.class?.grade_level ?? session.class?.name) || "Class"} · {session.class?.section ?? "Stream"}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Badge value={session.status} />
          <Badge value={session.dynamics_sync_status} />
        </div>
      </div>
      <div className="flex justify-end border-b px-5 py-3">
        <Button
          variant="secondary"
          disabled={busy || session.status === "closed"}
          onClick={() => {
            const next: Record<number, typeof statuses[number]> = {};
            rows.forEach((row) => {
              next[row.student.id] = "present";
            });
            setStatusMap(next);
          }}
        >
          Mark All Present
        </Button>
      </div>
      {!hasSyncableStudents ? (
        <p className="border-b px-5 py-4 text-sm text-amber-800 dark:text-amber-200">
          Students are still loading from Dataverse or are not synced to this class yet. Wait for the
          list to appear, or use Sync students from Dynamics, before saving attendance.
        </p>
      ) : null}
      <div className="overflow-x-auto">
        <table className="min-w-full text-sm">
          <thead className="bg-(--surface-muted) text-left text-muted">
            <tr>
              <th className="px-5 py-3 font-medium">Student</th>
              <th className="px-5 py-3 font-medium">Admission #</th>
              <th className="px-5 py-3 font-medium">Status</th>
              <th className="px-5 py-3 font-medium">Remark</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.student.id} className="border-t">
                <td className="px-5 py-3 font-medium text-foreground">
                  {row.student.full_name}
                </td>
                <td className="px-5 py-3 text-muted">
                  {row.student.admission_number}
                </td>
                <td className="px-5 py-3">
                  <div className="flex flex-wrap gap-2">
                    {statuses.map((status) => (
                      <button
                        key={status}
                        type="button"
                        className={cn(
                          "rounded-lg border px-2.5 py-1 text-xs font-medium transition",
                          row.status === status
                            ? statusSelectedStyles[status]
                            : "border text-muted hover:bg-(--surface-muted)",
                        )}
                        onClick={() =>
                          setStatusMap((current) => ({
                            ...current,
                            [row.student.id]: status,
                          }))
                        }
                      >
                        {statusLabel(status)}
                      </button>
                    ))}
                  </div>
                </td>
                <td className="px-5 py-3">
                  <input
                    className="field-control w-full"
                    placeholder="Optional remark"
                    value={row.remark}
                    onChange={(event) =>
                      setRemarks((current) => ({
                        ...current,
                        [row.student.id]: event.target.value,
                      }))
                    }
                  />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="flex flex-wrap justify-end gap-3 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
        <Button
          variant="outline"
          disabled={busy || session.status === "closed"}
          onClick={async () => {
            setBusy(true);
            try {
              await onCloseSession();
            } finally {
              setBusy(false);
            }
          }}
        >
          Close session
        </Button>
        <Button
          disabled={busy || session.status === "closed" || !hasSyncableStudents}
          title={
            hasSyncableStudents
              ? undefined
              : "Wait for students to finish loading from Dataverse before saving."
          }
          onClick={async () => {
            setBusy(true);
            try {
              await onSave(
                rows.map((row) => ({
                  student_id: row.student.id,
                  status: row.status,
                  remark: row.remark || undefined,
                })),
              );
            } finally {
              setBusy(false);
            }
          }}
        >
          {busy ? "Saving..." : "Save attendance"}
        </Button>
      </div>
    </Card>
  );
}
