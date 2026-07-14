export type Role = {
  id: number;
  name: string;
  slug: "admin" | "teacher" | "ict_staff" | string;
};

export type UserIdentity = {
  provider: string;
  tenant_id: string | null;
  provider_email: string | null;
  last_login_at: string | null;
};

export type School = {
  id: number;
  name: string;
  code: string;
  level?: string | null;
  is_junior?: boolean;
  active?: boolean;
};

export type AppUser = {
  id: number;
  name: string;
  email: string;
  job_title?: string | null;
  department?: string | null;
  status: string;
  last_login_at?: string | null;
  role: Role | null;
  identities?: UserIdentity[];
  schools?: School[];
};

export type TokenSet = {
  access_token: string;
  refresh_token: string;
  token_type: string;
  expires_in: number;
  refresh_expires_in: number;
};

export type AuthSession = {
  user: AppUser;
  tokens: TokenSet;
};

export type SchoolClass = {
  id: number;
  school_id?: number | null;
  name: string;
  code: string;
  grade_level?: string | null;
  section?: string | null;
  academic_year?: string;
  students_count?: number;
  school?: School | null;
};

export type TeacherClassAssignment = {
  id: number;
  class_id: number;
  subject_id: number;
  class?: SchoolClass | null;
};

export type Subject = {
  id: number;
  name: string;
  code: string;
  description?: string | null;
};

export type AttendanceStudent = {
  id: number;
  admission_number: string;
  full_name: string;
};

export type AttendanceRecord = {
  id?: number;
  status: "present" | "missing" | "sick" | "on_leave" | "absent" | "late" | "excused";
  remark?: string | null;
  marked_at?: string | null;
  student: AttendanceStudent | null;
};

export type AttendanceSession = {
  id: number;
  title: string;
  notes?: string | null;
  session_date: string;
  started_at: string;
  closed_at?: string | null;
  status: "draft" | "open" | "closed";
  dynamics_sync_status: string;
  class: SchoolClass | null;
  subject: Subject | null;
  teacher?: { id: number; name: string; email: string } | null;
  records?: AttendanceRecord[];
};

export type SchoolStaffMember = {
  id: number;
  name: string;
  email: string;
  job_title?: string | null;
};

export type DutyRosterEntry = {
  id?: number;
  category: string;
  category_label?: string;
  location?: string | null;
  time_slot?: string | null;
  sort_order?: number;
  staff_ids?: number[];
  staff: SchoolStaffMember[];
};

export type WeeklyDutyRoster = {
  id: number;
  school_id: number;
  week_start: string;
  week_end?: string | null;
  week_label: string;
  entries: DutyRosterEntry[];
};

export type DutyRosterSummary = {
  id: number;
  school_id: number;
  week_start: string;
  week_end?: string | null;
  week_label: string;
  entries_count: number;
};

export type DutyRosterMeta = {
  categories: Record<string, string>;
  standard_template: Array<{
    category: string;
    location: string | null;
    time_slot: string | null;
    sort_order: number;
  }>;
};

export type NotificationItem = {
  id: number;
  title: string;
  body: string;
  channel: string;
  type: string;
  read_at?: string | null;
  sent_at?: string | null;
  data?: Record<string, unknown> | null;
};

export type Student = {
  id: number;
  admission_number: string;
  first_name: string;
  last_name: string;
  middle_name?: string | null;
  full_name: string;
  email?: string | null;
  status: string;
  class: SchoolClass | null;
};
