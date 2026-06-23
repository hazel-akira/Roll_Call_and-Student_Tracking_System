import { apiClient } from "@/lib/api/client";
import type { NotificationItem } from "@/types";

function parseFilename(contentDisposition: string | undefined, fallback: string): string {
  if (!contentDisposition) {
    return fallback;
  }

  const match = /filename="?([^";\n]+)"?/i.exec(contentDisposition);

  return match?.[1] ?? fallback;
}

export async function downloadReportExport(notification: NotificationItem): Promise<void> {
  const format =
    typeof notification.data?.format === "string" ? notification.data.format : "xlsx";
  const fallbackName = `attendance-report.${format}`;

  const response = await apiClient.get<Blob>(
    `/reports/exports/${notification.id}/download`,
    { responseType: "blob" },
  );

  const filename = parseFilename(
    response.headers["content-disposition"] as string | undefined,
    fallbackName,
  );

  const blob = new Blob([response.data], {
    type: response.headers["content-type"] as string | undefined,
  });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  link.click();
  URL.revokeObjectURL(url);
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
