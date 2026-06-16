import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { AttendanceSessionForm } from "@/components/attendance/attendance-session-form";
import type { SchoolClass } from "@/types";

const getMock = vi.fn();

vi.mock("@/lib/api/client", () => ({
  apiClient: {
    get: (...args: unknown[]) => getMock(...args),
  },
}));

describe("AttendanceSessionForm", () => {
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

  beforeEach(() => {
    getMock.mockReset();
    getMock.mockResolvedValue({
      data: {
        data: {
          grade_levels: ["Form 3"],
          streams: [
            {
              grade_level: "Form 3",
              stream: "East",
              room_id: "room-east",
              label: "East",
            },
          ],
        },
      },
    });
  });

  it("submits mapped class_id from grade and stream", async () => {
    const user = userEvent.setup();
    const onCreate = vi.fn().mockResolvedValue(undefined);

    render(
      <AttendanceSessionForm
        schoolId="1"
        classes={classes}
        studentCount={5}
        onCreate={onCreate}
      />,
    );

    await waitFor(() => {
      expect(screen.getByLabelText("Select form or grade")).not.toBeDisabled();
    });

    await user.selectOptions(screen.getByLabelText("Select form or grade"), "Form 3");
    await user.selectOptions(screen.getByLabelText("Select stream"), "room-east");
    await user.clear(screen.getByLabelText("Roll call session title"));
    await user.type(screen.getByLabelText("Roll call session title"), "Morning Roll Call");
    await user.click(screen.getByRole("button", { name: "Create session" }));

    await waitFor(() => expect(onCreate).toHaveBeenCalledTimes(1));
    expect(onCreate).toHaveBeenCalledWith(
      expect.objectContaining({
        class_id: 1,
        title: "Morning Roll Call",
      }),
    );
  });
});
