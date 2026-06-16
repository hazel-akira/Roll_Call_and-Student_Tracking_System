import {
  findClassForFormStream,
  streamOptionsForGrade,
} from "@/lib/attendance/class-matching";
import type { FormStreamsPayload } from "@/lib/attendance/form-streams";
import type { SchoolClass } from "@/types";

export type StreamCatalogEntry = {
  key: string;
  gradeLevel: string;
  stream: string;
  roomId: string | null;
  label: string;
  classId: number;
};

export function streamCatalogKey(
  gradeLevel: string,
  stream: string,
  roomId: string | null,
): string {
  return `${gradeLevel}::${stream}::${roomId ?? ""}`;
}

export function parseStreamCatalogKey(key: string): {
  gradeLevel: string;
  stream: string;
  roomId: string | null;
} | null {
  const parts = key.split("::");
  if (parts.length < 2) {
    return null;
  }

  const gradeLevel = parts[0] ?? "";
  const stream = parts[1] ?? "";
  const roomId = parts[2] ? parts[2] : null;

  if (!gradeLevel) {
    return null;
  }

  return { gradeLevel, stream, roomId };
}

export function buildStreamCatalog(
  classes: SchoolClass[],
  payload: FormStreamsPayload,
): StreamCatalogEntry[] {
  const map = new Map<string, StreamCatalogEntry>();

  for (const gradeLevel of payload.grade_levels) {
    const options = streamOptionsForGrade(classes, payload.streams, gradeLevel);

    for (const option of options) {
      const stream = option.stream ?? "";
      const roomId = option.room_id ?? null;
      const key = streamCatalogKey(gradeLevel, stream, roomId);

      if (map.has(key)) {
        continue;
      }

      const matchedClass = findClassForFormStream(classes, gradeLevel, stream);

      map.set(key, {
        key,
        gradeLevel,
        stream,
        roomId,
        label: option.label ?? option.stream ?? gradeLevel,
        classId: matchedClass?.id ?? 0,
      });
    }
  }

  return Array.from(map.values()).sort((left, right) => {
    const gradeCompare = left.gradeLevel.localeCompare(right.gradeLevel);
    if (gradeCompare !== 0) {
      return gradeCompare;
    }

    return left.label.localeCompare(right.label);
  });
}
