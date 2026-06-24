"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { Spinner } from "@/components/ui/spinner";
import { useAuth } from "@/lib/auth/auth-context";
import { roleHomePath } from "@/lib/utils";

export default function HomePage() {
  const router = useRouter();
  const { user, loading } = useAuth();

  useEffect(() => {
    if (loading) return;
    router.replace(user ? roleHomePath(user.role?.slug) : "/login");
  }, [loading, router, user]);

  return (
    <div className="flex min-h-screen items-center justify-center">
      <div className="flex items-center gap-3 rounded-2xl border bg-(--surface-solid) px-6 py-4 text-foreground shadow-sm">
        <Spinner />
        Routing to your workspace...
      </div>
    </div>
  );
}
