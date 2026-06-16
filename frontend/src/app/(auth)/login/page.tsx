"use client";

import { useSyncExternalStore } from "react";
import Image from "next/image";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { useAuth } from "@/lib/auth/auth-context";

export default function LoginPage() {
  const { login, loading, error } = useAuth();
  const safeLoading = useSyncExternalStore(
    () => () => {},
    () => loading,
    () => false,
  );

  return (
    <div className=" min-h-screen ">
     
      <div className="flex items-center justify-center mt-50 pb-6">
        <Card className="w-full max-w-md p-8 bg-#124397dd8h dark:bg-#124397dd8h rounded-2xl">
        <Image src="/assets/pgos_logo.png" alt="PGoS Roll Call System" width={200} height={200} className="py-6 mx-auto"/>
          <p className="text-center font-semibold uppercase tracking-[0.2em] text-#df8811  text-2xl center py-6 mx-auto">
            Sign in
          </p>
        
          <h2 className="mt-3 text-3xl font-semibold text-slate-900 dark:text-white">
             PGoS Roll Call System
          </h2>
          <p className="mt-3 text-sm text-slate-500 dark:text-slate-400">
            Continue with your Microsoft institutional account to access dashboards, attendance workflows, and reports.
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
        </Card>
      </div>
    </div>
  );
}
