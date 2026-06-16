import { render, screen, waitFor } from "@testing-library/react";
import AdminDashboardPage from "@/app/(dashboard)/admin/page";

const replaceMock = vi.fn();
const getMock = vi.fn();
const useAuthMock = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace: replaceMock }),
}));

vi.mock("@/lib/api/client", () => ({
  apiClient: {
    get: (...args: unknown[]) => getMock(...args),
  },
}));

vi.mock("@/lib/auth/auth-context", () => ({
  useAuth: () => useAuthMock(),
}));

vi.mock("@/lib/tenant/school-context", () => ({
  useSchool: () => ({
    currentSchool: null,
    viewingAllSchools: true,
    revision: 0,
  }),
}));

vi.mock("@/components/admin/teacher-assignments", () => ({
  TeacherAssignments: () => null,
}));

const adminDashboardPayload = {
  stats: {
    students: 50,
    teachers: 5,
    classes: 3,
    today_sessions: 2,
    attendance_rate_today: 92,
    unresolved_absences: 1,
  },
  daily_attendance_trends: [],
  recent_audit_logs: [],
  recent_sync_failures: [],
};

describe("AdminDashboardPage authorization", () => {
  beforeEach(() => {
    replaceMock.mockReset();
    getMock.mockReset();
    useAuthMock.mockReset();
    vi.useRealTimers();
  });

  it("redirects teacher users away from admin route", async () => {
    useAuthMock.mockReturnValue({
      user: { role: { slug: "teacher", name: "Teacher" } },
      loading: false,
    });

    render(<AdminDashboardPage />);

    await waitFor(() => {
      expect(replaceMock).toHaveBeenCalledWith("/teacher");
    });
    expect(getMock).not.toHaveBeenCalled();
  });

  it("loads admin summary for admin users", async () => {
    useAuthMock.mockReturnValue({
      user: { role: { slug: "admin", name: "Administrator" } },
      loading: false,
    });
    getMock.mockResolvedValue({ data: adminDashboardPayload });

    render(<AdminDashboardPage />);

    await waitFor(() => expect(getMock).toHaveBeenCalledWith("/dashboard/admin"));
    expect(screen.getByText("Operations and attendance analytics")).toBeInTheDocument();
    expect(screen.getByText("50")).toBeInTheDocument();
    expect(screen.getByText("Today's sessions")).toBeInTheDocument();
  });

  it("allows ict_staff users without redirecting", async () => {
    useAuthMock.mockReturnValue({
      user: { role: { slug: "ict_staff", name: "ICT Staff" } },
      loading: false,
    });
    getMock.mockResolvedValue({ data: adminDashboardPayload });

    render(<AdminDashboardPage />);

    await waitFor(() => expect(getMock).toHaveBeenCalledWith("/dashboard/admin"));
    expect(replaceMock).not.toHaveBeenCalled();
  });

  it("shows an error message when dashboard load fails", async () => {
    useAuthMock.mockReturnValue({
      user: { role: { slug: "admin", name: "Administrator" } },
      loading: false,
    });
    getMock.mockRejectedValue(new Error("network"));

    render(<AdminDashboardPage />);

    await waitFor(() => {
      expect(screen.getByText("Unable to load admin dashboard data.")).toBeInTheDocument();
    });
  });
});
