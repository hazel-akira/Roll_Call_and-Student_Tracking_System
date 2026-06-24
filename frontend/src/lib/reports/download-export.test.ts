import { describe, expect, it } from "vitest";
import {
  canPreviewReportExport,
  isReportExportNotification,
} from "@/lib/reports/download-export";
import type { NotificationItem } from "@/types";

describe("download-export helpers", () => {
  it("detects report export notifications", () => {
    const notification = {
      id: 1,
      type: "report",
      data: { path: "exports/test.pdf", format: "pdf" },
    } as NotificationItem;

    expect(isReportExportNotification(notification)).toBe(true);
  });

  it("only allows preview for pdf exports", () => {
    expect(canPreviewReportExport("pdf")).toBe(true);
    expect(canPreviewReportExport("xlsx")).toBe(false);
  });
});
