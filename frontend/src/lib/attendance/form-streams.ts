import { isAxiosError } from "axios";
import { apiClient } from "@/lib/api/client";
import { canonicalGrade } from "@/lib/attendance/class-matching";

export type AttendanceFormStream = {
  grade_level: string | null;
  stream: string | null;
  room_id?: string | null;
  label?: string | null;
};

export type FormStreamsPayload = {
  grade_levels: string[];
  streams: AttendanceFormStream[];
  school_name?: string | null;
  local_school?: { id: number; name: string; code: string };
};

function asArray<T>(value: unknown): T[] {
  return Array.isArray(value) ? (value as T[]) : [];
}

export async function fetchFormStreamsForSchool(
  schoolId: string | null,
): Promise<{ payload: FormStreamsPayload | null; error: string | null }> {
  if (!schoolId) {
    return {
      payload: null,
      error: "Select a school in the header to load forms and streams from Dataverse.",
    };
  }

  try {
    const response = await apiClient.get<{ data: FormStreamsPayload }>(
      "/dynamics/attendance/form-streams",
      { params: { school_id: schoolId } },
    );

    const raw = response.data?.data;
    const streams = asArray<AttendanceFormStream>(raw?.streams ?? raw);

    const gradeLevels =
      asArray<string>(raw?.grade_levels).length > 0
        ? asArray<string>(raw?.grade_levels)
        : Array.from(
            new Set(
              streams
                .map((item) => item.grade_level)
                .filter((grade): grade is string => Boolean(grade)),
            ),
          ).sort((a, b) => a.localeCompare(b));

    return {
      payload: {
        grade_levels: gradeLevels,
        streams,
        school_name: raw?.school_name ?? null,
        local_school: raw?.local_school,
      },
      error: null,
    };
  } catch (error) {
    if (isAxiosError(error) && error.response?.data) {
      const data = error.response.data;
      const message =
        typeof data === "object" &&
        data !== null &&
        "message" in data &&
        typeof data.message === "string"
          ? data.message
          : null;

      if (message) {
        return { payload: null, error: message };
      }
    }

    if (isAxiosError(error) && error.response?.status === 422) {
      return { payload: null, error: "Select a school in the header first." };
    }

    return {
      payload: null,
      error: "Unable to load forms and streams from the server.",
    };
  }
}

export function streamsForGrade(
  streams: AttendanceFormStream[],
  gradeLevel: string,
): AttendanceFormStream[] {
  return streams.filter(
    (item) => canonicalGrade(item.grade_level) === canonicalGrade(gradeLevel),
  );
}
