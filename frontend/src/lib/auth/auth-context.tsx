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
import { usePathname, useRouter } from "next/navigation";
import { apiClient } from "@/lib/api/client";
import { clearSession, readSession, writeSession } from "@/lib/auth/storage";
import { clearSelectedSchoolId } from "@/lib/tenant/school-storage";
import {
  consumeStoredNonce,
  handleMicrosoftRedirect,
  loginWithMicrosoft,
  logoutFromMicrosoft,
} from "@/lib/auth/msal";
import { roleHomePath } from "@/lib/utils";
import type { AppUser, AuthSession, TokenSet } from "@/types";

type AuthContextValue = {
  user: AppUser | null;
  tokens: TokenSet | null;
  loading: boolean;
  error: string | null;
  login: () => Promise<void>;
  logout: () => Promise<void>;
  refreshProfile: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

function getAuthErrorMessage(error: unknown): string {
  if (!isAxiosError(error)) {
    return error instanceof Error ? error.message : "Unable to initialize authentication.";
  }

  const data = error.response?.data as
    | { message?: string; errors?: Record<string, string[]> }
    | undefined;

  const fieldMessage = data?.errors
    ? Object.values(data.errors).flat().find((message) => typeof message === "string")
    : undefined;

  if (fieldMessage) {
    return fieldMessage;
  }

  if (data?.message && typeof data.message === "string") {
    return data.message;
  }

  return error.message || "Unable to initialize authentication.";
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const [user, setUser] = useState<AppUser | null>(null);
  const [tokens, setTokens] = useState<TokenSet | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const applySession = useCallback((session: AuthSession) => {
    setUser(session.user);
    setTokens(session.tokens);
    writeSession(session);
  }, []);

  const resetSession = useCallback(() => {
    setUser(null);
    setTokens(null);
    clearSession();
    clearSelectedSchoolId();
  }, []);

  const refreshProfile = useCallback(async () => {
    const profileResponse = await apiClient.get<{
      user: AppUser;
      current_school_id?: string | number | null;
    }>("/auth/me");
    const currentTokens = readSession()?.tokens;

    if (!currentTokens) {
      return;
    }

    applySession({
      user: profileResponse.data.user,
      tokens: currentTokens,
    });
  }, [applySession]);

  const refreshTokenPair = useCallback(async () => {
    const currentSession = readSession();

    if (!currentSession?.tokens.refresh_token) {
      throw new Error("No refresh token available.");
    }

    const refreshResponse = await apiClient.post<{ tokens: TokenSet }>(
      "/auth/refresh",
      { refresh_token: currentSession.tokens.refresh_token },
    );

    applySession({
      user: currentSession.user,
      tokens: refreshResponse.data.tokens,
    });

    await refreshProfile();
  }, [applySession, refreshProfile]);

  const completeMicrosoftExchange = useCallback(
    async (idToken: string) => {
      const exchangeResponse = await apiClient.post<{
        user: AppUser;
        tokens: TokenSet;
        current_school_id?: string | number | null;
      }>("/auth/microsoft/exchange", {
        id_token: idToken,
        nonce: consumeStoredNonce(),
      });

      const nextSession = {
        user: exchangeResponse.data.user,
        tokens: exchangeResponse.data.tokens,
      };

      applySession(nextSession);
      setError(null);
      router.replace(roleHomePath(nextSession.user.role?.slug));
    },
    [applySession, router],
  );

  useEffect(() => {
    let cancelled = false;

    async function bootstrap() {
      setLoading(true);

      try {
        const redirectResult = await handleMicrosoftRedirect();

        if (redirectResult?.idToken) {
          await completeMicrosoftExchange(redirectResult.idToken);
          return;
        }

        const session = readSession();

        if (!session) {
          resetSession();
          return;
        }

        applySession(session);

        try {
          await refreshProfile();
        } catch {
          await refreshTokenPair();
        }
      } catch (bootstrapError) {
        resetSession();
        setError(getAuthErrorMessage(bootstrapError));
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    void bootstrap();

    return () => {
      cancelled = true;
    };
  }, [applySession, completeMicrosoftExchange, refreshProfile, refreshTokenPair, resetSession]);

  const login = useCallback(async () => {
    setError(null);
    await loginWithMicrosoft();
  }, []);

  const logout = useCallback(async () => {
    const currentSession = readSession();

    try {
      if (currentSession?.tokens.refresh_token) {
        await apiClient.post("/auth/logout", {
          refresh_token: currentSession.tokens.refresh_token,
        });
      }
    } catch {
      // Ignore logout API failures and continue clearing local state.
    }

    resetSession();

    try {
      await logoutFromMicrosoft();
    } catch {
      router.replace("/login");
    }
  }, [resetSession, router]);

  useEffect(() => {
    if (loading) {
      return;
    }

    if (pathname === "/callback") {
      if (user) {
        router.replace(roleHomePath(user.role?.slug));
      } else {
        router.replace("/login");
      }
      return;
    }

    if (pathname === "/login") {
      return;
    }

    if (!user) {
      router.replace("/login");
    }
  }, [loading, pathname, router, user]);

  const value = useMemo(
    () => ({
      user,
      tokens,
      loading,
      error,
      login,
      logout,
      refreshProfile,
    }),
    [error, loading, login, logout, refreshProfile, tokens, user],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error("useAuth must be used within AuthProvider.");
  }

  return context;
}
