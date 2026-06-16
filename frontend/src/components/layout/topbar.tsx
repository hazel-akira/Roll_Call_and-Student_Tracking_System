"use client";

import { LogOut, Menu } from "lucide-react";
import { SchoolSelector } from "@/components/layout/school-selector";
import { ThemeToggle } from "@/components/layout/theme-toggle";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/lib/auth/auth-context";
import { useSchool } from "@/lib/tenant/school-context";

export function Topbar({ onOpenMobileSidebar }: { onOpenMobileSidebar: () => void }) {
  const { user, logout } = useAuth();
  const { currentSchool, viewingAllSchools } = useSchool();

  return (
    <header className="flex flex-col gap-4  bg-(--surface) px-6 py-4 shadow-[0_10px_28px_rgba(18,59,105,0.08)] backdrop-blur-md lg:flex-row lg:items-center lg:justify-between">
      <div className="flex items-center gap-4">
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="lg:hidden"
          onClick={onOpenMobileSidebar}
          aria-label="Open sidebar navigation"
        >
          <Menu size={18} />
        </Button>
        <div className="m-4">
          <p className="text-xs font-bold uppercase tracking-[0.2em] text-(--color-accent-dark)">
            Roll Call System
          </p>
          <div>
            <h2 className="mt-1 text-lg font-semibold text-foreground">
              Welcome back, {user?.name?.split(" ")[0] ?? "User"}
            </h2>
            <p className="mt-1 text-sm text-(--text-muted)">
              {viewingAllSchools
                ? "Viewing all schools"
                : currentSchool
                  ? `Working in ${currentSchool.name}`
                  : "Attendance and student tracking dashboard"}
            </p>
          </div>
        </div>
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <SchoolSelector />
        <ThemeToggle />
        <Button variant="outline" size="sm" onClick={() => void logout()}>
          <LogOut size={16} />
          Sign out
        </Button>
      </div>
    </header>
  );
}
