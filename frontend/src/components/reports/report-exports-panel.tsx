"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { Eye, FileDown } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
import { ReportExportPreview } from "@/components/reports/report-export-preview";
import { apiClient } from "@/lib/api/client";
import {
  downloadReportExport,
  isReportExportNotification,
} from "@/lib/reports/download-export";
import { formatDate } from "@/lib/utils";
import type { NotificationItem } from "@/types";

type NotificationsResponse = {
  data: NotificationItem[];
};

function asNotificationList(value: unknown): NotificationItem[] {
  if (!value || typeof value !== "object" || !("data" in value)) {
    return [];
  }

  const data = (value as NotificationsResponse).data;

  return Array.isArray(data) ? data : [];
}

export function ReportExportsPanel({
  refreshKey = 0,
  pollForNewExport = false,
  onPollComplete,
}: {
  refreshKey?: number;
  pollForNewExport?: boolean;
  onPollComplete?: () => void;
}) {
  const [exports, setExports] = useState<NotificationItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [downloadingId, setDownloadingId] = useState<number | null>(null);
  const [previewNotification, setPreviewNotification] = useState<NotificationItem | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [refreshToken, setRefreshToken] = useState(0);
  const baselineExportCount = useRef(0);
  const exportsCountRef = useRef(0);
  exportsCountRef.current = exports.length;

  const loadExports = useCallback(async () => {
    try {
      const response = await apiClient.get<NotificationsResponse>("/notifications", {
        params: { per_page: 20 },
      });
      const reportExports = asNotificationList(response.data).filter(isReportExportNotification);
      setExports(reportExports);
      setError(null);

      return reportExports;
    } catch {
      setExports([]);
      setError("Unable to load recent exports.");

      return [];
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    let cancelled = false;

    async function refresh() {
      const items = await loadExports();
      if (cancelled) {
        return items;
      }

      return items;
    }

    void refresh();

    return () => {
      cancelled = true;
    };
  }, [loadExports, refreshKey, refreshToken]);

  useEffect(() => {
    if (!pollForNewExport) {
      return;
    }

    baselineExportCount.current = exportsCountRef.current;
    let attempts = 0;
    const maxAttempts = 30;

    const timer = window.setInterval(() => {
      attempts += 1;
      setRefreshToken((value) => value + 1);

      if (attempts >= maxAttempts) {
        window.clearInterval(timer);
        onPollComplete?.();
      }
    }, 2000);

    return () => {
      window.clearInterval(timer);
    };
  }, [onPollComplete, pollForNewExport]);

  useEffect(() => {
    if (
      pollForNewExport &&
      exports.length > baselineExportCount.current &&
      baselineExportCount.current >= 0
    ) {
      onPollComplete?.();
    }
  }, [exports.length, onPollComplete, pollForNewExport]);

  async function handleDownload(notification: NotificationItem) {
    setDownloadingId(notification.id);
    setError(null);

    try {
      await downloadReportExport(notification);
    } catch {
      setError("Download failed. The export may still be generating — try again shortly.");
    } finally {
      setDownloadingId(null);
    }
  }

  function handleOpenPreview(notification: NotificationItem) {
    setPreviewNotification(notification);
  }

  return (
    <>
      <Card className="p-5">
        <div className="flex items-center justify-between gap-3">
          <div>
            <h2 className="section-title">Recent exports</h2>
            <p className="mt-1 text-sm text-muted">
              {pollForNewExport
                ? "Generating export… this list refreshes automatically."
                : "Click an export to preview it, then download when ready."}
            </p>
          </div>
          {pollForNewExport ? <Spinner /> : null}
        </div>

        {error ? (
          <p className="mt-3 rounded-lg border border-amber-300/60 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
            {error}
          </p>
        ) : null}

        <div className="mt-4 space-y-3">
          {loading ? (
            <p className="text-sm text-muted">Loading exports…</p>
          ) : exports.length === 0 ? (
            <p className="text-sm text-muted">
              No exports yet. Queue an Excel or PDF export above.
            </p>
          ) : (
            exports.map((notification) => {
              const format =
                typeof notification.data?.format === "string"
                  ? notification.data.format.toUpperCase()
                  : "FILE";

              return (
                <div
                  key={notification.id}
                  role="button"
                  tabIndex={0}
                  className="flex w-full cursor-pointer flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-4 text-left transition hover:border-sky-300 hover:bg-sky-50/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 dark:border-slate-800 dark:hover:border-sky-500/40 dark:hover:bg-sky-500/5"
                  onClick={() => handleOpenPreview(notification)}
                  onKeyDown={(event) => {
                    if (event.key === "Enter" || event.key === " ") {
                      event.preventDefault();
                      handleOpenPreview(notification);
                    }
                  }}
                >
                  <div>
                    <p className="font-medium text-foreground">
                      {notification.title}
                    </p>
                    <p className="mt-1 text-sm text-muted">
                      {format} · {formatDate(notification.sent_at ?? notification.read_at)}
                    </p>
                  </div>
                  <div
                    className="flex flex-wrap gap-2"
                    onClick={(event) => event.stopPropagation()}
                    onKeyDown={(event) => event.stopPropagation()}
                  >
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => handleOpenPreview(notification)}
                    >
                      <Eye className="mr-2 h-4 w-4" />
                      Preview
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      disabled={downloadingId === notification.id}
                      onClick={() => void handleDownload(notification)}
                    >
                      <FileDown className="mr-2 h-4 w-4" />
                      {downloadingId === notification.id ? "Downloading…" : "Download"}
                    </Button>
                  </div>
                </div>
              );
            })
          )}
        </div>
      </Card>

      {previewNotification ? (
        <ReportExportPreview
          notification={previewNotification}
          onClose={() => setPreviewNotification(null)}
        />
      ) : null}
    </>
  );
}
