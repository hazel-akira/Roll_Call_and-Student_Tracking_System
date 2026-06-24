"use client";

import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import type { Student } from "@/types";

export function StudentProfileCard({ student }: { student: Student }) {
  return (
    <Card className="overflow-hidden">
      <div className="border-b px-5 py-4">
        <h3 className="section-title">{student.full_name}</h3>
        <p className="mt-1 text-sm text-muted">
          {student.admission_number} · {student.class?.name ?? "No class assigned"}
        </p>
        {student.class?.school?.name ? (
          <p className="mt-1 text-sm text-muted">{student.class.school.name}</p>
        ) : null}
      </div>
      <div className="grid gap-3 px-5 py-4 text-sm sm:grid-cols-2">
        <div>
          <p className="text-xs font-semibold uppercase tracking-wide text-muted">Email</p>
          <p className="mt-1 text-foreground">{student.email ?? "—"}</p>
        </div>
        <div>
          <p className="text-xs font-semibold uppercase tracking-wide text-muted">Status</p>
          <div className="mt-1">
            <Badge value={student.status} />
          </div>
        </div>
      </div>
    </Card>
  );
}
