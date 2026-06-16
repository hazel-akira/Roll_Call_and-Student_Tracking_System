"use client";

import { AuthProvider } from "@/lib/auth/auth-context";
import { ThemeProvider } from "@/components/providers/theme-provider";
import { SchoolProvider } from "@/lib/tenant/school-context";

export function AppProviders({ children }: { children: React.ReactNode }) {
  return (
    <ThemeProvider>
      <AuthProvider>
        <SchoolProvider>{children}</SchoolProvider>
      </AuthProvider>
    </ThemeProvider>
  );
}
