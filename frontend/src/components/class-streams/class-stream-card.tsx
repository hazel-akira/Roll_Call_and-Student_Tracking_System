"use client";

import Link from "next/link";
import { Card } from "@/components/ui/card";
import type { ClassStreamPage } from "@/lib/attendance/load-stream-catalog";

export function ClassStreamCard({ page }: { page: ClassStreamPage }) {
  return (
    <Link
      href={`/class-streams?key=${encodeURIComponent(page.key)}`}
      className="block transition hover:opacity-90"
    >
      <Card className="h-full p-5 hover:border-sky-300 hover:bg-sky-50/50 dark:hover:border-sky-500/40 dark:hover:bg-sky-500/5">
        <p className="text-xs font-semibold uppercase tracking-[0.15em] text-sky-600">
          {page.gradeLevel}
        </p>
        <h3 className="mt-2 text-lg font-semibold text-slate-900 dark:text-white">
          {page.label}
        </h3>
        {page.stream && page.stream !== page.label ? (
          <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{page.stream}</p>
        ) : null}
        <p className="mt-4 text-2xl font-semibold text-slate-900 dark:text-white">
          {page.studentCount}
          <span className="ml-2 text-sm font-normal text-slate-500">students</span>
        </p>
        {page.localClassId ? (
          <p className="mt-2 text-xs text-slate-500">Local class #{page.localClassId}</p>
        ) : null}
      </Card>
    </Link>
  );
}
