import { apiClient } from "@/lib/api/client";
import type { NotificationItem } from "@/types";

export type ReportExportFormat = "pdf" | "xlsx";

export type ReportExportFile = {
  blob: Blob;
  filename: string;
  format: ReportExportFormat;
  mimeType: string;
};

function parseFilename(contentDisposition: string | undefined, fallback: string): string {
  if (!contentDisposition) {
    return fallback;
  }

  const match = /filename="?([^";\n]+)"?/i.exec(contentDisposition);

  return match?.[1] ?? fallback;
}

function normalizeFormat(value: unknown): ReportExportFormat {
  return value === "pdf" ? "pdf" : "xlsx";
}

export function canPreviewReportExport(format: ReportExportFormat): boolean {
  return format === "pdf";
}

export async function fetchReportExport(
  notification: NotificationItem,
): Promise<ReportExportFile> {
  const format = normalizeFormat(notification.data?.format);
  const fallbackName = `attendance-report.${format}`;

  const response = await apiClient.get<Blob>(
    `/reports/exports/${notification.id}/download`,
    { responseType: "blob" },
  );

  const mimeType =
    (response.headers["content-type"] as string | undefined) ??
    (format === "pdf"
      ? "application/pdf"
      : "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");

  return {
    blob: new Blob([response.data], { type: mimeType }),
    filename: parseFilename(
      response.headers["content-disposition"] as string | undefined,
      fallbackName,
    ),
    format,
    mimeType,
  };
}

export function downloadReportExportFile(file: ReportExportFile): void {
  const url = URL.createObjectURL(file.blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = file.filename;
  link.click();
  URL.revokeObjectURL(url);
}

export async function downloadReportExport(notification: NotificationItem): Promise<void> {
  const file = await fetchReportExport(notification);
  downloadReportExportFile(file);
}

export function isReportExportNotification(
  notification: NotificationItem,
): notification is NotificationItem & {
  data: { path: string; format?: string };
} {
  return (
    notification.type === "report" &&
    typeof notification.data?.path === "string" &&
    notification.data.path !== ""
  );
}
