"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";
import { isAxiosError } from "axios";
import { apiClient } from "@/lib/api/client";
import { useAuth } from "@/lib/auth/auth-context";
import {
  ALL_SCHOOLS_VALUE,
  clearSelectedSchoolId,
  isAllSchoolsSelection,
  readSelectedSchoolId,
  writeSelectedSchoolId,
} from "@/lib/tenant/school-storage";
import type { AppUser, School } from "@/types";

type SchoolContextValue = {
  schools: School[];
  schoolId: string | null;
  currentSchool: School | null;
  viewingAllSchools: boolean;
  canSelectAllSchools: boolean;
  loading: boolean;
  error: string | null;
  canSwitchSchool: boolean;
  revision: number;
  selectSchool: (schoolId: string) => Promise<void>;
  refreshSchools: () => Promise<void>;
};

const SchoolContext = createContext<SchoolContextValue | undefined>(undefined);

function userCanSelectAllSchools(user: AppUser | null): boolean {
  const slug = user?.role?.slug;
  return slug === "admin" || slug === "ict_staff";
}

function resolveInitialSchoolId(
  schools: School[],
  apiCurrentId: string | number | null | undefined,
  canSelectAllSchools: boolean,
): string | null {
  if (schools.length === 0) {
    return null;
  }

  const allowed = new Set(schools.map((school) => String(school.id)));
  const stored = readSelectedSchoolId();

  if (canSelectAllSchools && isAllSchoolsSelection(stored)) {
    return null;
  }

  if (stored && allowed.has(stored)) {
    return stored;
  }

  if (apiCurrentId !== null && apiCurrentId !== undefined && allowed.has(String(apiCurrentId))) {
    return String(apiCurrentId);
  }

  if (canSelectAllSchools) {
    return null;
  }

  return String(schools[0].id);
}

export function SchoolProvider({ children }: { children: React.ReactNode }) {
  const { user, loading: authLoading } = useAuth();
  const [schools, setSchools] = useState<School[]>([]);
  const [schoolId, setSchoolId] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [revision, setRevision] = useState(0);

  const canSelectAllSchools = userCanSelectAllSchools(user);
  const viewingAllSchools = canSelectAllSchools && schoolId === null;

  const currentSchool = useMemo(
    () => schools.find((school) => String(school.id) === schoolId) ?? null,
    [schoolId, schools],
  );

  const applySchoolId = useCallback((nextId: string | null, persistAll = false) => {
    setSchoolId(nextId);
    if (nextId) {
      writeSelectedSchoolId(nextId);
    } else if (persistAll) {
      writeSelectedSchoolId(ALL_SCHOOLS_VALUE);
    } else {
      clearSelectedSchoolId();
    }
    setRevision((value) => value + 1);
  }, []);

  const refreshSchools = useCallback(async () => {
    if (!user) {
      setSchools([]);
      applySchoolId(null);
      setError(null);
      setLoading(false);
      return;
    }

    setLoading(true);

    try {
      const response = await apiClient.get<{
        data: School[];
        current_school_id?: string | number | null;
      }>("/schools");

      const items = Array.isArray(response.data?.data) ? response.data.data : [];
      setSchools(items);
      setError(null);

      const canSelectAll = userCanSelectAllSchools(user);
      const resolved = resolveInitialSchoolId(items, response.data.current_school_id, canSelectAll);
      const previousId = readSelectedSchoolId();
      applySchoolId(resolved, canSelectAll && resolved === null);

      if (resolved && resolved !== previousId) {
        await apiClient.post("/schools/select", { school_id: Number(resolved) });
      } else if (canSelectAll && resolved === null && previousId !== ALL_SCHOOLS_VALUE) {
        await apiClient.post("/schools/clear");
      }
    } catch (requestError) {
      setSchools([]);
      applySchoolId(null);

      if (isAxiosError(requestError) && requestError.response?.status === 403) {
        setError(
          typeof requestError.response.data === "object" &&
            requestError.response.data !== null &&
            "message" in requestError.response.data &&
            typeof requestError.response.data.message === "string"
            ? requestError.response.data.message
            : "Your account is not assigned to any school.",
        );
      } else {
        setError("Unable to load schools for your account.");
      }
    } finally {
      setLoading(false);
    }
  }, [applySchoolId, user]);

  useEffect(() => {
    if (authLoading) {
      return;
    }

    if (!user) {
      setSchools([]);
      applySchoolId(null);
      setError(null);
      setLoading(false);
      return;
    }

    void refreshSchools();
  }, [applySchoolId, authLoading, refreshSchools, user]);

  const selectSchool = useCallback(
    async (nextSchoolId: string) => {
      if (nextSchoolId === ALL_SCHOOLS_VALUE) {
        if (!canSelectAllSchools) {
          return;
        }
        await apiClient.post("/schools/clear");
        applySchoolId(null, true);
        return;
      }

      if (!schools.some((school) => String(school.id) === nextSchoolId)) {
        return;
      }

      await apiClient.post("/schools/select", { school_id: Number(nextSchoolId) });
      applySchoolId(nextSchoolId);
    },
    [applySchoolId, canSelectAllSchools, schools],
  );

  const canSwitchSchool = schools.length > 1 || canSelectAllSchools;

  const value = useMemo(
    () => ({
      schools,
      schoolId,
      currentSchool,
      viewingAllSchools,
      canSelectAllSchools,
      loading,
      error,
      canSwitchSchool,
      revision,
      selectSchool,
      refreshSchools,
    }),
    [
      canSelectAllSchools,
      canSwitchSchool,
      currentSchool,
      error,
      loading,
      refreshSchools,
      revision,
      schoolId,
      schools,
      selectSchool,
      viewingAllSchools,
    ],
  );

  return <SchoolContext.Provider value={value}>{children}</SchoolContext.Provider>;
}

export function useSchool() {
  const context = useContext(SchoolContext);

  if (!context) {
    throw new Error("useSchool must be used within SchoolProvider.");
  }

  return context;
}
