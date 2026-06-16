import { loadStudentsForFormStream } from "@/lib/attendance/load-students";
import type { StreamCatalogEntry } from "@/lib/attendance/stream-catalog";
import type { Student } from "@/types";

export type ClassStreamPage = StreamCatalogEntry & {
  students: Student[];
  studentCount: number;
  loadError: string | null;
  localClassId: number | null;
};

async function mapWithConcurrency<T, R>(
  items: T[],
  concurrency: number,
  mapper: (item: T, index: number) => Promise<R>,
): Promise<R[]> {
  if (items.length === 0) {
    return [];
  }

  const results: R[] = new Array(items.length);
  let nextIndex = 0;

  async function runWorker(): Promise<void> {
    while (nextIndex < items.length) {
      const index = nextIndex;
      nextIndex += 1;
      results[index] = await mapper(items[index], index);
    }
  }

  const workers = Array.from(
    { length: Math.min(concurrency, items.length) },
    () => runWorker(),
  );

  await Promise.all(workers);

  return results;
}

export async function loadClassStreamPages(
  schoolId: string,
  entries: StreamCatalogEntry[],
  options?: {
    concurrency?: number;
    includeEmpty?: boolean;
    onProgress?: (completed: number, total: number) => void;
  },
): Promise<ClassStreamPage[]> {
  const total = entries.length;
  let completed = 0;

  const pages = await mapWithConcurrency(
    entries,
    options?.concurrency ?? 4,
    async (entry) => {
      const result = await loadStudentsForFormStream({
        classId: entry.classId,
        gradeLevel: entry.gradeLevel,
        stream: entry.stream,
        roomId: entry.roomId,
        schoolId,
      });

      completed += 1;
      options?.onProgress?.(completed, total);

      return {
        ...entry,
        students: result.students,
        studentCount: result.students.length,
        loadError: result.error,
        localClassId:
          result.meta?.local_class_id ?? (entry.classId > 0 ? entry.classId : null),
      } satisfies ClassStreamPage;
    },
  );

  const filtered = options?.includeEmpty
    ? pages
    : pages.filter((page) => page.studentCount > 0);

  return filtered.sort((left, right) => {
    const gradeCompare = left.gradeLevel.localeCompare(right.gradeLevel);
    if (gradeCompare !== 0) {
      return gradeCompare;
    }

    return left.label.localeCompare(right.label);
  });
}
