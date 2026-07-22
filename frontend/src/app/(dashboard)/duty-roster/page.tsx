"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { DutyRosterEditor } from "@/components/duty-roster/duty-roster-editor";
import { useAuth } from "@/lib/auth/auth-context";
import { useSchool } from "@/lib/tenant/school-context";
import { canManageDutyRoster, isDeanRole, roleHomePath } from "@/lib/utils";

export default function DutyRosterPage() {
  const router = useRouter();
  const { user, loading } = useAuth();
  const { currentSchool, revision } = useSchool();
  const allowed = canManageDutyRoster(user?.role?.slug);
  const isDean = isDeanRole(user?.role?.slug);

  useEffect(() => {
    if (loading || !user) {
      return;
    }

    if (!allowed) {
      router.replace(roleHomePath(user.role?.slug));
    }
  }, [allowed, loading, router, user]);

  if (!allowed) {
    return null;
  }

  return (
    <div className="space-y-6">
      <section>
        <p className="page-eyebrow">{isDean ? "Dean of students" : "Roll call management"}</p>
        <h1 className="page-title">Weekly duty roster</h1>
        <p className="mt-2 max-w-3xl text-sm text-(--text-muted)">
          Create weeks, assign teachers, save drafts, preview, and publish
          {currentSchool ? ` for ${currentSchool.name}` : ". Select a school in the header to get started"}.
          After publishing, review history and exports under Reports → Duty Roster Reports.
        </p>
      </section>

      <DutyRosterEditor schoolName={currentSchool?.name} revision={revision} />
    </div>
  );
}
