"use client";

import { useEffect, useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
import {
  findClassForFormStream,
  streamOptionsForGrade,
} from "@/lib/attendance/class-matching";
import {
  fetchFormStreamsForSchool,
  type AttendanceFormStream,
  type FormStreamsPayload,
} from "@/lib/attendance/form-streams";
import type { SchoolClass } from "@/types";

export type FormStreamSelection = {
  gradeLevel: string;
  stream: string;
  roomId: string | null;
  classId: number;
  class: SchoolClass | null;
};

export function AttendanceSessionForm({
  schoolId,
  classes,
  studentsLoading,
  studentCount,
  resolvedClassId,
  onFormStreamChange,
  onCreate,
  createError,
  createSuccess,
}: {
  schoolId: string | null;
  classes: SchoolClass[];
  studentsLoading?: boolean;
  studentCount?: number;
  resolvedClassId?: number;
  onFormStreamChange?: (selection: FormStreamSelection | null) => void;
  onCreate: (payload: {
    class_id: number;
    title: string;
    session_date: string;
    notes?: string;
    grade_level?: string;
    stream?: string;
  }) => Promise<void>;
  createError?: string | null;
  createSuccess?: string | null;
}) {
  const [payload, setPayload] = useState({
    class_id: 0,
    title: "Daily Roll Call",
    session_date: new Date().toISOString().slice(0, 10),
    notes: "",
  });
  const [busy, setBusy] = useState(false);
  const [formsLoading, setFormsLoading] = useState(false);
  const [formsError, setFormsError] = useState<string | null>(null);
  const [formStreamsPayload, setFormStreamsPayload] = useState<FormStreamsPayload | null>(null);
  const [selectedGradeLevel, setSelectedGradeLevel] = useState("");
  const [selectedStreamKey, setSelectedStreamKey] = useState("");

  const gradeLevels = formStreamsPayload?.grade_levels ?? [];

  const streamOptions: AttendanceFormStream[] = useMemo(() => {
    if (!selectedGradeLevel) {
      return [];
    }

    const dynamics = formStreamsPayload?.streams ?? [];
    return streamOptionsForGrade(classes, dynamics, selectedGradeLevel);
  }, [classes, formStreamsPayload?.streams, selectedGradeLevel]);

  const selectedStreamOption = useMemo(
    () =>
      streamOptions.find(
        (item) => (item.room_id ?? item.stream ?? "") === selectedStreamKey,
      ) ?? null,
    [selectedStreamKey, streamOptions],
  );

  const selectedClass = useMemo(() => {
    if (!selectedGradeLevel || !selectedStreamOption) {
      return null;
    }

    const streamName = selectedStreamOption.stream ?? "";
    return findClassForFormStream(classes, selectedGradeLevel, streamName);
  }, [classes, selectedGradeLevel, selectedStreamOption]);

  const effectiveClassId =
    resolvedClassId && resolvedClassId > 0
      ? resolvedClassId
      : (selectedClass?.id ?? 0);

  const formStreamSelection = useMemo((): FormStreamSelection | null => {
    if (!selectedGradeLevel || !selectedStreamOption) {
      return null;
    }

    return {
      gradeLevel: selectedGradeLevel,
      stream: selectedStreamOption.stream ?? "",
      roomId: selectedStreamOption.room_id ?? null,
      classId: effectiveClassId,
      class: selectedClass,
    };
  }, [effectiveClassId, selectedClass, selectedGradeLevel, selectedStreamOption]);

  useEffect(() => {
    const load = async () => {
      setFormsLoading(true);
      const result = await fetchFormStreamsForSchool(schoolId);
      setFormStreamsPayload(result.payload);
      setFormsError(result.error);
      setSelectedGradeLevel("");
      setSelectedStreamKey("");
      setPayload((current) => ({ ...current, class_id: 0 }));
      setFormsLoading(false);
    };

    void load();
  }, [schoolId]);

  useEffect(() => {
    onFormStreamChange?.(formStreamSelection);
  }, [formStreamSelection, onFormStreamChange]);

  function applyGrade(gradeLevel: string) {
    setSelectedGradeLevel(gradeLevel);
    setSelectedStreamKey("");

    const options = streamOptionsForGrade(
      classes,
      formStreamsPayload?.streams ?? [],
      gradeLevel,
    );
    if (options.length === 1) {
      const only = options[0];
      setSelectedStreamKey(only.room_id ?? only.stream ?? "");
    }
  }

  return (
    <Card className="p-5 text-sm text-slate-500 dark:text-slate-400">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h3 className="text-lg font-semibold text-slate-900 text-white  dark:text-white">
            Create attendance session
          </h3>
          <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Forms and streams load from Dataverse for the school selected in the header.
          </p>
          {formStreamsPayload?.school_name ? (
            <p className="mt-1 text-xs text-sky-700 dark:text-sky-300">
              Dataverse school: {formStreamsPayload.school_name}
            </p>
          ) : null}
        </div>
        {formsLoading ? (
          <div className="flex items-center gap-2 text-sm text-slate-500">
            <Spinner />
            Loading forms…
          </div>
        ) : null}
      </div>

      {formsError ? (
        <p className="mt-3 rounded-lg border border-amber-300/60 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
          {formsError}
        </p>
      ) : null}

      <div className="mt-5 grid gap-4 md:grid-cols-2">
        <select
          aria-label="Select form or grade"
          className="rounded-xl border  px-3 py-2.5 text-sm outline-none dark:border-slate-700"
          value={selectedGradeLevel}
          disabled={!schoolId || formsLoading || gradeLevels.length === 0}
          onChange={(event) => applyGrade(event.target.value)}
        >
          <option value="">
            {!schoolId
              ? "Select a school in the header first"
              : formsLoading
                ? "Loading forms…"
                : gradeLevels.length === 0
                  ? "No forms found for this school"
                  : "Select form/grade"}
          </option>
          {gradeLevels.map((grade) => (
            <option key={grade} value={grade}>
              {grade}
            </option>
          ))}
        </select>
        <select
          aria-label="Select stream"
          className="rounded-xl border border-slate-200  dark:bg-slate-900 px-3 py-2.5 text-sm outline-none dark:border-slate-700"
          value={selectedStreamKey}
          disabled={!selectedGradeLevel || streamOptions.length === 0}
          onChange={(event) => setSelectedStreamKey(event.target.value)}
        >
          <option value="">
            {selectedGradeLevel
              ? streamOptions.length === 0
                ? "No streams for this form"
                : "Select stream"
              : "Select form/grade first"}
          </option>
          {streamOptions.map((item) => {
            const key = item.room_id ?? item.stream ?? "";
            const label = item.label ?? item.stream ?? "Stream";

            return (
              <option key={key || "default"} value={key}>
                {label}
              </option>
            );
          })}
        </select>
        <input
          aria-label="Roll call session title"
          className="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none dark:border-slate-700 dark:bg-slate-900"
          placeholder="Session title (e.g. Morning Roll Call)"
          value={payload.title}
          onChange={(event) =>
            setPayload((current) => ({ ...current, title: event.target.value }))
          }
        />
        <input
          type="date"
          aria-label="Roll call date"
          className="rounded-xl border border-slate-200  px-3 py-2.5 text-sm outline-none dark:border-slate-700 dark:bg-slate-900"
          value={payload.session_date}
          onChange={(event) =>
            setPayload((current) => ({
              ...current,
              session_date: event.target.value,
            }))
          }
        />
        <input
          aria-label="Selected class mapping"
          className="rounded-xl border border-slate-200  px-3 py-2.5 text-sm text-#df8811 outline-none dark:border-slate-400 dark:bg-slate-900 dark:text-green-600"
          value={
            selectedStreamOption
              ? `${selectedGradeLevel} · ${selectedStreamOption.label ?? selectedStreamOption.stream}`
              : ""
          }
          placeholder="Class mapping"
          readOnly
        />
        <div className="flex items-center text-sm text-slate-600 dark:text-slate-300">
          {studentsLoading
            ? "Loading students from Dataverse…"
            : selectedStreamOption
              ? (studentCount ?? 0) === 0
                ? "No students loaded yet — you can still create a session and sync or mark records later."
                : `${studentCount ?? 0} student(s) for this form/stream`
              : selectedGradeLevel
                ? "Select a stream to load students"
                : ""}
        </div>
        <textarea
          aria-label="Roll call notes"
          className="min-h-28 rounded-xl border border-slate-200  px-3 py-2.5 text-sm outline-none dark:border-slate-700 dark:bg-slate-900 md:col-span-2"
          placeholder="Optional notes"
          value={payload.notes}
          onChange={(event) =>
            setPayload((current) => ({ ...current, notes: event.target.value }))
          }
        />
      </div>
      {createSuccess ? (
        <p className="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
          {createSuccess}
        </p>
      ) : null}
      {createError ? (
        <p className="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
          {createError}
        </p>
      ) : null}
      <div className="mt-5 flex justify-end">
        <Button
          type="button"
          disabled={busy || !payload.title || !selectedStreamOption || !selectedGradeLevel}
          onClick={async () => {
            setBusy(true);
            try {
              await onCreate({
                ...payload,
                class_id: effectiveClassId > 0 ? effectiveClassId : 0,
                grade_level: selectedGradeLevel,
                stream: selectedStreamOption?.stream ?? "",
              });
              setPayload((current) => ({ ...current, notes: "" }));
            } finally {
              setBusy(false);
            }
          }}
        >
          {busy ? "Creating..." : "Create session"}
        </Button>
      </div>
    </Card>
  );
}
