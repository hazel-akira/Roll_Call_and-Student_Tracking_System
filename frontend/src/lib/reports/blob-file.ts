export type BlobFile = {
  blob: Blob;
  filename: string;
  mimeType?: string;
};

function createObjectUrl(file: BlobFile): string {
  const mimeType = file.mimeType ?? file.blob.type;

  if (mimeType && file.blob.type !== mimeType) {
    return URL.createObjectURL(new Blob([file.blob], { type: mimeType }));
  }

  return URL.createObjectURL(file.blob);
}

export function isMobilePdfPreviewUnsupported(userAgent: string): boolean {
  return /iPad|iPhone|iPod|Android/i.test(userAgent);
}

export function supportsInlinePdfPreview(): boolean {
  if (typeof window === "undefined") {
    return true;
  }

  return !isMobilePdfPreviewUnsupported(navigator.userAgent);
}

export function openBlobFile(file: BlobFile): void {
  const url = createObjectUrl(file);
  const opened = window.open(url, "_blank", "noopener,noreferrer");

  if (!opened) {
    const link = document.createElement("a");
    link.href = url;
    link.target = "_blank";
    link.rel = "noopener noreferrer";
    document.body.appendChild(link);
    link.click();
    link.remove();
  }

  window.setTimeout(() => URL.revokeObjectURL(url), 60_000);
}

export function downloadBlobFile(file: BlobFile): void {
  const url = createObjectUrl(file);
  const link = document.createElement("a");
  link.href = url;
  link.download = file.filename;

  if (/iPad|iPhone|iPod/i.test(navigator.userAgent)) {
    link.target = "_blank";
    link.rel = "noopener noreferrer";
    link.removeAttribute("download");
  }

  document.body.appendChild(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 60_000);
}
