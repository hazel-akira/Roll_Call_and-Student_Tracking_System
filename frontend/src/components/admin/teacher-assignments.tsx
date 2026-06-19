"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { apiClient } from "@/lib/api/client";
import type { AppUser, School, SchoolClass } from "@/types";

type ClassAssignment = {
  class_id: number;
  class?: SchoolClass | null;
};

type TeacherRow = AppUser & {
  schools?: School[];
  class_assignments?: ClassAssignment[];
};

type Draft = {
  schoolIds: number[];
  classIds: number[];
};

function classLabel(item: SchoolClass): string {
  const grade = item.grade_level ?? item.name;
  const stream = item.section?.trim();
  return stream ? `${grade} · ${stream}` : grade;
}

export function TeacherAssignments() {
  const [teachers, setTeachers] = useState<TeacherRow[]>([]);
  const [schools, setSchools] = useState<School[]>([]);
  const [classes, setClasses] = useState<SchoolClass[]>([]);
  const [draft, setDraft] = useState<Record<number, Draft>>({});
  const [loading, setLoading] = useState(true);
  const [savingId, setSavingId] = useState<number | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const classesBySchool = useMemo(() => {
    const map = new Map<number, SchoolClass[]>();
    for (const school of schools) {
      map.set(
        school.id,
        classes.filter((item) => item.school_id === school.id),
      );
    }
    return map;
  }, [classes, schools]);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [teacherResponse, schoolResponse, classResponse] = await Promise.all([
        apiClient.get<{ data: TeacherRow[] }>("/teachers"),
        apiClient.get<{ data: School[] }>("/schools"),
        apiClient.get<{ data: SchoolClass[] }>("/classes"),
      ]);

      const teacherRows = Array.isArray(teacherResponse.data?.data)
        ? teacherResponse.data.data
        : [];
      const schoolRows = Array.isArray(schoolResponse.data?.data)
        ? schoolResponse.data.data
        : [];
      const classRows = Array.isArray(classResponse.data?.data)
        ? classResponse.data.data
        : [];

      setTeachers(teacherRows);
      setSchools(schoolRows);
      setClasses(classRows);
      setDraft(
        Object.fromEntries(
          teacherRows.map((teacher) => [
            teacher.id,
            {
              schoolIds: (teacher.schools ?? []).map((school) => school.id),
              classIds: (teacher.class_assignments ?? []).map(
                (row) => row.class_id ?? row.class?.id ?? 0,
              ).filter((id) => id > 0),
            },
          ]),
        ),
      );
      setMessage(null);
    } catch {
      setTeachers([]);
      setSchools([]);
      setClasses([]);
      setMessage("Unable to load teachers, schools, or classes.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const loadData = async () => {
      setLoading(true);
      try {
        const [teacherResponse, schoolResponse, classResponse] = await Promise.all([
          apiClient.get<{ data: TeacherRow[] }>("/teachers"),
          apiClient.get<{ data: School[] }>("/schools"),
          apiClient.get<{ data: SchoolClass[] }>("/classes"),
        ]);

        const teacherRows = Array.isArray(teacherResponse.data?.data)
          ? teacherResponse.data.data
          : [];
        const schoolRows = Array.isArray(schoolResponse.data?.data)
          ? schoolResponse.data.data
          : [];
        const classRows = Array.isArray(classResponse.data?.data)
          ? classResponse.data.data
          : [];

        setTeachers(teacherRows);
        setSchools(schoolRows);
        setClasses(classRows);
        setDraft(
          Object.fromEntries(
            teacherRows.map((teacher) => [
              teacher.id,
              {
                schoolIds: (teacher.schools ?? []).map((school) => school.id),
                classIds: (teacher.class_assignments ?? []).map(
                  (row) => row.class_id ?? row.class?.id ?? 0,
                ).filter((id) => id > 0),
              },
            ]),
          ),
        );
        setMessage(null);
      } catch {
        setTeachers([]);
        setSchools([]);
        setClasses([]);
        setMessage("Unable to load teachers, schools, or classes.");
      } finally {
        setLoading(false);
      }
    };

    void loadData();
  }, []);

  async function saveTeacher(teacherId: number) {
    const entry = draft[teacherId];
    if (!entry || entry.schoolIds.length === 0) {
      setMessage("Each teacher must be assigned to at least one school.");
      return;
    }

    setSavingId(teacherId);
    setMessage(null);

    try {
      await apiClient.put(`/teachers/${teacherId}/assignments`, {
        school_ids: entry.schoolIds,
        class_ids: entry.classIds.filter((classId) =>
          classes.some(
            (item) =>
              item.id === classId && entry.schoolIds.includes(item.school_id ?? 0),
          ),
        ),
      });
      setMessage("Teacher assignments saved.");
      await load();
    } catch {
      setMessage("Failed to save assignments. Check schools and classes.");
    } finally {
      setSavingId(null);
    }
  }

  function updateDraft(teacherId: number, updater: (current: Draft) => Draft) {
    setDraft((current) => ({
      ...current,
      [teacherId]: updater(
        current[teacherId] ?? {
          schoolIds: [],
          classIds: [],
        },
      ),
    }));
  }

  function toggleSchool(teacherId: number, schoolId: number, checked: boolean) {
    updateDraft(teacherId, (current) => {
      const schoolIds = checked
        ? Array.from(new Set([...current.schoolIds, schoolId]))
        : current.schoolIds.filter((id) => id !== schoolId);

      const classIds = current.classIds.filter((classId) => {
        const item = classes.find((row) => row.id === classId);
        return item?.school_id ? schoolIds.includes(item.school_id) : false;
      });

      return { schoolIds, classIds };
    });
  }

  function toggleClass(teacherId: number, classId: number, checked: boolean) {
    updateDraft(teacherId, (current) => ({
      ...current,
      classIds: checked
        ? Array.from(new Set([...current.classIds, classId]))
        : current.classIds.filter((id) => id !== classId),
    }));
  }

  if (loading) {
    return (
      <Card className="p-5">
        <p className="text-sm text-slate-500 dark:text-slate-400">Loading teacher assignments...</p>
      </Card>
    );
  }

  return (
    <Card className="p-5">
      <h2 className="text-lg font-semibold">Teacher assignments</h2>
      <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Assign each teacher to one or more schools, then choose the classes and streams they teach.
        Teachers only see attendance and students for their assigned schools and classes.
      </p>
      {message ? (
        <p className="mt-3 text-sm text-sky-700 dark:text-sky-300">{message}</p>
      ) : null}
      <div className="mt-4 space-y-6">
        {teachers.length === 0 ? (
          <p className="text-sm text-slate-500 dark:text-slate-400">
            No teacher accounts yet. Teachers are created on first Microsoft sign-in.
          </p>
        ) : null}
        {teachers.map((teacher) => {
          const entry = draft[teacher.id] ?? { schoolIds: [], classIds: [] };

          return (
            <div
              key={teacher.id}
              className="rounded-xl border border-slate-200 p-4 dark:border-slate-800"
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p className="font-medium text-slate-900 dark:text-white">{teacher.name}</p>
                  <p className="text-sm text-slate-500 dark:text-slate-400">{teacher.email}</p>
                </div>
                <Button
                  size="sm"
                  variant="outline"
                  disabled={savingId === teacher.id || entry.schoolIds.length === 0}
                  onClick={() => void saveTeacher(teacher.id)}
                >
                  {savingId === teacher.id ? "Saving..." : "Save assignments"}
                </Button>
              </div>

              <div className="mt-4">
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  Schools
                </p>
                <div className="mt-2 flex flex-wrap gap-2">
                  {schools.map((school) => (
                    <label
                      key={school.id}
                      className="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700"
                    >
                      <input
                        type="checkbox"
                        checked={entry.schoolIds.includes(school.id)}
                        onChange={(event) =>
                          toggleSchool(teacher.id, school.id, event.target.checked)
                        }
                      />
                      {school.name}
                    </label>
                  ))}
                </div>
              </div>

              <div className="mt-4 space-y-3">
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                  Classes & streams
                </p>
                {entry.schoolIds.length === 0 ? (
                  <p className="text-sm text-slate-500 dark:text-slate-400">
                    Select at least one school to choose classes.
                  </p>
                ) : (
                  entry.schoolIds.map((schoolId) => {
                    const school = schools.find((item) => item.id === schoolId);
                    const schoolClasses = classesBySchool.get(schoolId) ?? [];

                    return (
                      <div
                        key={schoolId}
                        className="rounded-lg border border-slate-100 bg-slate-50/80 p-3 dark:border-slate-800 dark:bg-slate-900/50"
                      >
                        <p className="text-sm font-medium text-slate-800 dark:text-slate-200">
                          {school?.name ?? "School"}
                        </p>
                        {schoolClasses.length === 0 ? (
                          <p className="mt-1 text-xs text-slate-500">No classes seeded for this school.</p>
                        ) : (
                          <div className="mt-2 flex flex-wrap gap-2">
                            {schoolClasses.map((item) => (
                              <label
                                key={item.id}
                                className="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-950"
                              >
                                <input
                                  type="checkbox"
                                  checked={entry.classIds.includes(item.id)}
                                  onChange={(event) =>
                                    toggleClass(teacher.id, item.id, event.target.checked)
                                  }
                                />
                                {classLabel(item)}
                              </label>
                            ))}
                          </div>
                        )}
                      </div>
                    );
                  })
                )}
              </div>
            </div>
          );
        })}
      </div>
    </Card>
  );
}
