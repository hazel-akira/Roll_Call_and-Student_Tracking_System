import { describe, expect, it } from "vitest";
import { buildStreamCatalog, streamCatalogKey } from "@/lib/attendance/stream-catalog";
import type { FormStreamsPayload } from "@/lib/attendance/form-streams";
import type { SchoolClass } from "@/types";

describe("buildStreamCatalog", () => {
  const classes: SchoolClass[] = [
    {
      id: 1,
      name: "Form 3 A",
      code: "F3A",
      grade_level: "Form 3",
      section: "East",
      academic_year: "2026",
    },
  ];

  const payload: FormStreamsPayload = {
    grade_levels: ["Form 3", "Form 4"],
    streams: [
      {
        grade_level: "Form 3",
        stream: "East",
        room_id: "room-east",
        label: "East",
      },
      {
        grade_level: "Form 3",
        stream: "West",
        room_id: "room-west",
        label: "West",
      },
      {
        grade_level: "Form 4",
        stream: "North",
        room_id: null,
        label: "North",
      },
    ],
  };

  it("builds unique entries per grade and stream", () => {
    const catalog = buildStreamCatalog(classes, payload);

    expect(catalog).toHaveLength(3);
    expect(catalog.map((item) => item.key)).toEqual([
      streamCatalogKey("Form 3", "East", "room-east"),
      streamCatalogKey("Form 3", "West", "room-west"),
      streamCatalogKey("Form 4", "North", null),
    ]);
    expect(catalog[0]?.classId).toBe(1);
  });
});
