import { isAxiosError } from "axios";
import { apiClient } from "@/lib/api/client";
import type { Student } from "@/types";

function asArray<T>(value: unknown): T[] {
  return Array.isArray(value) ? (value as T[]) : [];
}

export async function loadStudentsForFormStream(params: {
  classId: number;
  gradeLevel: string;
  stream: string;
  roomId?: string | null;
  schoolId?: string | null;
}): Promise<{
  students: Student[];
  source: "dynamics" | "local";
  error: string | null;
  meta?: {
    count?: number;
    school_name?: string | null;
    local_class_id?: number | null;
  };
}> {
  const { classId, gradeLevel, stream, roomId, schoolId } = params;

  if (!schoolId) {
    return {
      students: [],
      source: "local",
      error: "Select a school in the header before loading students.",
    };
  }

  try {
    const response = await apiClient.get<{
      data: Student[];
      meta?: {
        count?: number;
        school_name?: string | null;
        source?: string;
        local_class_id?: number | null;
      };
    }>("/dynamics/attendance/students", {
      params: {
        class_id: classId > 0 ? classId : undefined,
        grade_level: gradeLevel || undefined,
        stream: stream || undefined,
        room_id: roomId || undefined,
        school_id: schoolId,
      },
    });

    const students = asArray<Student>(response.data?.data);

    return {
      students,
      source: "dynamics",
      error:
        students.length === 0
          ? "No students found for this school and grade in Dataverse. Students are matched by ses_schoolname and ses_gradelevel (Class Stream is often empty). Link Class Stream on records or sync locally."
          : null,
      meta: response.data?.meta,
    };
  } catch (error) {
    let dynamicsMessage = "Dataverse unavailable. Showing local students if any.";

    if (isAxiosError(error)) {
      const apiMessage =
        typeof error.response?.data === "object" &&
        error.response.data !== null &&
        "message" in error.response.data &&
        typeof error.response.data.message === "string"
          ? error.response.data.message
          : null;

      if (error.response?.status === 422) {
        dynamicsMessage = apiMessage ?? "Select a school in the header first.";
      } else if (error.response?.status === 403) {
        dynamicsMessage = "Dynamics access denied.";
      } else if (error.response?.status === 503) {
        dynamicsMessage = apiMessage ?? "Dataverse is temporarily unavailable.";
      } else if (!error.response) {
        dynamicsMessage =
          "Cannot reach the API server. Check that the backend is running and NEXT_PUBLIC_API_URL is correct.";
      }
    }

    if (classId <= 0) {
      return {
        students: [],
        source: "local",
        error: dynamicsMessage,
      };
    }

    try {
      const fallback = await apiClient.get<{ data: { data: Student[] } }>("/students", {
        params: {
          class_id: classId,
          grade_level: gradeLevel || undefined,
          stream: stream || undefined,
          per_page: 100,
        },
      });

      return {
        students: asArray<Student>(fallback.data?.data?.data),
        source: "local",
        error: dynamicsMessage,
      };
    } catch {
      return {
        students: [],
        source: "local",
        error: dynamicsMessage,
      };
    }
  }
}
