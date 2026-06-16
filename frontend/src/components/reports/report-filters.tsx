"use client";

import { Button } from "@/components/ui/button";
import type { SchoolClass } from "@/types";

export type ReportFilters = {
  from: string;
  to: string;
  class_id: string;
};

export function ReportFilters({
  classes,
  value,
  onChange,
  onApply,
}: {
  classes: SchoolClass[];
  value: ReportFilters;
  onChange: (value: ReportFilters) => void;
  onApply: () => void;
}) {
  return (
    <div className="grid gap-4 md:grid-cols-[1fr_1fr_1fr_auto]">
      <input
        type="date"
        aria-label="From date"
        title="From date"
        className="rounded-xl border border-slate-200  px-3 py-2.5 text-sm outline-none dark:border-slate-700 dark:bg-slate-900"
        value={value.from}
        onChange={(event) => onChange({ ...value, from: event.target.value })}
      />
      <input
        type="date"
        aria-label="To date"
        title="To date"
        className="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none dark:border-slate-700 dark:bg-slate-900"
        value={value.to}
        onChange={(event) => onChange({ ...value, to: event.target.value })}
      />
      
      <select
        aria-label="Class filter"
        title="Class filter"
        className="rounded-xl border border-slate-200  px-3 py-2.5 text-sm outline-none dark:border-slate-700 "
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
      <Button onClick={onApply}>Apply filters</Button>
    </div>
  );
}
