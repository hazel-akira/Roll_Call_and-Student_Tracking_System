"use client";

import { useEffect, useState } from "react";
import { StudentHistoryCard } from "@/components/students/student-history-card";
import { StudentList } from "@/components/students/student-list";
import { Card } from "@/components/ui/card";
import { apiClient } from "@/lib/api/client";
import { useSchool } from "@/lib/tenant/school-context";
import type { Student } from "@/types";

type HistoryResponse = {
  student: Student;
  history: { data: Array<{
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
  }> };
};

function asArray<T>(value: unknown): T[] {
  return Array.isArray(value) ? (value as T[]) : [];
}

export default function StudentsPage() {
  const { currentSchool, revision } = useSchool();
  const [query, setQuery] = useState("");
  const [students, setStudents] = useState<Student[]>([]);
  const [selectedStudent, setSelectedStudent] = useState<Student | null>(null);
  const [history, setHistory] = useState<HistoryResponse["history"]["data"]>([]);

  useEffect(() => {
    void apiClient
      .get<{ data: { data: Student[] } }>("/students", { params: { q: query, per_page: 100 } })
      .then((response) => {
        setStudents(asArray<Student>(response.data?.data?.data));
      })
      .catch(() => {
        setStudents([]);
      });
  }, [query, revision]);

  async function loadHistory(student: Student) {
    setSelectedStudent(student);
    try {
      const response = await apiClient.get<HistoryResponse>(
        `/students/${student.id}/attendance-history`,
      );
      setHistory(asArray<HistoryResponse["history"]["data"][number]>(response.data?.history?.data));
    } catch {
      setHistory([]);
    }
  }

  return (
    <div className="space-y-6">
      <section>
        <p className="text-sm font-semibold uppercase tracking-[0.2em] text-sky-600">
          Student tracking
        </p>
        <h1 className="mt-2 text-3xl font-semibold">Search attendance history and student records</h1>
        {currentSchool ? (
          <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
            Students enrolled at {currentSchool.name}
          </p>
        ) : null}
      </section>
      <Card className="p-4">
        <input
          className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none dark:border-slate-700 dark:bg-slate-900"
          placeholder="Search by admission number, name, or email"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
        />
      </Card>
      <div className="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
        <StudentList
          students={students}
          selectedStudentId={selectedStudent?.id ?? null}
          onSelect={(student) => void loadHistory(student)}
        />
        <StudentHistoryCard student={selectedStudent} history={history} />
      </div>
    </div>
  );
}
