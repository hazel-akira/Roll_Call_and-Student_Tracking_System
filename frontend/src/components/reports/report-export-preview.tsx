"use client";

import { useCallback, useEffect, useState } from "react";
import { FileSpreadsheet, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import {
  canPreviewReportExport,
  downloadReportExportFile,
  fetchReportExport,
  type ReportExportFile,
} from "@/lib/reports/download-export";
import { formatDate } from "@/lib/utils";
import type { NotificationItem } from "@/types";

export function ReportExportPreview({
  notification,
  onClose,
}: {
  notification: NotificationItem;
  onClose: () => void;
}) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [exportFile, setExportFile] = useState<ReportExportFile | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [downloading, setDownloading] = useState(false);

  const format =
    typeof notification.data?.format === "string"
      ? notification.data.format.toUpperCase()
      : "FILE";

  const handleClose = useCallback(() => {
    onClose();
  }, [onClose]);

  useEffect(() => {
    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") {
        handleClose();
      }
    }

    window.addEventListener("keydown", onKeyDown);

    return () => {
      window.removeEventListener("keydown", onKeyDown);
    };
  }, [handleClose]);

  useEffect(() => {
    let cancelled = false;

    async function loadPreview() {
      setLoading(true);
      setError(null);
      setExportFile(null);
      setPreviewUrl((current) => {
        if (current) {
          URL.revokeObjectURL(current);
        }

        return null;
      });

      try {
        const file = await fetchReportExport(notification);

        if (cancelled) {
          return;
        }

        setExportFile(file);

        if (canPreviewReportExport(file.format)) {
          setPreviewUrl(URL.createObjectURL(file.blob));
        }
      } catch {
        if (!cancelled) {
          setError("Unable to load this export. It may still be generating — try again shortly.");
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    void loadPreview();

    return () => {
      cancelled = true;
    };
  }, [notification]);

  useEffect(() => {
    return () => {
      if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
      }
    };
  }, [previewUrl]);

  async function handleDownload() {
    if (!exportFile) {
      return;
    }

    setDownloading(true);

    try {
      downloadReportExportFile(exportFile);
    } finally {
      setDownloading(false);
    }
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-(--overlay) p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="export-preview-title"
      onClick={handleClose}
    >
      <div
        className="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border bg-(--surface-solid) text-foreground shadow-2xl"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="flex items-start justify-between gap-4 border-b px-5 py-4">
          <div>
            <h2 id="export-preview-title" className="text-lg font-semibold text-foreground">
              {notification.title}
            </h2>
            <p className="mt-1 text-sm text-muted">
              {format} · {formatDate(notification.sent_at ?? notification.read_at)}
            </p>
          </div>
          <button
            type="button"
            aria-label="Close preview"
            className="rounded-lg p-2 text-muted hover:bg-(--surface-muted)"
            onClick={handleClose}
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="min-h-[320px] flex-1 overflow-hidden bg-(--surface-muted)">
          {loading ? (
            <div className="flex h-full min-h-[320px] items-center justify-center gap-3 text-sm text-muted">
              <Spinner />
              Loading preview…
            </div>
          ) : error ? (
            <div className="flex h-full min-h-[320px] items-center justify-center px-6">
              <p className="rounded-lg border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
                {error}
              </p>
            </div>
          ) : exportFile && canPreviewReportExport(exportFile.format) && previewUrl ? (
            <iframe
              title={`Preview ${exportFile.filename}`}
              src={previewUrl}
              className="h-[70vh] w-full bg-(--surface-solid)"
            />
          ) : exportFile ? (
            <div className="flex h-full min-h-[320px] flex-col items-center justify-center gap-4 px-6 text-center">
              <div className="rounded-2xl bg-emerald-50 p-4 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                <FileSpreadsheet className="h-10 w-10" />
              </div>
              <div>
                <p className="font-medium text-foreground">{exportFile.filename}</p>
                <p className="mt-2 max-w-md text-sm text-muted">
                  Excel exports cannot be previewed in the browser. Download the file to open it in
                  Excel or another spreadsheet app.
                </p>
              </div>
            </div>
          ) : null}
        </div>

        <div className="flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
          <Button variant="outline" onClick={handleClose}>
            Close
          </Button>
          <Button disabled={!exportFile || downloading} onClick={() => void handleDownload()}>
            {downloading ? "Downloading…" : "Download"}
          </Button>
        </div>
      </div>
    </div>
  );
}
