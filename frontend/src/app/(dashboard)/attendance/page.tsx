"use client";

import { isAxiosError } from "axios";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  AttendanceSessionForm,
  type FormStreamSelection,
} from "@/components/attendance/attendance-session-form";
import { AttendanceRecordsTable } from "@/components/attendance/attendance-records-table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { useAuth } from "@/lib/auth/auth-context";
import { apiClient } from "@/lib/api/client";
import { loadStudentsForFormStream } from "@/lib/attendance/load-students";
import { useSchool } from "@/lib/tenant/school-context";
import { formatDate } from "@/lib/utils";
import type { AttendanceSession, SchoolClass, Student } from "@/types";

function asArray<T>(value: unknown): T[] {
  return Array.isArray(value) ? (value as T[]) : [];
}

export default function AttendancePage() {
  const { user, loading: authLoading } = useAuth();
  const { currentSchool, schoolId, revision } = useSchool();
  const [classes, setClasses] = useState<SchoolClass[]>([]);
  const [students, setStudents] = useState<Student[]>([]);
  const [sessions, setSessions] = useState<AttendanceSession[]>([]);
  const [formStreamSelection, setFormStreamSelection] = useState<FormStreamSelection | null>(
    null,
  );
  const [selectedSessionId, setSelectedSessionId] = useState<number | null>(null);
  const [studentsLoading, setStudentsLoading] = useState(false);
  const [syncingStudents, setSyncingStudents] = useState(false);
  const [dynamicsError, setDynamicsError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [createError, setCreateError] = useState<string | null>(null);
  const [createSuccess, setCreateSuccess] = useState<string | null>(null);
  const [saveSuccess, setSaveSuccess] = useState<string | null>(null);
  const [pinnedSession, setPinnedSession] = useState<AttendanceSession | null>(null);
  const studentFetchInFlightRef = useRef<string | null>(null);
  const studentFetchCompletedRef = useRef<string | null>(null);
  const sessionStudentsLoadedForRef = useRef<string | null>(null);

  const canSyncStudentsFromDynamics =
    user?.role?.slug === "admin" || user?.role?.slug === "ict_staff";

  const selectedSession = useMemo(() => {
    if (!selectedSessionId) {
      return null;
    }

    const fromList = sessions.find((session) => session.id === selectedSessionId);
    if (fromList) {
      return fromList;
    }

    const pinned = pinnedSession;
    return pinned?.id === selectedSessionId ? pinned : null;
  }, [pinnedSession, selectedSessionId, sessions]);

  const buildStudentFetchKey = useCallback(
    (params: {
      classId: number;
      gradeLevel: string;
      stream: string;
      roomId?: string | null;
    }) =>
      [
        schoolId ?? "",
        params.classId,
        params.gradeLevel,
        params.stream,
        params.roomId ?? "",
      ].join("|"),
    [schoolId],
  );

  const fetchStudents = useCallback(
    async (
      params: {
        classId: number;
        gradeLevel: string;
        stream: string;
        roomId?: string | null;
      },
      options?: { force?: boolean },
    ) => {
      if (!schoolId) {
        return;
      }

      const fetchKey = buildStudentFetchKey(params);
      if (!options?.force) {
        if (
          studentFetchInFlightRef.current === fetchKey ||
          studentFetchCompletedRef.current === fetchKey
        ) {
          return;
        }
      }

      studentFetchInFlightRef.current = fetchKey;
      setStudentsLoading(true);

      try {
        const result = await loadStudentsForFormStream({
          ...params,
          schoolId,
        });
        setStudents(result.students);
        setDynamicsError(result.error);

        const isRetryableError =
          result.error !== null &&
          (result.error.includes("Select a school") ||
            result.error.includes("unavailable") ||
            result.error.includes("Cannot reach the API"));

        if (!isRetryableError) {
          studentFetchCompletedRef.current = fetchKey;
        }

        const resolvedLocalClassId = result.meta?.local_class_id;
        if (resolvedLocalClassId && resolvedLocalClassId > 0) {
          setFormStreamSelection((current) =>
            current && current.classId !== resolvedLocalClassId
              ? { ...current, classId: resolvedLocalClassId }
              : current,
          );
        }

        return result;
      } finally {
        if (studentFetchInFlightRef.current === fetchKey) {
          studentFetchInFlightRef.current = null;
        }
        setStudentsLoading(false);
      }
    },
    [buildStudentFetchKey, schoolId],
  );

  const handleFormStreamChange = useCallback((selection: FormStreamSelection | null) => {
    setFormStreamSelection((current) => {
      if (!selection) {
        return current === null ? current : null;
      }

      if (
        current &&
        current.gradeLevel === selection.gradeLevel &&
        current.stream === selection.stream &&
        current.classId === selection.classId &&
        current.roomId === selection.roomId
      ) {
        return current;
      }

      studentFetchCompletedRef.current = null;
      setCreateError(null);
      return selection;
    });
  }, []);

  const loadReferenceData = useCallback(async () => {
    try {
      const [classResponse, sessionResponse] = await Promise.all([
        apiClient.get<{ data: SchoolClass[] }>("/classes"),
        apiClient.get<{ data: { data: AttendanceSession[] } }>("/attendance-sessions", {
          params: { per_page: 100 },
        }),
      ]);

      const fetchedSessions = asArray<AttendanceSession>(sessionResponse.data?.data?.data);

      setClasses(asArray<SchoolClass>(classResponse.data?.data));
      setSessions((current) => {
        const merged = new Map<number, AttendanceSession>();
        for (const session of fetchedSessions) {
          merged.set(session.id, session);
        }
        for (const session of current) {
          if (!merged.has(session.id)) {
            merged.set(session.id, session);
          }
        }
        const pinned = pinnedSession;
        if (pinned && !merged.has(pinned.id)) {
          merged.set(pinned.id, pinned);
        }

        return Array.from(merged.values()).sort((left, right) =>
          right.session_date.localeCompare(left.session_date),
        );
      });
      setActionError(null);
    } catch (error) {
      if (isAxiosError(error) && error.response?.status === 401) {
        return;
      }

      setActionError("Unable to load classes and attendance sessions.");
    }
  }, [pinnedSession]);

  useEffect(() => {
    if (authLoading || !user) {
      return;
    }

    const load = async () => {
      await loadReferenceData();
    };

    void load();
  }, [authLoading, loadReferenceData, revision, user]);

  useEffect(() => {
    studentFetchCompletedRef.current = null;
    sessionStudentsLoadedForRef.current = null;
  }, [revision, schoolId]);

  const formGradeLevel = formStreamSelection?.gradeLevel ?? "";
  const formStream = formStreamSelection?.stream ?? "";
  const formClassId = formStreamSelection?.classId ?? 0;
  const formRoomId = formStreamSelection?.roomId ?? null;

  useEffect(() => {
    const load = async () => {
      if (!schoolId || !formGradeLevel || !formStream) {
        if (!formGradeLevel || !formStream) {
          setStudents([]);
          studentFetchCompletedRef.current = null;
        }
        return;
      }

      await fetchStudents({
        classId: formClassId,
        gradeLevel: formGradeLevel,
        stream: formStream,
        roomId: formRoomId,
      });
    };

    void load();
  }, [fetchStudents, formClassId, formGradeLevel, formRoomId, formStream, schoolId]);

  const applySessionUpdate = useCallback(
    (updated: AttendanceSession, options?: { pin?: boolean }) => {
      if (!updated?.id) {
        return;
      }

      const shouldPin = options?.pin ?? selectedSessionId === updated.id;
      if (shouldPin) {
        setPinnedSession(updated);
      }

      setSessions((current) => {
        const exists = current.some((item) => item.id === updated.id);
        if (exists) {
          return current.map((item) => (item.id === updated.id ? updated : item));
        }

        return [updated, ...current];
      });
    },
    [selectedSessionId],
  );

  useEffect(() => {
    if (!selectedSessionId) {
      sessionStudentsLoadedForRef.current = null;
      return;
    }

    const sessionClass = selectedSession?.class;
    if (!sessionClass?.id) {
      return;
    }

    const roomId = formStreamSelection?.roomId ?? formRoomId;
    const loadKey = [
      selectedSessionId,
      sessionClass.id,
      sessionClass.section ?? "",
      roomId ?? "",
    ].join("|");

    if (sessionStudentsLoadedForRef.current === loadKey) {
      return;
    }

    const load = async () => {
      const result = await fetchStudents(
        {
          classId: sessionClass.id,
          gradeLevel: sessionClass.grade_level ?? sessionClass.name ?? "",
          stream: sessionClass.section ?? formStreamSelection?.stream ?? "",
          roomId,
        },
        { force: true },
      );

      if (result && (result.students.length > 0 || !result.error)) {
        sessionStudentsLoadedForRef.current = loadKey;
      }
    };

    void load();
  }, [fetchStudents, formRoomId, formStreamSelection, selectedSession, selectedSessionId]);

  const refreshSession = useCallback(
    async (sessionId: number) => {
      const response = await apiClient.get<{ data: AttendanceSession }>(
        `/attendance-sessions/${sessionId}`,
      );

      const refreshed = response.data?.data;
      if (!refreshed?.id) {
        return;
      }

      applySessionUpdate(refreshed);
    },
    [applySessionUpdate],
  );

  const handleSelectSession = useCallback((session: AttendanceSession) => {
    setSelectedSessionId(session.id);
    setCreateSuccess(null);
    setSaveSuccess(null);
    setPinnedSession(session);
    sessionStudentsLoadedForRef.current = null;
    applySessionUpdate(session, { pin: true });
  }, [applySessionUpdate]);

  const handleCreateSession = useCallback(
    async (payload: {
      class_id: number;
      title: string;
      session_date: string;
      notes?: string;
      grade_level?: string;
      stream?: string;
    }) => {
      setCreateError(null);
      setCreateSuccess(null);

      try {
        const response = await apiClient.post<{ data: AttendanceSession }>(
          "/attendance-sessions",
          {
            ...payload,
            class_id:
              formStreamSelection?.classId && formStreamSelection.classId > 0
                ? formStreamSelection.classId
                : payload.class_id,
            grade_level: formStreamSelection?.gradeLevel ?? payload.grade_level,
            stream: formStreamSelection?.stream ?? payload.stream,
          },
        );

        const created = response.data?.data;
        if (!created?.id) {
          setCreateError("Session was created but the server response was incomplete.");
          return;
        }

        applySessionUpdate(created, { pin: true });
        setSelectedSessionId(created.id);
        sessionStudentsLoadedForRef.current = null;
        setCreateError(null);
        setCreateSuccess(`Session "${created.title}" created. Mark attendance on the right.`);
        studentFetchCompletedRef.current = null;

        if (formStreamSelection && schoolId) {
          void fetchStudents(
            {
              classId: created.class?.id ?? formStreamSelection.classId,
              gradeLevel: formStreamSelection.gradeLevel,
              stream: formStreamSelection.stream,
              roomId: formStreamSelection.roomId,
            },
            { force: true },
          );
        }

        try {
          await refreshSession(created.id);
        } catch {
          // Keep optimistic session if detail refresh fails.
        }
      } catch (error) {
        if (isAxiosError(error) && error.response?.status === 403) {
          setCreateError("You do not have permission to create attendance sessions.");
        } else if (isAxiosError(error) && error.response?.status === 422) {
          const data = error.response.data as {
            message?: string;
            errors?: Record<string, string[]>;
          };
          const fieldMessage = data.errors
            ? Object.values(data.errors).flat()[0]
            : undefined;
          setCreateError(
            fieldMessage ?? data.message ?? "Could not create attendance session.",
          );
        } else {
          setCreateError("Failed to create attendance session.");
        }
      }
    },
    [applySessionUpdate, formStreamSelection, refreshSession, schoolId, fetchStudents],
  );

  async function syncSelectedClassStudents() {
    const classId = selectedSession?.class?.id ?? formStreamSelection?.classId;
    if (!classId || !canSyncStudentsFromDynamics) {
      return;
    }

    setSyncingStudents(true);
    try {
      await apiClient.post(`/dynamics/classes/${classId}/students/sync`);
      await fetchStudents({
        classId,
        gradeLevel:
          formStreamSelection?.gradeLevel ??
          selectedSession?.class?.grade_level ??
          selectedSession?.class?.name ??
          "",
        stream: formStreamSelection?.stream ?? selectedSession?.class?.section ?? "",
        roomId: formStreamSelection?.roomId,
      });
      setDynamicsError(null);
    } catch (error) {
      if (isAxiosError(error) && error.response?.status === 403) {
        setDynamicsError("You are not allowed to sync students from Dynamics.");
      } else {
        setDynamicsError("Student sync from Dynamics failed.");
      }
    } finally {
      setSyncingStudents(false);
    }
  }

  const activeClassLabel = formStreamSelection
    ? `${formStreamSelection.gradeLevel}${formStreamSelection.stream ? ` · ${formStreamSelection.stream}` : ""}`
    : null;

  return (
    <div className="space-y-6">
      <section>
        <p className="page-eyebrow">
          Attendance management
        </p>
        <h1 className="page-title">Create sessions and capture roll call</h1>
        {currentSchool ? (
          <p className="mt-2 text-sm text-muted">
            Classes and sessions for {currentSchool.name}
          </p>
        ) : null}
      </section>
      <AttendanceSessionForm
        schoolId={schoolId}
        classes={classes}
        studentsLoading={studentsLoading}
        studentCount={students.length}
        resolvedClassId={formStreamSelection?.classId}
        onFormStreamChange={handleFormStreamChange}
        onCreate={handleCreateSession}
        createError={createError}
        createSuccess={createSuccess}
      />
      {formStreamSelection && !selectedSession ? (
        <Card className="p-5">
          <h3 className="section-title">
            Students for {activeClassLabel}
          </h3>
          <p className="mt-1 text-sm text-muted">
            {studentsLoading
              ? "Loading students..."
              : `${students.length} student(s) ready. Create a session to mark attendance.`}
          </p>
          {students.length > 0 ? (
            <ul className="mt-4 max-h-48 space-y-2 overflow-y-auto text-sm">
              {students.map((student) => (
                <li
                  key={student.id}
                  className="rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-800"
                >
                  {student.full_name}{" "}
                  <span className="text-muted">({student.admission_number})</span>
                </li>
              ))}
            </ul>
          ) : null}
        </Card>
      ) : null}
      <div className="grid gap-6 xl:grid-cols-[0.85fr_1.15fr]">
        <Card className="overflow-hidden">
          <div className="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <h2 className="section-title">Attendance sessions</h2>
          </div>
          <div className="divide-y divide-slate-200 dark:divide-slate-800">
            {sessions.map((session) => (
              <button
                key={session.id}
                type="button"
                className={`w-full px-5 py-4 text-left transition list-row ${
                  selectedSessionId === session.id ? "bg-sky-50 dark:bg-sky-500/10" : ""
                }`}
                onClick={() => handleSelectSession(session)}
              >
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p className="font-medium text-foreground">{session.title}</p>
                    <p className="mt-1 text-sm text-muted">
                      {(session.class?.grade_level ?? session.class?.name) || "Class"} ·{" "}
                      {session.class?.section || "No stream"} · {formatDate(session.session_date)}
                    </p>
                  </div>
                  <div className="flex items-center gap-2">
                    <Badge value={session.status} />
                    <Badge value={session.dynamics_sync_status} />
                  </div>
                </div>
              </button>
            ))}
          </div>
        </Card>
        <AttendanceRecordsTable
          session={selectedSession}
          students={students}
          onSave={async (records) => {
            if (!selectedSession) return;
            const sessionId = selectedSession.id;
            setSaveSuccess(null);
            try {
              const response = await apiClient.put<{
                data: AttendanceSession;
                message?: string;
              }>(`/attendance-sessions/${sessionId}/records`, {
                records,
              });

              const saved = response.data?.data;
              if (saved?.id) {
                applySessionUpdate(saved, { pin: true });
              }

              try {
                await refreshSession(sessionId);
              } catch {
                // PUT already returned the updated session; keep selection if refresh fails.
              }

              setActionError(null);
              setSaveSuccess(
                response.data?.message ?? "Attendance records saved successfully.",
              );
            } catch (error) {
              if (isAxiosError(error) && error.response?.status === 403) {
                setActionError("You do not have permission to save attendance records.");
              } else if (isAxiosError(error) && error.response?.status === 422) {
                const data = error.response.data as {
                  message?: string;
                  errors?: Record<string, string[]>;
                };
                const fieldMessage = data.errors
                  ? Object.values(data.errors).flat()[0]
                  : undefined;
                setActionError(
                  fieldMessage ??
                    data.message ??
                    "Could not save attendance. Students may belong to a different class than this session.",
                );
              } else if (isAxiosError(error) && error.code === "ECONNABORTED") {
                setActionError(
                  "The request timed out. The API may still be waiting on Dataverse — check the backend logs and try again.",
                );
              } else {
                setActionError("Failed to save attendance records.");
              }
            }
          }}
          onCloseSession={async () => {
            if (!selectedSession) return;
            const sessionId = selectedSession.id;
            try {
              const response = await apiClient.patch<{ data: AttendanceSession }>(
                `/attendance-sessions/${sessionId}/close`,
              );
              const closed = response.data?.data;
              if (closed?.id) {
                applySessionUpdate(closed, { pin: true });
              }
              try {
                await refreshSession(sessionId);
              } catch {
                // PATCH response already updated local state.
              }
              setActionError(null);
              setSaveSuccess(null);
            } catch (error) {
              if (isAxiosError(error) && error.response?.status === 403) {
                setActionError("You do not have permission to close this session.");
              } else {
                setActionError("Failed to close attendance session.");
              }
            }
          }}
        />
      </div>
      {dynamicsError ? (
        <Card className="border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
          {dynamicsError}
        </Card>
      ) : null}
      {saveSuccess ? (
        <Card className="border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
          {saveSuccess}
        </Card>
      ) : null}
      {actionError ? (
        <Card className="border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
          {actionError}
        </Card>
      ) : null}
      {(selectedSession || formStreamSelection) && canSyncStudentsFromDynamics ? (
        <div className="flex justify-end">
          <Button
            variant="outline"
            onClick={() => void syncSelectedClassStudents()}
            disabled={syncingStudents}
          >
            {syncingStudents ? "Syncing students from Dynamics..." : "Sync students from Dynamics"}
          </Button>
        </div>
      ) : null}
    </div>
  );
}
