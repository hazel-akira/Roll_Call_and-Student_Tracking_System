"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import {
  Copy,
  Download,
  Eye,
  Plus,
  Printer,
  RotateCcw,
  Save,
  Send,
  Trash2,
  Users,
} from "lucide-react";
import { SummaryCard } from "@/components/dashboard/summary-card";
import { StaffMultiSelect } from "@/components/duty-roster/staff-multi-select";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
import { apiClient } from "@/lib/api/client";
import { downloadBlobFile } from "@/lib/reports/blob-file";
import { cn } from "@/lib/utils";
import type {
  DutyRosterEntry,
  DutyRosterMeta,
  DutyRosterSummary,
  SchoolStaffMember,
  WeeklyDutyRoster,
} from "@/types";

type EditableEntry = DutyRosterEntry & {
  staff_ids: number[];
  client_key: string;
};

function startOfWeekIso(date = new Date()) {
  const d = new Date(date);
  const day = d.getDay();
  const diff = d.getDate() - day + (day === 0 ? -6 : 1);
  d.setDate(diff);
  return d.toISOString().slice(0, 10);
}

function entryKey(entry: Pick<EditableEntry, "id" | "client_key">): string {
  return entry.id != null ? `id-${entry.id}` : entry.client_key;
}

function applyRoster(loaded: WeeklyDutyRoster): EditableEntry[] {
  return loaded.entries.map((entry) => ({
    ...entry,
    staff_ids: entry.staff_ids ?? entry.staff.map((member) => member.id),
    client_key: entry.id != null ? `id-${entry.id}` : `tmp-${entry.category}-${entry.sort_order}-${Math.random()}`,
  }));
}

function upsertRosterSummary(
  current: DutyRosterSummary[],
  roster: WeeklyDutyRoster,
): DutyRosterSummary[] {
  const next: DutyRosterSummary = {
    id: roster.id,
    school_id: roster.school_id,
    week_start: roster.week_start,
    week_end: roster.week_end,
    week_label: roster.week_label,
    status: roster.status,
    published_at: roster.published_at,
    entries_count: roster.entries.length,
  };

  return [next, ...current.filter((item) => item.id !== roster.id)];
}

