"use client";

import Image from "next/image";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { useAuth } from "@/lib/auth/auth-context";
import { useSyncExternalStore } from "react";

export function LoginForm() {
  const { login, loginWithGoogle, googleSignInEnabled, loading, error } = useAuth();
  const safeLoading = useSyncExternalStore(
    () => () => {},
    () => loading,
    () => false,
  );

  return (
    <Card className="w-full max-w-md p-8">
      <Image
        src="/assets/pgos_logo.png"
        alt="PGoS Roll Call System"
        width={200}
        height={200}
        className="mx-auto py-6"
      />
      <p className="page-eyebrow py-6 text-center text-2xl">Sign in</p>
      <h2 className="mt-3 text-3xl font-semibold text-foreground">PGoS Roll Call System</h2>
      <p className="mt-3 text-sm text-muted">
        Continue with your Microsoft institutional account to access dashboards, attendance
        workflows, and reports.
      </p>
      {error ? (
        <p className="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
          {error}
        </p>
      ) : null}
      <Button
        className="mt-6 w-full"
        size="lg"
        onClick={() => void login()}
        disabled={safeLoading}
      >
        {safeLoading ? "Preparing sign-in..." : "Continue with Microsoft"}
      </Button>
      {googleSignInEnabled ? (
        <Button
          className="mt-3 w-full"
          size="lg"
          variant="outline"
          onClick={() => void loginWithGoogle()}
          disabled={safeLoading}
        >
          {safeLoading ? "Preparing sign-in..." : "Continue with Google"}
        </Button>
      ) : null}
    </Card>
  );
}
