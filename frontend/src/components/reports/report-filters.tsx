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
  exportBusy,
  onExport,
}: {
  classes: SchoolClass[];
  value: ReportFilters;
  onChange: (value: ReportFilters) => void;
  onApply: () => void;
  exportBusy: "xlsx" | "pdf" | null;
  onExport: (format: "xlsx" | "pdf") => void;
}) {
  return (
    <div className="space-y-4">
      <div className="grid gap-4 md:grid-cols-[1fr_1fr_1fr_auto]">
        <input
          type="date"
          aria-label="From date"
          title="From date"
          className="field-control"
          value={value.from}
          onChange={(event) => onChange({ ...value, from: event.target.value })}
        />
        <input
          type="date"
          aria-label="To date"
          title="To date"
          className="field-control"
          value={value.to}
          onChange={(event) => onChange({ ...value, to: event.target.value })}
        />

        <select
          aria-label="Class filter"
          title="Class filter"
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
        <Button onClick={onApply}>Apply filters</Button>
      </div>
      <div className="flex flex-wrap gap-3">
        <Button
          variant="outline"
          disabled={exportBusy !== null}
          onClick={() => onExport("xlsx")}
        >
          {exportBusy === "xlsx" ? "Exporting Excel…" : "Export Excel"}
        </Button>
        <Button
          variant="outline"
          disabled={exportBusy !== null}
          onClick={() => onExport("pdf")}
        >
          {exportBusy === "pdf" ? "Exporting PDF…" : "Export PDF"}
        </Button>
      </div>
    </div>
  );
}
