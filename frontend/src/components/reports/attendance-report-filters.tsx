"use client";

import { Button } from "@/components/ui/button";
import type { SchoolClass } from "@/types";

export type AttendanceReportFilters = {
  academic_year: string;
  term: string;
  week_start: string;
  class_id: string;
  from: string;
  to: string;
};

export function AttendanceReportFiltersForm({
  classes,
  academicYears,
  weeks,
  value,
  onChange,
  onApply,
  exportBusy,
  onExport,
}: {
  classes: SchoolClass[];
  academicYears: string[];
  weeks: Array<{ value: string; label: string }>;
  value: AttendanceReportFilters;
  onChange: (value: AttendanceReportFilters) => void;
  onApply: () => void;
  exportBusy: "xlsx" | "pdf" | null;
  onExport: (format: "xlsx" | "pdf") => void;
}) {
  return (
    <div className="space-y-4">
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <label className="space-y-1 text-sm">
          <span className="font-medium text-(--text-muted)">Academic year</span>
          <select
            className="field-control"
            value={value.academic_year}
            onChange={(event) => onChange({ ...value, academic_year: event.target.value })}
          >
            <option value="">All years</option>
            {academicYears.map((year) => (
              <option key={year} value={year}>
                {year}
              </option>
            ))}
          </select>
        </label>

        <label className="space-y-1 text-sm">
          <span className="font-medium text-(--text-muted)">Term</span>
          <select
            className="field-control"
            value={value.term}
            onChange={(event) => onChange({ ...value, term: event.target.value })}
          >
            <option value="">All terms</option>
            <option value="1">Term 1</option>
            <option value="2">Term 2</option>
            <option value="3">Term 3</option>
          </select>
        </label>

        <label className="space-y-1 text-sm">
          <span className="font-medium text-(--text-muted)">Week</span>
          <select
            className="field-control"
            value={value.week_start}
            onChange={(event) => onChange({ ...value, week_start: event.target.value })}
          >
            <option value="">All weeks</option>
            {weeks.map((week) => (
              <option key={week.value} value={week.value}>
                {week.label}
              </option>
            ))}
          </select>
        </label>

        <label className="space-y-1 text-sm">
          <span className="font-medium text-(--text-muted)">Class</span>
          <select
            className="field-control"
            value={value.class_id}
            onChange={(event) => onChange({ ...value, class_id: event.target.value })}
          >
            <option value="">All classes</option>
            {classes.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name}
              </option>
            ))}
          </select>
        </label>

        <label className="space-y-1 text-sm">
          <span className="font-medium text-(--text-muted)">From</span>
          <input
            type="date"
            className="field-control"
            value={value.from}
            onChange={(event) => onChange({ ...value, from: event.target.value })}
          />
        </label>

        <label className="space-y-1 text-sm">
          <span className="font-medium text-(--text-muted)">To</span>
          <input
            type="date"
            className="field-control"
            value={value.to}
            onChange={(event) => onChange({ ...value, to: event.target.value })}
          />
        </label>
      </div>

      <div className="flex flex-wrap gap-3">
        <Button onClick={onApply}>Apply filters</Button>
        <Button
          variant="outline"
          disabled={exportBusy !== null}
          onClick={() => onExport("pdf")}
        >
          {exportBusy === "pdf" ? "Exporting PDF…" : "Export PDF"}
        </Button>
        <Button
          variant="outline"
          disabled={exportBusy !== null}
          onClick={() => onExport("xlsx")}
        >
          {exportBusy === "xlsx" ? "Exporting Excel…" : "Export Excel"}
        </Button>
      </div>
    </div>
  );
}
