"use client";

import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { Card } from "@/components/ui/card";
import type { ClassStreamPage } from "@/lib/attendance/load-stream-catalog";
import type { Student } from "@/types";

function studentRowKey(student: Student): string {
  return String(student.id ?? student.admission_number ?? student.full_name);
}

export function ClassStreamDetail({ page }: { page: ClassStreamPage }) {
  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center gap-4">
        <Link
          href="/class-streams"
          className="inline-flex h-9 items-center rounded-xl border px-3 text-sm font-semibold text-foreground hover:bg-(--surface-muted)"
        >
          <ArrowLeft size={16} className="mr-2" />
          All classes
        </Link>
        <Link
          href="/attendance"
          className="inline-flex h-9 items-center rounded-xl bg-(--surface-muted) px-3 text-sm font-semibold text-foreground hover:bg-[rgba(212,174,43,0.18)]"
        >
          Take roll call
        </Link>
      </div>

      <section>
        <p className="page-eyebrow">
          {page.gradeLevel}
        </p>
        <h1 className="page-title">{page.label}</h1>
        <p className="mt-2 text-sm text-muted">
          {page.studentCount} student(s)
          {page.stream ? ` · Stream: ${page.stream}` : ""}
        </p>
        {page.loadError ? (
          <p className="mt-3 rounded-lg border border-amber-300/60 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
            {page.loadError}
          </p>
        ) : null}
      </section>

      <Card className="overflow-hidden">
        <div className="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
          <h2 className="section-title">Students</h2>
        </div>
        <ul className="divide-y divide-slate-200 dark:divide-slate-800">
          {page.students.map((student) => (
            <li
              key={studentRowKey(student)}
              className="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm"
            >
              <div>
                <p className="font-medium text-foreground">{student.full_name}</p>
                <p className="text-muted">{student.admission_number}</p>
              </div>
              <span className="text-muted">{student.status}</span>
            </li>
          ))}
        </ul>
      </Card>
    </div>
  );
}