export function DutyRosterEditor({
  schoolName,
  revision,
}: {
  schoolName?: string | null;
  revision: number;
}) {
  const [meta, setMeta] = useState<DutyRosterMeta | null>(null);
  const [staff, setStaff] = useState<SchoolStaffMember[]>([]);
  const [rosters, setRosters] = useState<DutyRosterSummary[]>([]);
  const [roster, setRoster] = useState<WeeklyDutyRoster | null>(null);
  const [entries, setEntries] = useState<EditableEntry[]>([]);
  const [activeSection, setActiveSection] = useState<string>("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [creating, setCreating] = useState(false);
  const [resetting, setResetting] = useState(false);
  const [copying, setCopying] = useState(false);
  const [publishing, setPublishing] = useState(false);
  const [previewOpen, setPreviewOpen] = useState(false);
  const [previewExportBusy, setPreviewExportBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [newRowCategory, setNewRowCategory] = useState("");

  const loadRoster = useCallback(async (rosterId?: number) => {
    setLoading(true);
    setError(null);

    try {
      const [metaRes, staffRes, listRes, currentRes] = await Promise.all([
        apiClient.get<DutyRosterMeta>("/duty-roster-meta"),
        apiClient.get<{ data: SchoolStaffMember[] }>("/school-staff"),
        apiClient.get<{ data: DutyRosterSummary[] }>("/duty-rosters"),
        apiClient.get<{ data: WeeklyDutyRoster | null }>("/duty-rosters/current"),
      ]);

      setMeta(metaRes.data);
      setStaff(staffRes.data.data);
      setRosters(listRes.data.data);

      const targetId = rosterId ?? currentRes.data.data?.id ?? listRes.data.data[0]?.id;
      if (!targetId) {
        setRoster(null);
        setEntries([]);
        return;
      }

      const rosterRes = await apiClient.get<{ data: WeeklyDutyRoster }>(`/duty-rosters/${targetId}`);
      const loaded = rosterRes.data.data;
      setRoster(loaded);
      setEntries(applyRoster(loaded));
    } catch {
      setError("Unable to load the duty roster. Confirm a school is selected in the header.");
      setRoster(null);
      setEntries([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadRoster();
  }, [loadRoster, revision]);

  const groupedEntries = useMemo(() => {
    const groups = new Map<string, EditableEntry[]>();

    for (const entry of entries) {
      const label = entry.category_label ?? entry.category;
      const bucket = groups.get(label) ?? [];
      bucket.push(entry);
      groups.set(label, bucket);
    }

    return Array.from(groups.entries());
  }, [entries]);

  useEffect(() => {
    if (groupedEntries.length === 0) {
      setActiveSection("");
      return;
    }

    if (!groupedEntries.some(([section]) => section === activeSection)) {
      setActiveSection(groupedEntries[0][0]);
    }
  }, [activeSection, groupedEntries]);

  const progress = useMemo(() => {
    const total = entries.length;
    const assigned = entries.filter((entry) => entry.staff_ids.length > 0).length;
    const uniqueStaff = new Set(entries.flatMap((entry) => entry.staff_ids)).size;
    const sectionsComplete = groupedEntries.filter(([, rows]) =>
      rows.every((entry) => entry.staff_ids.length > 0),
    ).length;

    return {
      total,
      assigned,
      unassigned: total - assigned,
      uniqueStaff,
      sectionsComplete,
      sectionsTotal: groupedEntries.length,
      percent: total > 0 ? Math.round((assigned / total) * 100) : 0,
    };
  }, [entries, groupedEntries]);

  const previousWeekAvailable = useMemo(() => {
    if (!roster) {
      return false;
    }

    return rosters.some(
      (item) => item.id !== roster.id && item.week_start < roster.week_start,
    );
  }, [roster, rosters]);

  const activeEntries = useMemo(() => {
    return groupedEntries.find(([section]) => section === activeSection)?.[1] ?? [];
  }, [activeSection, groupedEntries]);

  const previewGroups = useMemo(() => {
    return groupedEntries.map(([section, sectionEntries]) => ({
      section,
      rows: sectionEntries.map((entry) => ({
        key: entryKey(entry),
        location: entry.location || "General",
        time_slot: entry.time_slot || "All day",
        staff: staff
          .filter((member) => entry.staff_ids.includes(member.id))
          .map((member) => member.name)
          .join(", ") || "Unassigned",
        assigned: entry.staff_ids.length > 0,
      })),
    }));
  }, [groupedEntries, staff]);

  const updateEntryStaff = (entryKeyValue: string, staffIds: number[]) => {
    setEntries((current) =>
      current.map((entry) =>
        entryKey(entry) === entryKeyValue ? { ...entry, staff_ids: staffIds } : entry,
      ),
    );
  };

  const updateEntryField = (
    entryKeyValue: string,
    field: "location" | "time_slot",
    nextValue: string,
  ) => {
    setEntries((current) =>
      current.map((entry) =>
        entryKey(entry) === entryKeyValue ? { ...entry, [field]: nextValue } : entry,
      ),
    );
  };

  const removeEntry = (entryKeyValue: string) => {
    setEntries((current) => current.filter((entry) => entryKey(entry) !== entryKeyValue));
  };

  const addDutyRow = () => {
    if (!meta || !newRowCategory) {
      return;
    }

    const label = meta.categories[newRowCategory] ?? newRowCategory;
    const nextSort =
      entries.reduce((max, entry) => Math.max(max, entry.sort_order ?? 0), 0) + 10;

    setEntries((current) => [
      ...current,
      {
        category: newRowCategory,
        category_label: label,
        location: "",
        time_slot: "",
        sort_order: nextSort,
        staff_ids: [],
        staff: [],
        client_key: `tmp-${newRowCategory}-${nextSort}-${Date.now()}`,
      },
    ]);
    setActiveSection(label);
    setNewRowCategory("");
    setMessage("Duty row added for this week only. Save draft to keep it. School default is unchanged.");
  };

  const saveRoster = async () => {
    if (!roster) {
      return;
    }

    setSaving(true);
    setMessage(null);
    setError(null);

    try {
      const response = await apiClient.put<{ data: WeeklyDutyRoster; message: string }>(
        `/duty-rosters/${roster.id}`,
        {
          entries: entries.map((entry, index) => ({
            id: entry.id,
            category: entry.category,
            location: entry.location,
            time_slot: entry.time_slot,
            sort_order: entry.sort_order ?? (index + 1) * 10,
            staff_ids: entry.staff_ids,
          })),
        },
      );

      const saved = response.data.data;
      setRoster(saved);
      setEntries(applyRoster(saved));
      setRosters((current) => upsertRosterSummary(current, saved));
      setMessage(response.data.message);
    } catch {
      setError("Unable to save the duty roster. Check staff assignments and try again.");
    } finally {
      setSaving(false);
    }
  };

  const createRoster = async () => {
    setCreating(true);
    setMessage(null);
    setError(null);

    try {
      const weekStart = startOfWeekIso();
      const response = await apiClient.post<{ data: WeeklyDutyRoster; message: string }>(
        "/duty-rosters",
        {
          week_start: weekStart,
          week_end: new Date(new Date(weekStart).getTime() + 6 * 86400000)
            .toISOString()
            .slice(0, 10),
        },
      );

      const created = response.data.data;
      setRoster(created);
      setEntries(applyRoster(created));
      setRosters((current) => upsertRosterSummary(current, created));
      setMessage("Draft roster created. Assign staff, then preview and publish when ready.");
    } catch (err) {
      const apiMessage =
        typeof err === "object" &&
        err !== null &&
        "response" in err &&
        typeof (err as { response?: { data?: { message?: string } } }).response?.data?.message ===
          "string"
          ? (err as { response: { data: { message: string } } }).response.data.message
          : null;
      setError(apiMessage ?? "Unable to create a roster for this week.");
    } finally {
      setCreating(false);
    }
  };

  const resetTemplate = async () => {
    if (!roster) {
      return;
    }

    setResetting(true);
    setMessage(null);
    setError(null);

    try {
      const response = await apiClient.post<{ data: WeeklyDutyRoster; message: string }>(
        `/duty-rosters/${roster.id}/reset-template`,
      );

      const reset = response.data.data;
      setRoster(reset);
      setEntries(applyRoster(reset));
      setRosters((current) => upsertRosterSummary(current, reset));
      setMessage(response.data.message);
    } catch {
      setError("Unable to reset the roster layout.");
    } finally {
      setResetting(false);
    }
  };

  const copyPreviousWeek = async () => {
    if (!roster) {
      return;
    }

    setCopying(true);
    setMessage(null);
    setError(null);

    try {
      const response = await apiClient.post<{ data: WeeklyDutyRoster; message: string }>(
        `/duty-rosters/${roster.id}/copy-from-previous`,
      );

      const copied = response.data.data;
      setRoster(copied);
      setEntries(applyRoster(copied));
      setRosters((current) => upsertRosterSummary(current, copied));
      setMessage(response.data.message);
    } catch {
      setError("Unable to copy the previous week. Confirm an earlier roster exists for this school.");
    } finally {
      setCopying(false);
    }
  };

  const publishRoster = async () => {
    if (!roster) {
      return;
    }

    setPublishing(true);
    setMessage(null);
    setError(null);

    try {
      await apiClient.put(`/duty-rosters/${roster.id}`, {
        entries: entries.map((entry, index) => ({
          id: entry.id,
          category: entry.category,
          location: entry.location,
          time_slot: entry.time_slot,
          sort_order: entry.sort_order ?? (index + 1) * 10,
          staff_ids: entry.staff_ids,
        })),
      });

      const response = await apiClient.post<{ data: WeeklyDutyRoster; message: string }>(
        `/duty-rosters/${roster.id}/publish`,
      );

      const published = response.data.data;
      setRoster(published);
      setEntries(applyRoster(published));
      setRosters((current) => upsertRosterSummary(current, published));
      setPreviewOpen(false);
      setMessage(response.data.message);
    } catch {
      setError(
        progress.unassigned > 0
          ? `Assign staff to all ${progress.unassigned} remaining duty row(s) before publishing.`
          : "Unable to publish the duty roster.",
      );
    } finally {
      setPublishing(false);
    }
  };

  const downloadPreviewPdf = async () => {
    if (!roster) {
      return;
    }

    setPreviewExportBusy(true);
    setError(null);

    try {
      if (roster.status !== "published") {
        // Persist current draft assignments so the PDF matches what is on screen.
        const saveResponse = await apiClient.put<{ data: WeeklyDutyRoster }>(
          `/duty-rosters/${roster.id}`,
          {
            entries: entries.map((entry, index) => ({
              id: entry.id,
              category: entry.category,
              location: entry.location,
              time_slot: entry.time_slot,
              sort_order: entry.sort_order ?? (index + 1) * 10,
              staff_ids: entry.staff_ids,
            })),
          },
        );
        const saved = saveResponse.data.data;
        setRoster(saved);
        setEntries(applyRoster(saved));
        setRosters((current) => upsertRosterSummary(current, saved));
      }

      const response = await apiClient.get<Blob>(`/reports/duty-rosters/${roster.id}/export`, {
        params: { format: "pdf" },
        responseType: "blob",
      });

      const disposition = response.headers["content-disposition"] as string | undefined;
      const match = disposition ? /filename="?([^";\n]+)"?/i.exec(disposition) : null;

      downloadBlobFile({
        blob: new Blob([response.data], { type: "application/pdf" }),
        filename: match?.[1] ?? `duty-roster-${roster.week_start}.pdf`,
        mimeType: "application/pdf",
      });
    } catch {
      setError("Unable to download the roster PDF. Save the draft and try again.");
    } finally {
      setPreviewExportBusy(false);
    }
  };

  if (loading) {
    return (
      <Card className="flex items-center justify-center gap-3 p-10">
        <Spinner />
        <span className="text-sm text-(--text-muted)">Loading duty roster…</span>
      </Card>
    );
  }

  const isPublished = roster?.status === "published";

  return (
    <div className="space-y-6">
      {roster ? (
        <section className="space-y-4">
          <div className="flex flex-wrap items-end justify-between gap-3">
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <p className="text-sm font-medium text-(--text-muted)">This week at a glance</p>
                <Badge value={roster.status} />
              </div>
              <h2 className="mt-1 text-xl font-semibold text-foreground">{roster.week_label}</h2>
              {schoolName ? (
                <p className="mt-1 text-sm text-(--text-muted)">{schoolName}</p>
              ) : null}
            </div>
            <p className="text-sm text-(--text-muted)">
              {progress.assigned}/{progress.total} rows assigned · {progress.percent}% complete
            </p>
          </div>

          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <SummaryCard
              label="Assignment progress"
              value={`${progress.assigned}/${progress.total}`}
              helper={`${progress.percent}% of duty rows have staff`}
            />
            <SummaryCard
              label="Unassigned rows"
              value={progress.unassigned}
              helper={progress.unassigned === 0 ? "Ready to publish" : "Still need staff"}
            />
            <SummaryCard
              label="Staff on roster"
              value={progress.uniqueStaff}
              helper="Unique people assigned this week"
            />
            <SummaryCard
              label="Sections complete"
              value={`${progress.sectionsComplete}/${progress.sectionsTotal}`}
              helper={isPublished ? "Published for reports" : "Draft — not yet live"}
            />
          </div>

          <Card className="space-y-3 p-5">
            <div className="flex items-center justify-between gap-3">
              <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                <Users size={16} className="text-(--color-primary)" />
                Filling progress
              </div>
              <span className="text-sm text-(--text-muted)">{progress.percent}%</span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-(--surface-muted)">
              <div
                className={cn(
                  "h-full rounded-full transition-all",
                  progress.percent === 100 ? "bg-emerald-500" : "bg-(--color-primary)",
                )}
                style={{ width: `${progress.percent}%` }}
              />
            </div>
          </Card>
        </section>
      ) : null}

      <Card className="space-y-4 p-5">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-sm font-medium text-(--text-muted)">Week controls</p>
            <h2 className="text-lg font-semibold text-foreground">
              {roster ? "Manage this week" : "No roster for this school yet"}
            </h2>
            <p className="mt-1 text-sm text-(--text-muted)">
              Switch weeks, save drafts, preview, and publish when every row is assigned.
            </p>
          </div>

          <div className="flex flex-wrap gap-2">
            {rosters.length > 0 ? (
              <label className="flex flex-col gap-1 text-sm">
                <span className="font-medium text-(--text-muted)">Switch week</span>
                <select
                  className="rounded-xl border border-[rgba(148,163,184,0.25)] bg-(--surface-solid) px-3 py-2"
                  value={roster?.id ?? ""}
                  onChange={(event) => void loadRoster(Number(event.target.value))}
                >
                  {rosters.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.week_label}
                      {item.status === "draft" ? " (draft)" : ""}
                    </option>
                  ))}
                </select>
              </label>
            ) : null}
            <Button type="button" variant="secondary" onClick={() => void createRoster()} disabled={creating}>
              <Plus size={16} className="mr-2" />
              {creating ? "Creating…" : "New week"}
            </Button>
            {roster ? (
              <>
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => void copyPreviousWeek()}
                  disabled={copying || !previousWeekAvailable}
                  title={
                    previousWeekAvailable
                      ? "Copy staff assignments from the previous week"
                      : "No earlier week available to copy"
                  }
                >
                  <Copy size={16} className="mr-2" />
                  {copying ? "Copying…" : "Copy previous week"}
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => void resetTemplate()}
                  disabled={resetting}
                  title="Restore this school's default duty locations and clear staff for the week"
                >
                  <RotateCcw size={16} className="mr-2" />
                  {resetting ? "Resetting…" : "Reset to school default"}
                </Button>
                <Button type="button" variant="secondary" onClick={() => setPreviewOpen(true)}>
                  <Eye size={16} className="mr-2" />
                  Preview
                </Button>
                <Button type="button" onClick={() => void saveRoster()} disabled={saving}>
                  <Save size={16} className="mr-2" />
                  {saving ? "Saving…" : "Save draft"}
                </Button>
                <Button
                  type="button"
                  onClick={() => void publishRoster()}
                  disabled={publishing || progress.unassigned > 0}
                >
                  <Send size={16} className="mr-2" />
                  {publishing ? "Publishing…" : "Publish"}
                </Button>
              </>
            ) : null}
          </div>
        </div>

        {message ? (
          <p className="text-sm text-emerald-700 dark:text-emerald-300">
            {message}
            {isPublished ? (
              <>
                {" "}
                <Link
                  href={`/reports/duty-roster/${roster?.id}`}
                  className="font-semibold underline underline-offset-2"
                >
                  View in Duty Roster Reports
                </Link>
              </>
            ) : null}
          </p>
        ) : null}
        {error ? (
          <p className="rounded-lg border border-amber-300/60 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
            {error}
          </p>
        ) : null}

        {!roster ? (
          <div className="rounded-2xl border border-dashed border-[rgba(148,163,184,0.35)] p-8 text-center">
            <p className="text-sm text-(--text-muted)">
              Create a weekly roster to load the standard dining hall, boarding, tuition, games, and Sunday service layout.
            </p>
            <Button type="button" className="mt-4" onClick={() => void createRoster()} disabled={creating}>
              Create this week&apos;s roster
            </Button>
          </div>
        ) : null}
      </Card>

      {roster ? (
        <Card className="overflow-visible">
          <div className="border-b border-[rgba(148,163,184,0.18)] px-5 py-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
              <div>
                <h3 className="text-base font-semibold text-foreground">Duty assignments</h3>
                <p className="mt-1 text-sm text-(--text-muted)">
                  Assign staff for this week. You can edit locations here without changing the school
                  default configured by Admin.
                </p>
              </div>
              {meta ? (
                <div className="flex flex-wrap items-end gap-2">
                  <label className="flex flex-col gap-1 text-sm">
                    <span className="font-medium text-(--text-muted)">Add location to this week</span>
                    <select
                      className="rounded-xl border border-[rgba(148,163,184,0.25)] bg-(--surface-solid) px-3 py-2"
                      value={newRowCategory}
                      onChange={(event) => setNewRowCategory(event.target.value)}
                    >
                      <option value="">Choose section…</option>
                      {Object.entries(meta.categories).map(([key, label]) => (
                        <option key={key} value={key}>
                          {label}
                        </option>
                      ))}
                    </select>
                  </label>
                  <Button
                    type="button"
                    variant="secondary"
                    disabled={!newRowCategory}
                    onClick={addDutyRow}
                  >
                    <Plus size={16} className="mr-2" />
                    Add row
                  </Button>
                </div>
              ) : null}
            </div>
          </div>

          <div className="flex gap-1 overflow-x-auto border-b border-[rgba(148,163,184,0.18)] bg-(--surface-muted) px-2 py-2">
            {groupedEntries.map(([section, sectionEntries]) => {
              const sectionAssigned = sectionEntries.filter((entry) => entry.staff_ids.length > 0).length;
              const complete = sectionAssigned === sectionEntries.length;

              return (
                <button
                  key={section}
                  type="button"
                  onClick={() => setActiveSection(section)}
                  className={cn(
                    "shrink-0 rounded-xl px-3 py-2 text-left text-sm transition",
                    activeSection === section
                      ? "bg-(--surface-solid) font-semibold text-foreground shadow-sm"
                      : "text-(--text-muted) hover:bg-(--surface-solid)/70 hover:text-foreground",
                  )}
                >
                  <span className="block">{section}</span>
                  <span
                    className={cn(
                      "mt-0.5 block text-xs",
                      complete ? "text-emerald-600 dark:text-emerald-300" : "text-(--text-muted)",
                    )}
                  >
                    {sectionAssigned}/{sectionEntries.length}
                    {complete ? " · done" : ""}
                  </span>
                </button>
              );
            })}
          </div>

          <div className="divide-y divide-[rgba(148,163,184,0.12)]">
            {activeEntries.length === 0 ? (
              <p className="px-5 py-8 text-center text-sm text-(--text-muted)">
                No rows in this section. Add a location above, or reset to the school default layout.
              </p>
            ) : (
              activeEntries.map((entry) => {
                const key = entryKey(entry);

                return (
                  <div
                    key={key}
                    className="grid gap-4 px-5 py-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,2fr)_auto] lg:items-start"
                  >
                    <div>
                      <label className="text-xs font-medium uppercase tracking-wide text-(--text-muted)">
                        Location
                      </label>
                      <input
                        className="field-control mt-1"
                        value={entry.location ?? ""}
                        placeholder="General"
                        onChange={(event) => updateEntryField(key, "location", event.target.value)}
                      />
                    </div>
                    <div>
                      <label className="text-xs font-medium uppercase tracking-wide text-(--text-muted)">
                        Time slot
                      </label>
                      <input
                        className="field-control mt-1"
                        value={entry.time_slot ?? ""}
                        placeholder="All day"
                        onChange={(event) => updateEntryField(key, "time_slot", event.target.value)}
                      />
                    </div>
                    <div>
                      <label className="text-xs font-medium uppercase tracking-wide text-(--text-muted)">
                        Staff on duty
                      </label>
                      <div className="mt-1">
                        <StaffMultiSelect
                          options={staff}
                          value={entry.staff_ids}
                          onChange={(next) => updateEntryStaff(key, next)}
                        />
                      </div>
                    </div>
                    <div className="flex items-end">
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="text-rose-600 hover:bg-rose-500/10 hover:text-rose-500"
                        title="Remove this location from this week only"
                        onClick={() => removeEntry(key)}
                      >
                        <Trash2 size={16} />
                      </Button>
                    </div>
                  </div>
                );
              })
            )}
          </div>
        </Card>
      ) : null}

      {meta ? (
        <p className="text-xs text-(--text-muted)">
          School default locations are configured by Admin under Schools → Duty roster default layout.
          Week edits here do not change that default. After publish, use{" "}
          <Link href="/reports/duty-roster" className="font-medium text-(--color-primary) hover:underline">
            Reports → Duty Roster Reports
          </Link>{" "}
          to view history, print, or export.
        </p>
      ) : null}

      {previewOpen && roster ? (
        <div className="fixed inset-0 z-40 flex items-end justify-center bg-black/45 p-4 sm:items-center print:static print:bg-transparent print:p-0">
          <div
            className="absolute inset-0 print:hidden"
            onClick={() => setPreviewOpen(false)}
            aria-hidden
          />
          <Card className="relative z-10 flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden print:max-h-none print:max-w-none print:overflow-visible print:border-0 print:shadow-none">
            <div className="border-b border-[rgba(148,163,184,0.18)] px-5 py-4 print:border-0">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p className="text-sm font-medium text-(--text-muted)">Roster preview</p>
                  <h3 className="text-lg font-semibold text-foreground">{roster.week_label}</h3>
                  <p className="mt-1 text-sm text-(--text-muted)">
                    {progress.assigned}/{progress.total} rows assigned · {progress.uniqueStaff} staff
                  </p>
                </div>
                <Badge value={roster.status} />
              </div>
            </div>

            <div className="flex-1 space-y-4 overflow-y-auto px-5 py-4 print:overflow-visible">
              {previewGroups.map((group) => (
                <div key={group.section}>
                  <h4 className="mb-2 text-sm font-semibold uppercase tracking-wide text-[#df8811]">
                    {group.section}
                  </h4>
                  <div className="overflow-hidden rounded-xl border border-[rgba(148,163,184,0.18)]">
                    <table className="w-full text-left text-sm">
                      <thead className="bg-(--surface-muted) text-xs uppercase tracking-wide text-(--text-muted)">
                        <tr>
                          <th className="px-3 py-2 font-medium">Location</th>
                          <th className="px-3 py-2 font-medium">Time</th>
                          <th className="px-3 py-2 font-medium">Staff</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-[rgba(148,163,184,0.12)]">
                        {group.rows.map((row) => (
                          <tr key={row.key}>
                            <td className="px-3 py-2 font-medium text-foreground">{row.location}</td>
                            <td className="px-3 py-2 text-(--text-muted)">{row.time_slot}</td>
                            <td
                              className={cn(
                                "px-3 py-2",
                                row.assigned ? "text-foreground" : "text-amber-700 dark:text-amber-300",
                              )}
                            >
                              {row.staff}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              ))}
            </div>

            <div className="flex flex-wrap items-center justify-end gap-2 border-t border-[rgba(148,163,184,0.18)] px-5 py-4 print:hidden">
              <Button type="button" variant="outline" onClick={() => window.print()}>
                <Printer size={16} className="mr-2" />
                Print
              </Button>
              <Button
                type="button"
                variant="outline"
                disabled={previewExportBusy}
                onClick={() => void downloadPreviewPdf()}
              >
                <Download size={16} className="mr-2" />
                {previewExportBusy ? "Downloading…" : "Download PDF"}
              </Button>
              <Button type="button" variant="secondary" onClick={() => setPreviewOpen(false)}>
                Close
              </Button>
            </div>
          </Card>
        </div>
      ) : null}
    </div>
  );
}
