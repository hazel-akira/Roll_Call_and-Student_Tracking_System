"use client";

import { useCallback, useMemo, useState } from "react";
import { useSearchParams } from "next/navigation";
import { ClassStreamCard } from "@/components/class-streams/class-stream-card";
import { ClassStreamDetail } from "@/components/class-streams/class-stream-detail";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
import { apiClient } from "@/lib/api/client";
import { fetchFormStreamsForSchool } from "@/lib/attendance/form-streams";
import {
  loadClassStreamPages,
  type ClassStreamPage,
} from "@/lib/attendance/load-stream-catalog";
import { buildStreamCatalog } from "@/lib/attendance/stream-catalog";
import { useSchool } from "@/lib/tenant/school-context";
import type { SchoolClass } from "@/types";

function asArray<T>(value: unknown): T[] {
  return Array.isArray(value) ? (value as T[]) : [];
}

export default function ClassStreamsPage() {
  const { currentSchool, schoolId } = useSchool();
  const searchParams = useSearchParams();
  const selectedKey = searchParams.get("key");

  const [pages, setPages] = useState<ClassStreamPage[]>([]);
  const [loading, setLoading] = useState(false);
  const [progress, setProgress] = useState<{ done: number; total: number } | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loadedAt, setLoadedAt] = useState<string | null>(null);

  const selectedPage = useMemo(
    () => pages.find((page) => page.key === selectedKey) ?? null,
    [pages, selectedKey],
  );

  const loadClasses = useCallback(async () => {
    const response = await apiClient.get<{ data: SchoolClass[] }>("/classes");
    return asArray<SchoolClass>(response.data?.data);
  }, []);

  const loadAllStreamPages = useCallback(async () => {
    if (!schoolId) {
      setError("Select a school in the header before loading class streams.");
      return;
    }

    setLoading(true);
    setError(null);
    setProgress(null);
    setPages([]);

    try {
      const [localClasses, formStreamsResult] = await Promise.all([
        loadClasses(),
        fetchFormStreamsForSchool(schoolId),
      ]);

      if (formStreamsResult.error || !formStreamsResult.payload) {
        setError(formStreamsResult.error ?? "Unable to load forms and streams from Dataverse.");
        return;
      }

      const catalog = buildStreamCatalog(localClasses, formStreamsResult.payload);

      if (catalog.length === 0) {
        setError("No forms or streams were found for this school.");
        return;
      }

      setProgress({ done: 0, total: catalog.length });

      const streamPages = await loadClassStreamPages(schoolId, catalog, {
        onProgress: (done, total) => setProgress({ done, total }),
      });

      setPages(streamPages);
      setLoadedAt(new Date().toLocaleString());

      if (streamPages.length === 0) {
        setError(
          "No students were found for any class or stream. Check Dataverse school and grade mapping.",
        );
      }
    } catch {
      setError("Failed to load students for class streams.");
      setPages([]);
    } finally {
      setLoading(false);
      setProgress(null);
    }
  }, [loadClasses, schoolId]);

  if (selectedKey && selectedPage) {
    return <ClassStreamDetail page={selectedPage} />;
  }

  if (selectedKey && !loading && pages.length > 0 && !selectedPage) {
    return (
      <div className="space-y-6">
        <Card className="border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
          That class or stream was not found. Load all streams again or pick another card.
        </Card>
        <Button onClick={() => void loadAllStreamPages()}>Load all classes & streams</Button>
      </div>
    );
  }

  const progressPercent =
    progress && progress.total > 0
      ? Math.round((progress.done / progress.total) * 100)
      : 0;

  return (
    <div className="space-y-6">
      <section className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="page-eyebrow">
            Class streams
          </p>
          <h1 className="page-title">Browse classes and streams with students</h1>
          {currentSchool ? (
            <p className="mt-2 text-sm text-muted">
              Loads every form and stream from Dataverse for {currentSchool.name}, then builds a
              page for each group that has students.
            </p>
          ) : (
            <p className="mt-2 text-sm text-amber-700 dark:text-amber-200">
              Select a school in the header first.
            </p>
          )}
          {loadedAt ? (
            <p className="mt-1 text-xs text-muted">Last loaded: {loadedAt}</p>
          ) : null}
        </div>
        <Button
          disabled={loading || !schoolId}
          onClick={() => void loadAllStreamPages()}
        >
          {loading ? "Loading students…" : "Load all classes & streams"}
        </Button>
      </section>

      {loading && progress ? (
        <Card className="p-5">
          <div className="flex items-center gap-3">
            <Spinner />
            <div className="flex-1">
              <p className="text-sm font-medium text-foreground">
                Loading students ({progress.done} of {progress.total})
              </p>
              <div className="mt-3 h-2 overflow-hidden rounded-full bg-(--surface-muted)">
                <div
                  className="h-full rounded-full bg-sky-500 transition-all"
                  style={{ width: `${progressPercent}%` }}
                />
              </div>
            </div>
          </div>
        </Card>
      ) : null}

      {error ? (
        <Card className="border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
          {error}
        </Card>
      ) : null}

      {pages.length > 0 ? (
        <>
          <p className="text-sm text-muted">
            {pages.length} class/stream {pages.length === 1 ? "page" : "pages"} with students ·{" "}
            {pages.reduce((sum, page) => sum + page.studentCount, 0)} students total
          </p>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {pages.map((page) => (
              <ClassStreamCard key={page.key} page={page} />
            ))}
          </div>
        </>
      ) : !loading && !error && schoolId ? (
        <Card className="p-8 text-center text-sm text-muted">
          Click &quot;Load all classes & streams&quot; to fetch students from Dataverse and build
          pages for each form and stream that has enrolments.
        </Card>
      ) : null}
    </div>
  );
}
