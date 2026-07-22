"use client";

import Image from "next/image";
import { useEffect, useState } from "react";
import { isAxiosError } from "axios";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { apiClient } from "@/lib/api/client";
import { useAuth } from "@/lib/auth/auth-context";
import { roleHomePath } from "@/lib/utils";
import type { AppUser, School, TokenSet } from "@/types";

type OnboardingSchoolsResponse = {
  schools: School[];
};

type AssignSchoolsResponse = {
  user: AppUser;
  tokens: TokenSet;
  current_school_id?: string | number | null;
};

function getErrorMessage(error: unknown): string {
  if (isAxiosError(error)) {
    const message = (error.response?.data as { message?: string } | undefined)?.message;
    if (message) {
      return message;
    }
  }

  return error instanceof Error ? error.message : "Unable to save your school selection.";
}

export function SchoolOnboardingForm() {
  const router = useRouter();
  const { user, completeSchoolOnboarding } = useAuth();
  const [schools, setSchools] = useState<School[]>([]);
  const [selectedSchoolIds, setSelectedSchoolIds] = useState<number[]>([]);
  const [loadingSchools, setLoadingSchools] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function loadSchools() {
      setLoadingSchools(true);
      setError(null);

      try {
        const response = await apiClient.get<OnboardingSchoolsResponse>("/auth/onboarding/schools");
        if (!cancelled) {
          setSchools(response.data.schools);
        }
      } catch (loadError) {
        if (!cancelled) {
          setError(getErrorMessage(loadError));
        }
      } finally {
        if (!cancelled) {
          setLoadingSchools(false);
        }
      }
    }

    void loadSchools();

    return () => {
      cancelled = true;
    };
  }, []);

  const toggleSchool = (schoolId: number) => {
    setSelectedSchoolIds((current) =>
      current.includes(schoolId)
        ? current.filter((id) => id !== schoolId)
        : [...current, schoolId],
    );
  };

  const handleSubmit = async () => {
    if (selectedSchoolIds.length === 0) {
      setError("Select at least one school to continue.");
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      const response = await apiClient.post<AssignSchoolsResponse>("/auth/onboarding/schools", {
        school_ids: selectedSchoolIds,
      });

      completeSchoolOnboarding(response.data);
      router.replace(roleHomePath(response.data.user.role?.slug));
    } catch (submitError) {
      setError(getErrorMessage(submitError));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Card className="w-full max-w-xl p-8">
      <Image
        src="/assets/pgos_logo.png"
        alt="PGoS Roll Call System"
        width={160}
        height={160}
        className="mx-auto py-4"
      />
      <p className="page-eyebrow text-center text-xl">Welcome{user?.name ? `, ${user.name.split(" ")[0]}` : ""}</p>
      <h2 className="mt-3 text-3xl font-semibold text-foreground">Choose your school</h2>
      <p className="mt-3 text-sm text-muted">
        Select the school or schools where you teach. You can change this later through your administrator if needed.
      </p>

      {error ? (
        <p className="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
          {error}
        </p>
      ) : null}

      <div className="mt-6 space-y-3">
        {loadingSchools ? (
          <p className="text-sm text-muted">Loading schools...</p>
        ) : schools.length === 0 ? (
          <p className="text-sm text-muted">No active schools are available. Contact your administrator.</p>
        ) : (
          schools.map((school) => {
            const selected = selectedSchoolIds.includes(school.id);

            return (
              <button
                key={school.id}
                type="button"
                onClick={() => toggleSchool(school.id)}
                className={`flex w-full items-center justify-between rounded-xl border px-4 py-3 text-left transition ${
                  selected
                    ? "border-primary bg-primary/5"
                    : "border-border hover:border-primary/40"
                }`}
              >
                <span>
                  <span className="block font-medium text-foreground">{school.name}</span>
                  <span className="block text-xs text-muted">{school.code}</span>
                </span>
                <span
                  className={`h-5 w-5 rounded-full border ${
                    selected ? "border-primary bg-primary" : "border-border"
                  }`}
                />
              </button>
            );
          })
        )}
      </div>

      <Button
        className="mt-6 w-full"
        size="lg"
        onClick={() => void handleSubmit()}
        disabled={submitting || loadingSchools || schools.length === 0}
      >
        {submitting ? "Saving..." : "Continue to dashboard"}
      </Button>
    </Card>
  );
}
