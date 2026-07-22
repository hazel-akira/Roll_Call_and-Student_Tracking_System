"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Eye, Pencil } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
import { ReportSchoolHeading } from "@/components/reports/report-school-heading";
import { useAuth } from "@/lib/auth/auth-context";
import { apiClient } from "@/lib/api/client";
import { useSchool } from "@/lib/tenant/school-context";
import { canManageDutyRoster, canViewReports, formatDate, roleHomePath } from "@/lib/utils";
import type { DutyRosterSummary } from "@/types";

export default function DutyRosterReportsPage() {
  const router = useRouter();
  const { user, loading } = useAuth();
  const { currentSchool, revision } = useSchool();
  const canView = canViewReports(user?.role?.slug);
  const canEdit = canManageDutyRoster(user?.role?.slug);
  const [rows, setRows] = useState<DutyRosterSummary[]>([]);
  const [statusFilter, setStatusFilter] = useState<"" | "draft" | "published">("");
  const [loadingRows, setLoadingRows] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadRows = useCallback(async () => {
    setLoadingRows(true);
    setError(null);

    try {
      const response = await apiClient.get<{ data: DutyRosterSummary[] }>("/reports/duty-rosters", {
        params: statusFilter ? { status: statusFilter } : undefined,
      });
      setRows(response.data.data);
    } catch {
      setRows([]);
      setError("Unable to load duty roster history. Confirm a school is selected.");
    } finally {
      setLoadingRows(false);
    }
  }, [statusFilter]);

  useEffect(() => {
    if (loading || !user) {
      return;
    }
    if (!canView) {
      router.replace(roleHomePath(user.role?.slug));
    }
  }, [canView, loading, router, user]);

  useEffect(() => {
    if (!canView) {
      return;
    }
    void loadRows();
  }, [canView, loadRows, revision]);

  if (!canView) {
    return null;
  }

  return (
    <div className="space-y-6">
      <section className="flex flex-wrap items-start justify-between gap-4">
        <ReportSchoolHeading
          school={currentSchool}
          title="Duty Roster Reports"
          subtitle={`Browse published and draft weekly duty rosters${
            currentSchool ? ` for ${currentSchool.name}` : ""
          }. Create and assign staff in the Duty roster module; review and export history here.`}
        />
        {canEdit ? (
          <Button type="button" onClick={() => router.push("/duty-roster")}>
            Manage roster
          </Button>
        ) : null}
      </section>

      <Card className="p-5">
        <div className="flex flex-wrap items-end gap-3">
          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-(--text-muted)">Status</span>
            <select
              className="field-control min-w-44"
              value={statusFilter}
              onChange={(event) =>
                setStatusFilter(event.target.value as "" | "draft" | "published")
              }
            >
              <option value="">All</option>
              <option value="published">Published</option>
              <option value="draft">Draft</option>
            </select>
          </label>
          <Button type="button" variant="secondary" onClick={() => void loadRows()}>
            Refresh
          </Button>
        </div>
        {error ? (
          <p className="mt-3 rounded-lg border border-amber-300/60 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
            {error}
          </p>
        ) : null}
      </Card>

      <Card className="overflow-hidden">
        <div className="border-b border-[rgba(148,163,184,0.18)] px-5 py-4">
          <h2 className="text-base font-semibold text-foreground">Duty history</h2>
          <p className="mt-1 text-sm text-(--text-muted)">
            Published rosters are ready for print and export. Drafts can be finished in the duty roster module.
          </p>
        </div>

        {loadingRows ? (
          <div className="flex items-center justify-center gap-3 p-10">
            <Spinner />
            <span className="text-sm text-(--text-muted)">Loading duty history…</span>
          </div>
        ) : rows.length === 0 ? (
          <div className="p-8 text-center">
            <p className="text-sm text-(--text-muted)">No duty rosters found for this school yet.</p>
            {canEdit ? (
              <Button type="button" className="mt-4" onClick={() => router.push("/duty-roster")}>
                Create a weekly roster
              </Button>
            ) : null}
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="bg-(--surface-muted) text-xs uppercase tracking-wide text-(--text-muted)">
                <tr>
                  <th className="px-4 py-3 font-medium">Week</th>
                  <th className="px-4 py-3 font-medium">Start date</th>
                  <th className="px-4 py-3 font-medium">End date</th>
                  <th className="px-4 py-3 font-medium">Status</th>
                  <th className="px-4 py-3 font-medium">Published by</th>
                  <th className="px-4 py-3 font-medium">Published on</th>
                  <th className="px-4 py-3 font-medium">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[rgba(148,163,184,0.12)]">
                {rows.map((row) => (
                  <tr key={row.id}>
                    <td className="px-4 py-3 font-medium text-foreground">{row.week_label}</td>
                    <td className="px-4 py-3 text-(--text-muted)">{formatDate(row.week_start)}</td>
                    <td className="px-4 py-3 text-(--text-muted)">{formatDate(row.week_end)}</td>
                    <td className="px-4 py-3">
                      <Badge value={row.status ?? "draft"} />
                    </td>
                    <td className="px-4 py-3 text-foreground">
                      {row.published_by_name ?? "—"}
                    </td>
                    <td className="px-4 py-3 text-(--text-muted)">
                      {row.published_at ? formatDate(row.published_at) : "—"}
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex flex-wrap gap-2">
                        <Link
                          href={`/reports/duty-roster/${row.id}`}
                          className="inline-flex h-9 items-center rounded-xl border px-3 text-sm font-semibold text-foreground hover:bg-(--surface-muted)"
                        >
                          <Eye size={14} className="mr-1" />
                          View
                        </Link>
                        {canEdit && row.status === "draft" ? (
                          <Link
                            href="/duty-roster"
                            className="inline-flex h-9 items-center rounded-xl px-3 text-sm font-semibold text-(--color-primary) hover:bg-(--surface-muted)"
                          >
                            <Pencil size={14} className="mr-1" />
                            Edit
                          </Link>
                        ) : null}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </div>
  );
}
