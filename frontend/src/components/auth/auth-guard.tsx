"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { Spinner } from "@/components/ui/spinner";
import { useAuth } from "@/lib/auth/auth-context";

export function AuthGuard({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const { user, loading } = useAuth();

  useEffect(() => {
    if (!loading && !user) {
      router.replace("/login");
    }
  }, [loading, router, user]);

  if (loading || !user) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-(--background)">
        <div className="flex items-center gap-3 rounded-2xl border bg-(--surface-solid) px-6 py-4 text-foreground shadow-sm">
          <Spinner />
          Preparing your secure workspace...
        </div>
      </div>
    );
  }

  return <>{children}</>;
}
