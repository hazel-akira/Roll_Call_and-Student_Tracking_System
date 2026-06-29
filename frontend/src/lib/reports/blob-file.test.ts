import { describe, expect, it } from "vitest";
import { isMobilePdfPreviewUnsupported } from "@/lib/reports/blob-file";

describe("blob-file helpers", () => {
  it("detects mobile user agents that cannot preview pdfs inline", () => {
    expect(isMobilePdfPreviewUnsupported("Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)")).toBe(
      true,
    );
    expect(isMobilePdfPreviewUnsupported("Mozilla/5.0 (Linux; Android 14; Pixel 8)")).toBe(true);
    expect(
      isMobilePdfPreviewUnsupported(
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36",
      ),
    ).toBe(false);
  });
});
