import { isAxiosError } from "axios";
import { apiClient } from "@/lib/api/client";
import type { Student } from "@/types";

export type StudentAttendanceReportRow = {
  session_date: string | null;
  session_title: string | null;
  class: string | null;
  subject: string | null;
  teacher: string | null;
  status: string;
  remark?: string | null;
  marked_at?: string | null;
};

export type StudentAttendanceReportSummary = {
  records: number;
  present: number;
  absent: number;
  late: number;
  excused: number;
  missing: number;
  sick: number;
  on_leave: number;
  attendance_rate: number;
};

export type StudentAttendanceReport = {
  student: {
    id: number;
    full_name: string;
    admission_number: string;
    email?: string | null;
    class?: string | null;
    school?: string | null;
    status?: string | null;
  };
  filters: {
    from: string | null;
    to: string | null;
  };
  summary: StudentAttendanceReportSummary;
  rows: StudentAttendanceReportRow[];
};

export type StudentAttendanceReportFile = {
  blob: Blob;
  filename: string;
};


function parseFilename(contentDisposition: string | undefined, fallback: string): string {
  if (!contentDisposition) {
    return fallback;
  }

  const match = /filename="?([^";\n]+)"?/i.exec(contentDisposition);

  return match?.[1] ?? fallback;
}

export async function searchStudentByAdmission(
  admissionNumber: string,
  schoolId?: string | null,
): Promise<{ student: Student | null; source: "local" | "dynamics" | null; error: string | null }> {
  const query = admissionNumber.trim();

  if (!query) {
    return { student: null, source: null, error: "Enter an admission number to search." };
  }

  try {
    const response = await apiClient.get<{
      data: Student;
      meta?: { source?: "local" | "dynamics"; dataverse_school?: string };
      message?: string;
    }>("/students/lookup", {
      params: {
        admission_number: query,
        ...(schoolId ? { school_id: schoolId } : {}),
      },
    });

    return {
      student: response.data.data,
      source: response.data.meta?.source ?? "local",
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
        return { student: null, source: null, error: message };
      }
    }

    if (isAxiosError(error) && error.response?.status === 401) {
      return { student: null, source: null, error: "Your session expired. Sign in again." };
    }

    return { student: null, source: null, error: "Unable to search students. Try again." };
  }
}

export async function fetchStudentAttendanceReport(
  studentId: number,
  params?: { from?: string; to?: string },
): Promise<StudentAttendanceReport> {
  const response = await apiClient.get<{ data: StudentAttendanceReport }>(
    `/students/${studentId}/attendance-report`,
    { params },
  );

  return response.data.data;
}

export async function fetchStudentAttendanceReportPdf(
  studentId: number,
  params?: { from?: string; to?: string },
): Promise<StudentAttendanceReportFile> {
  const response = await apiClient.get<Blob>(`/students/${studentId}/attendance-report`, {
    params: { ...params, format: "pdf" },
    responseType: "blob",
  });

  return {
    blob: new Blob([response.data], { type: "application/pdf" }),
    filename: parseFilename(
      response.headers["content-disposition"] as string | undefined,
      "student-attendance-report.pdf",
    ),
  };
}

export function downloadStudentAttendanceReport(file: StudentAttendanceReportFile): void {
  const url = URL.createObjectURL(file.blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = file.filename;
  link.click();
  URL.revokeObjectURL(url);
}
