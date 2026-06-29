"use client";

import { useSyncExternalStore } from "react";
import { ExternalLink, FileText } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  downloadBlobFile,
  openBlobFile,
  supportsInlinePdfPreview,
  type BlobFile,
} from "@/lib/reports/blob-file";

export function PdfDocumentPreview({
  previewUrl,
  file,
  title,
  className,
  onDownload,
  downloading = false,
}: {
  previewUrl: string;
  file: BlobFile;
  title: string;
  className?: string;
  onDownload?: () => void | Promise<void>;
  downloading?: boolean;
}) {
  const inlinePreview = useSyncExternalStore(
    () => () => {},
    () => supportsInlinePdfPreview(),
    () => true,
  );

  if (inlinePreview) {
    return (
      <iframe
        title={title}
        src={previewUrl}
        className={className ?? "h-[70vh] w-full rounded-xl border bg-(--surface-solid)"}
      />
    );
  }

  return (
    <div className="flex flex-col items-center justify-center gap-4 rounded-xl border bg-(--surface-muted) px-6 py-10 text-center">
      <div className="rounded-2xl bg-(--surface-solid) p-4 text-foreground">
        <FileText className="h-10 w-10" />
      </div>
      <div className="max-w-md space-y-2">
        <p className="font-medium text-foreground">{file.filename}</p>
        <p className="text-sm text-muted">
          Mobile browsers cannot show PDFs inline here. Open the report in your phone&apos;s PDF
          viewer instead.
        </p>
      </div>
      <div className="flex flex-wrap justify-center gap-3">
        <Button type="button" onClick={() => openBlobFile(file)}>
          <ExternalLink size={16} />
          Open PDF
        </Button>
        {onDownload ? (
          <Button type="button" variant="outline" disabled={downloading} onClick={() => void onDownload()}>
            {downloading ? "Downloading…" : "Download PDF"}
          </Button>
        ) : (
          <Button
            type="button"
            variant="outline"
            onClick={() => downloadBlobFile(file)}
          >
            Download PDF
          </Button>
        )}
      </div>
    </div>
  );
}
