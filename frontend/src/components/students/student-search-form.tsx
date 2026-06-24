"use client";

import { Search } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";

export function StudentSearchForm({
  value,
  loading,
  error,
  onChange,
  onSearch,
}: {
  value: string;
  loading?: boolean;
  error?: string | null;
  onChange: (value: string) => void;
  onSearch: () => void;
}) {
  return (
    <Card className="p-5">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div className="min-w-[16rem] flex-1">
          <label htmlFor="student-admission-search" className="text-sm font-medium text-foreground">
            Admission number
          </label>
          <input
            id="student-admission-search"
            className="field-control mt-2 w-full"
            placeholder="e.g. ADM-5001"
            value={value}
            onChange={(event) => onChange(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === "Enter") {
                event.preventDefault();
                onSearch();
              }
            }}
          />
          <p className="mt-2 text-xs text-muted">
            Search by admission number, then generate an attendance report for that student.
          </p>
        </div>
        <Button type="button" disabled={loading || !value.trim()} onClick={onSearch}>
          <Search size={16} />
          {loading ? "Searching…" : "Search"}
        </Button>
      </div>
      {error ? (
        <p className="mt-4 rounded-lg border border-amber-300/60 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
          {error}
        </p>
      ) : null}
    </Card>
  );
}
