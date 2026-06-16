import type { AttendanceFormStream } from "@/lib/attendance/form-streams";
import type { SchoolClass } from "@/types";

export function normalize(value: string | null | undefined): string {
  return (value ?? "").trim().toLowerCase();
}

export function canonicalGrade(value: string | null | undefined): string {
  const normalized = normalize(value).replace(/[^a-z0-9 ]/g, " ");
  const compact = normalized.replace(/\s+/g, " ").trim();
  const numberMatch = compact.match(/\b\d+\b/);
  let number = numberMatch?.[0] ?? "";

  if (!number) {
    const wordsToNumber: Record<string, string> = {
      three: "3",
      four: "4",
      five: "5",
      six: "6",
      seven: "7",
      eight: "8",
      nine: "9",
      ten: "10",
    };
    for (const [word, mapped] of Object.entries(wordsToNumber)) {
      if (compact.includes(word)) {
        number = mapped;
        break;
      }
    }
  }

  const mapByNumber: Record<string, string> = {
    "3": "Form 3",
    "4": "Form 4",
    "5": "Grade 5",
    "6": "Grade 6",
    "7": "Grade 7",
    "8": "Grade 8",
    "9": "Grade 9",
    "10": "Grade 10",
  };

  if (number && mapByNumber[number]) {
    return mapByNumber[number];
  }

  return (value ?? "").trim();
}

export function findClassForFormStream(
  classes: SchoolClass[],
  gradeLevel: string,
  streamName: string,
): SchoolClass | null {
  if (!gradeLevel) {
    return null;
  }

  const matches = classes.filter(
    (item) => canonicalGrade(item.grade_level ?? item.name) === canonicalGrade(gradeLevel),
  );

  if (matches.length === 0) {
    return null;
  }

  if (!streamName) {
    const withoutStream = matches.filter((item) => !normalize(item.section));
    return withoutStream[0] ?? matches[0] ?? null;
  }

  return (
    matches.find(
      (item) =>
        normalize(item.section) === normalize(streamName) ||
        normalize(item.name) === normalize(streamName),
    ) ?? null
  );
}

export function streamOptionsForGrade(
  classes: SchoolClass[],
  dynamicsStreams: AttendanceFormStream[],
  gradeLevel: string,
): AttendanceFormStream[] {
  const fromDynamics = dynamicsStreams.filter(
    (item) => canonicalGrade(item.grade_level) === canonicalGrade(gradeLevel),
  );

  if (fromDynamics.length > 0) {
    return fromDynamics;
  }

  const fromClasses = classes
    .filter((item) => canonicalGrade(item.grade_level ?? item.name) === canonicalGrade(gradeLevel))
    .map((item) => ({
      grade_level: item.grade_level ?? item.name,
      stream: item.section ?? item.name,
      room_id: null,
      label: item.section ?? item.name,
    }));

  if (fromClasses.length > 0) {
    return fromClasses;
  }

  const gradeClasses = classes.filter(
    (item) => canonicalGrade(item.grade_level ?? item.name) === canonicalGrade(gradeLevel),
  );

  if (gradeClasses.some((item) => !normalize(item.section))) {
    return [
      {
        grade_level: gradeLevel,
        stream: "",
        room_id: null,
        label: "Default (no stream)",
      },
    ];
  }

  return [];
}
