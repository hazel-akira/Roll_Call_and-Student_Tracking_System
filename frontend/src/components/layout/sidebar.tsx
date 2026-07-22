"use client";

import { useEffect, useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  BarChart3,
  BookOpen,
  ChevronDown,
  ClipboardCheck,
  ClipboardList,
  LayoutDashboard,
  Layers,
  Users,
  X,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { useSchool } from "@/lib/tenant/school-context";
import type { AppUser } from "@/types";

type NavLeaf = {
  href: string;
  label: string;
  icon: typeof LayoutDashboard;
  roles: readonly string[];
};

type NavGroup = {
  label: string;
  icon: typeof BookOpen;
  roles: readonly string[];
  children: readonly NavLeaf[];
};

type NavItem = NavLeaf | NavGroup;

function isNavGroup(item: NavItem): item is NavGroup {
  return "children" in item;
}

const navItems: readonly NavItem[] = [
  { href: "/teacher", label: "Teacher Dashboard", icon: LayoutDashboard, roles: ["teacher", "admin", "ict_staff"] },
  { href: "/admin", label: "Admin Dashboard", icon: BarChart3, roles: ["admin", "ict_staff"] },
  { href: "/attendance", label: "Attendance", icon: ClipboardCheck, roles: ["teacher", "admin", "ict_staff"] },
  { href: "/class-streams", label: "Class streams", icon: Layers, roles: ["teacher", "admin", "ict_staff"] },
  { href: "/students", label: "Students", icon: Users, roles: ["teacher", "admin", "ict_staff"] },
  {
    href: "/duty-roster",
    label: "Duty roster",
    icon: ClipboardList,
    roles: ["admin", "ict_staff", "dean_of_students", "deputy_dean"],
  },
  {
    label: "Reports",
    icon: BookOpen,
    roles: ["admin", "ict_staff", "dean_of_students", "deputy_dean"],
    children: [
      {
        href: "/reports/attendance",
        label: "Attendance Reports",
        icon: ClipboardCheck,
        roles: ["admin", "ict_staff", "dean_of_students", "deputy_dean"],
      },
      {
        href: "/reports/duty-roster",
        label: "Duty Roster Reports",
        icon: ClipboardList,
        roles: ["admin", "ict_staff", "dean_of_students", "deputy_dean"],
      },
    ],
  },
];

export function Sidebar({
  user,
  mobileOpen,
  onCloseMobile,
}: {
  user: AppUser;
  mobileOpen: boolean;
  onCloseMobile: () => void;
}) {
  const pathname = usePathname();
  const { currentSchool } = useSchool();
  const [logoMissing, setLogoMissing] = useState(false);
  const role = user.role?.slug ?? "teacher";
  const items = navItems.filter((item) => item.roles.some((itemRole) => itemRole === role));
  const reportsOpenByDefault = pathname.startsWith("/reports");
  const [reportsOpen, setReportsOpen] = useState(reportsOpenByDefault);

  useEffect(() => {
    if (pathname.startsWith("/reports")) {
      setReportsOpen(true);
    }
  }, [pathname]);

  return (
    <>
      <button
        type="button"
        className={cn(
          "fixed inset-0 z-40 bg-(--overlay) transition-opacity lg:hidden",
          mobileOpen ? "opacity-100" : "pointer-events-none opacity-0",
        )}
        onClick={onCloseMobile}
        aria-label="Close sidebar navigation"
      />
      <aside
        className={cn(
          "fixed inset-y-0 left-0 z-50 flex w-[18rem] max-w-[85vw] flex-col  bg-(--surface-solid) px-4 py-6 shadow-xl transition-transform duration-300 lg:static lg:z-auto lg:h-full lg:w-full lg:max-w-72 lg:translate-x-0 lg:shadow-none",
          mobileOpen ? "translate-x-0" : "-translate-x-full",
        )}
      >
        <div className="mb-4 flex items-center justify-end lg:hidden">
          <button
            type="button"
            onClick={onCloseMobile}
            className="rounded-lg p-1.5 text-(--text-muted) hover:bg-(--surface-muted)"
            aria-label="Close sidebar"
          >
            <X size={18} />
          </button>
        </div>
        <div className="flex items-center gap-4">
          <div className="flex shrink-0 items-center justify-center overflow-hidden bg-(--color-primary-deep)">
            {!logoMissing ? (
              <Image
                src="/assets/dark_pgos_logo.png"
                alt="PGOS logo"
                width={100}
                height={200}
                className="h-full w-full object-contain p-2"
                onError={() => setLogoMissing(true)}
                priority
              />
            ) : null}
          </div>
        </div>
        <div className="mb-8 px-3">
          <h1 className="mt-2 text-xl font-semibold text-foreground">
            Student Tracking
          </h1>
        </div>
        <nav className="space-y-1">
          {items.map((item) => {
            if (isNavGroup(item)) {
              const Icon = item.icon;
              const childActive = item.children.some(
                (child) => pathname === child.href || pathname.startsWith(`${child.href}/`),
              );

              return (
                <div key={item.label} className="space-y-1">
                  <button
                    type="button"
                    onClick={() => setReportsOpen((open) => !open)}
                    className={cn(
                      "flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition",
                      childActive
                        ? "bg-(--surface-muted) text-[#df8811]"
                        : "text-(--text-muted) hover:bg-(--surface-muted) hover:text-foreground",
                    )}
                  >
                    <Icon size={18} />
                    <span className="flex-1 text-left">{item.label}</span>
                    <ChevronDown
                      size={16}
                      className={cn("transition", reportsOpen ? "rotate-180" : "")}
                    />
                  </button>
                  {reportsOpen
                    ? item.children
                        .filter((child) => child.roles.some((itemRole) => itemRole === role))
                        .map((child) => {
                          const ChildIcon = child.icon;
                          const active =
                            pathname === child.href || pathname.startsWith(`${child.href}/`);

                          return (
                            <Link
                              key={child.href}
                              href={child.href}
                              onClick={onCloseMobile}
                              className={cn(
                                "ml-3 flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition",
                                active
                                  ? "bg-(--surface-muted) text-[#df8811]"
                                  : "text-(--text-muted) hover:bg-(--surface-muted) hover:text-foreground",
                              )}
                            >
                              <ChildIcon size={16} />
                              {child.label}
                            </Link>
                          );
                        })
                    : null}
                </div>
              );
            }

            const Icon = item.icon;
            const active = pathname === item.href || pathname.startsWith(`${item.href}/`);

            return (
              <Link
                key={item.href}
                href={item.href}
                onClick={onCloseMobile}
                className={cn(
                  "flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition",
                  active
                    ? "bg-(--surface-muted) text-[#df8811]"
                    : "text-(--text-muted) hover:bg-(--surface-muted) hover:text-foreground",
                )}
              >
                <Icon size={18} />
                {item.label}
              </Link>
            );
          })}
        </nav>
        <div className="mt-auto rounded-2xl border border-[rgba(148,163,184,0.18)] bg-(--surface-muted) p-4">
          <p className="text-sm font-semibold text-foreground">{user.name}</p>
          <p className="mt-1 text-xs text-(--text-muted)">{user.role?.name}</p>
          {currentSchool ? (
            <p className="mt-2 text-xs font-medium text-(--color-accent-dark)">{currentSchool.name}</p>
          ) : null}
        </div>
      </aside>
    </>
  );
}
