import axios, {
  type AxiosError,
  type AxiosInstance,
  type InternalAxiosRequestConfig,
} from "axios";
import { clearSession, readSession, readTokens, writeSession } from "@/lib/auth/storage";
import { ALL_SCHOOLS_VALUE, readSelectedSchoolId } from "@/lib/tenant/school-storage";

export const SELECTED_SCHOOL_STORAGE_KEY = "roll-call-selected-school-id";

const baseURL = process.env.NEXT_PUBLIC_API_URL ?? "http://127.0.0.1:8000/api/v1";

export const apiClient: AxiosInstance = axios.create({
  baseURL,
  timeout: 60_000,
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
  },
});

type RetriableRequestConfig = InternalAxiosRequestConfig & { _retry?: boolean };

let refreshPromise: Promise<string> | null = null;

function shouldAttemptTokenRefresh(config: RetriableRequestConfig | undefined): boolean {
  if (!config?.url) {
    return false;
  }

  const path = config.url;

  return !path.includes("/auth/refresh") && !path.includes("/auth/microsoft/exchange");
}

async function refreshAccessToken(): Promise<string> {
  const session = readSession();

  if (!session?.tokens.refresh_token) {
    throw new Error("No refresh token available.");
  }

  const response = await axios.post<{ tokens: { access_token: string; refresh_token: string } }>(
    `${baseURL}/auth/refresh`,
    { refresh_token: session.tokens.refresh_token },
    {
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
    },
  );

  const tokens = response.data.tokens;

  writeSession({
    user: session.user,
    tokens,
  });

  return tokens.access_token;
}

function redirectToLogin(): void {
  clearSession();

  if (typeof window !== "undefined" && window.location.pathname !== "/login") {
    window.location.replace("/login");
  }
}

async function getRefreshedAccessToken(): Promise<string> {
  if (!refreshPromise) {
    refreshPromise = refreshAccessToken().finally(() => {
      refreshPromise = null;
    });
  }

  return refreshPromise;
}

apiClient.interceptors.request.use((config) => {
  const tokens = readTokens();
  const schoolId = typeof window !== "undefined" ? readSelectedSchoolId() : null;

  if (tokens?.access_token) {
    config.headers.Authorization = `Bearer ${tokens.access_token}`;
  }

  if (schoolId && schoolId !== ALL_SCHOOLS_VALUE) {
    config.headers["X-School-Id"] = schoolId;
  }

  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const originalRequest = error.config as RetriableRequestConfig | undefined;

    if (
      error.response?.status !== 401 ||
      !originalRequest ||
      originalRequest._retry ||
      !shouldAttemptTokenRefresh(originalRequest)
    ) {
      return Promise.reject(error);
    }

    originalRequest._retry = true;

    try {
      const accessToken = await getRefreshedAccessToken();
      originalRequest.headers.Authorization = `Bearer ${accessToken}`;

      return apiClient(originalRequest);
    } catch {
      redirectToLogin();
      return Promise.reject(error);
    }
  },
);
