"use client";

import { Card } from "@/components/ui/card";
import type { Student } from "@/types";

export function StudentList({
  students,
  selectedStudentId,
  onSelect,
}: {
  students?: Student[];
  selectedStudentId: number | null;
  onSelect: (student: Student) => void;
}) {
  const safeStudents = students ?? [];

  return (
    <Card className="overflow-hidden">
      <div className="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
        <h3 className="text-lg font-semibold text-slate-900 dark:text-white">Students</h3>
      </div>
      <div className="divide-y divide-slate-200 dark:divide-slate-800">
        {safeStudents.map((student) => (
          <button
            key={student.id}
            className={`w-full px-5 py-4 text-left transition hover:bg-slate-50 dark:hover:bg-slate-900 ${
              selectedStudentId === student.id ? "bg-sky-50 dark:bg-sky-500/10" : ""
            }`}
            onClick={() => onSelect(student)}
          >
            <p className="font-medium text-slate-900 dark:text-white">{student.full_name}</p>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
              {student.admission_number} · {student.class?.name ?? "No class"}
            </p>
          </button>
        ))}
      </div>
    </Card>
  );
}
