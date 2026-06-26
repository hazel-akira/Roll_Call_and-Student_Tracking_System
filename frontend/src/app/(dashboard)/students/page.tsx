"use client";

import { useState } from "react";
import { StudentAttendanceReportPanel } from "@/components/students/student-attendance-report-panel";
import { StudentHistoryCard } from "@/components/students/student-history-card";
import { StudentProfileCard } from "@/components/students/student-profile-card";
import { StudentSearchForm } from "@/components/students/student-search-form";
import { Card } from "@/components/ui/card";
import { apiClient } from "@/lib/api/client";
import { searchStudentByAdmission } from "@/lib/students/attendance-report";
import { useSchool } from "@/lib/tenant/school-context";
import type { Student } from "@/types";

type HistoryResponse = {
  student: Student;
  history: {
    data: Array<{
      id: number;
      status: string;
      remark?: string | null;
      marked_at?: string | null;
      session: {
        id: number;
        title: string;
        session_date: string;
        class: string;
        subject: string;
      };
    }>;
  };
};

function asArray<T>(value: unknown): T[] {
  return Array.isArray(value) ? (value as T[]) : [];
}

export default function StudentsPage() {
  const { currentSchool, schoolId } = useSchool();
  const [admissionQuery, setAdmissionQuery] = useState("");
  const [searching, setSearching] = useState(false);
  const [searchError, setSearchError] = useState<string | null>(null);
  const [searchSource, setSearchSource] = useState<"local" | "dynamics" | null>(null);
  const [student, setStudent] = useState<Student | null>(null);
  const [history, setHistory] = useState<HistoryResponse["history"]["data"]>([]);
  const [historyLoading, setHistoryLoading] = useState(false);

  async function loadHistory(selectedStudent: Student) {
    setHistoryLoading(true);

    try {
      const response = await apiClient.get<HistoryResponse>(
        `/students/${selectedStudent.id}/attendance-history`,
      );
      setHistory(asArray<HistoryResponse["history"]["data"][number]>(response.data?.history?.data));
    } catch {
      setHistory([]);
    } finally {
      setHistoryLoading(false);
    }
  }

  async function handleSearch() {
    setSearching(true);
    setSearchError(null);
    setSearchSource(null);
    setStudent(null);
    setHistory([]);

    const result = await searchStudentByAdmission(admissionQuery, schoolId);
    setSearching(false);

    if (result.error) {
      setSearchError(result.error);
      return;
    }

    if (!result.student) {
      return;
    }

    setStudent(result.student);
    setSearchSource(result.source);
    await loadHistory(result.student);
  }

  return (
    <div className="space-y-6">
      <section>
        <p className="page-eyebrow">Student tracking</p>
        <h1 className="page-title">Search students and generate attendance reports</h1>
        {currentSchool ? (
          <p className="mt-2 text-sm text-muted">Students enrolled at {currentSchool.name}</p>
        ) : null}
      </section>

      <StudentSearchForm
        value={admissionQuery}
        loading={searching}
        error={searchError}
        onChange={setAdmissionQuery}
        onSearch={() => void handleSearch()}
      />

      {!student ? (
        <Card className="p-8 text-center text-sm text-muted">
          Enter an admission number and click Search to view a student profile, attendance history,
          and PDF report.
        </Card>
      ) : (
        <div className="space-y-6">
          {searchSource === "dynamics" ? (
            <p className="rounded-lg border border-sky-300/60 bg-sky-50 px-3 py-2 text-sm text-sky-900 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-100">
              Student loaded from Dataverse and synced locally for {currentSchool?.name ?? "this school"}.
            </p>
          ) : null}
          <StudentProfileCard student={student} />
          <div className="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
            <StudentHistoryCard
              student={student}
              history={history}
              loading={historyLoading}
            />
            <StudentAttendanceReportPanel student={student} />
          </div>
        </div>
      )}
    </div>
  );
}
