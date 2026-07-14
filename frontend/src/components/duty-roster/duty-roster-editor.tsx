"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { Plus, RotateCcw, Save } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
import { apiClient } from "@/lib/api/client";
import type {
  DutyRosterEntry,
  DutyRosterMeta,
  DutyRosterSummary,
  SchoolStaffMember,
  WeeklyDutyRoster,
} from "@/types";

type EditableEntry = DutyRosterEntry & {
  staff_ids: number[];
};

function startOfWeekIso(date = new Date()) {
  const d = new Date(date);
  const day = d.getDay();
  const diff = d.getDate() - day + (day === 0 ? -6 : 1);
  d.setDate(diff);
  return d.toISOString().slice(0, 10);
}

function entryKey(entry: Pick<EditableEntry, "id" | "category" | "location" | "time_slot" | "sort_order">) {
  return entry.id ?? `${entry.category}-${entry.location}-${entry.time_slot}-${entry.sort_order}`;
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
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [creating, setCreating] = useState(false);
  const [resetting, setResetting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

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
      setEntries(
        loaded.entries.map((entry) => ({
          ...entry,
          staff_ids: entry.staff_ids ?? entry.staff.map((member) => member.id),
        })),
      );
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

  const updateEntryStaff = (entryKeyValue: string, staffIds: number[]) => {
    setEntries((current) =>
      current.map((entry) =>
        entryKey(entry) === entryKeyValue ? { ...entry, staff_ids: staffIds } : entry,
      ),
    );
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
      setEntries(
        saved.entries.map((entry) => ({
          ...entry,
          staff_ids: entry.staff_ids ?? entry.staff.map((member) => member.id),
        })),
      );
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
      setEntries(
        created.entries.map((entry) => ({
          ...entry,
          staff_ids: entry.staff_ids ?? entry.staff.map((member) => member.id),
        })),
      );
      setRosters((current) => [
        {
          id: created.id,
          school_id: created.school_id,
          week_start: created.week_start,
          week_end: created.week_end,
          week_label: created.week_label,
          entries_count: created.entries.length,
        },
        ...current,
      ]);
      setMessage("Standard weekly layout created. Assign staff to each duty row.");
    } catch {
      setError("Unable to create a roster for this week.");
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
      setEntries(
        reset.entries.map((entry) => ({
          ...entry,
          staff_ids: [],
        })),
      );
      setMessage(response.data.message);
    } catch {
      setError("Unable to reset the roster layout.");
    } finally {
      setResetting(false);
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

  return (
    <div className="space-y-6">
      <Card className="space-y-4 p-5">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-sm font-medium text-(--text-muted)">Weekly duty roster</p>
            <h2 className="text-xl font-semibold text-foreground">
              {roster?.week_label ?? "No roster for this school yet"}
            </h2>
            {schoolName ? (
              <p className="mt-1 text-sm text-(--text-muted)">{schoolName}</p>
            ) : null}
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
                <Button type="button" variant="outline" onClick={() => void resetTemplate()} disabled={resetting}>
                  <RotateCcw size={16} className="mr-2" />
                  {resetting ? "Resetting…" : "Reset layout"}
                </Button>
                <Button type="button" onClick={() => void saveRoster()} disabled={saving}>
                  <Save size={16} className="mr-2" />
                  {saving ? "Saving…" : "Save roster"}
                </Button>
              </>
            ) : null}
          </div>
        </div>

        {message ? <p className="text-sm text-emerald-700 dark:text-emerald-300">{message}</p> : null}
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

      {roster
        ? groupedEntries.map(([section, sectionEntries]) => (
            <Card key={section} className="overflow-hidden">
              <div className="border-b border-[rgba(148,163,184,0.18)] bg-(--surface-muted) px-5 py-3">
                <h3 className="text-sm font-semibold uppercase tracking-wide text-[#df8811]">{section}</h3>
              </div>
              <div className="divide-y divide-[rgba(148,163,184,0.12)]">
                {sectionEntries.map((entry) => {
                  const key = entryKey(entry);

                  return (
                    <div
                      key={key}
                      className="grid gap-4 px-5 py-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,2fr)] lg:items-start"
                    >
                      <div>
                        <p className="text-xs font-medium uppercase tracking-wide text-(--text-muted)">Location</p>
                        <p className="mt-1 text-sm font-medium text-foreground">
                          {entry.location || "General"}
                        </p>
                      </div>
                      <div>
                        <p className="text-xs font-medium uppercase tracking-wide text-(--text-muted)">Time slot</p>
                        <p className="mt-1 text-sm text-foreground">{entry.time_slot || "All day"}</p>
                      </div>
                      <div>
                        <label className="text-xs font-medium uppercase tracking-wide text-(--text-muted)">
                          Staff on duty
                        </label>
                        <select
                          multiple
                          className="mt-1 min-h-28 w-full rounded-xl border border-[rgba(148,163,184,0.25)] bg-(--surface-solid) px-3 py-2 text-sm"
                          value={entry.staff_ids.map(String)}
                          onChange={(event) => {
                            const selected = Array.from(event.target.selectedOptions).map((option) =>
                              Number(option.value),
                            );
                            updateEntryStaff(key, selected);
                          }}
                        >
                          {staff.map((member) => (
                            <option key={member.id} value={member.id}>
                              {member.name}
                              {member.job_title ? ` · ${member.job_title}` : ""}
                            </option>
                          ))}
                        </select>
                        <p className="mt-1 text-xs text-(--text-muted)">
                          Hold Ctrl or Cmd to select multiple teachers.
                        </p>
                      </div>
                    </div>
                  );
                })}
              </div>
            </Card>
          ))
        : null}

      {meta ? (
        <p className="text-xs text-(--text-muted)">
          Assigned staff on this roster are included in roll call report emails when the school&apos;s duty roster notification is enabled.
        </p>
      ) : null}
    </div>
  );
}
