import { PublicClientApplication } from "@azure/msal-browser";

const loginRequest = {
  scopes: ["openid", "profile", "email"] as string[],
  prompt: "select_account",
} as const;

let instancePromise: Promise<PublicClientApplication> | null = null;

function getBaseUrl() {
  if (typeof window !== "undefined") {
    return window.location.origin;
  }

  return process.env.NEXT_PUBLIC_APP_URL ?? "http://localhost:3000";
}

function getAuthority() {
  if (process.env.NEXT_PUBLIC_MICROSOFT_AUTHORITY) {
    return process.env.NEXT_PUBLIC_MICROSOFT_AUTHORITY;
  }

  const tenantId = process.env.NEXT_PUBLIC_MICROSOFT_TENANT_ID;

  if (tenantId) {
    return `https://login.microsoftonline.com/${tenantId}`;
  }

  return "https://login.microsoftonline.com/common";
}

function createMsalInstance() {
  return new PublicClientApplication({
    auth: {
      clientId: process.env.NEXT_PUBLIC_MICROSOFT_CLIENT_ID ?? "",
      authority: getAuthority(),
      redirectUri:
        process.env.NEXT_PUBLIC_MICROSOFT_REDIRECT_URI ?? `${getBaseUrl()}/callback`,
      postLogoutRedirectUri: `${getBaseUrl()}/login`,
    },
    cache: {
      cacheLocation: "sessionStorage",
    },
  });
}

export async function getMsalInstance() {
  if (!instancePromise) {
    const instance = createMsalInstance();
    instancePromise = instance.initialize().then(() => instance);
  }

  return instancePromise;
}

export async function loginWithMicrosoft() {
  const instance = await getMsalInstance();
  const nonce = window.crypto.randomUUID();
  window.sessionStorage.setItem("roll-call-msal-nonce", nonce);

  await instance.loginRedirect({
    ...loginRequest,
    nonce,
  });
}

export async function handleMicrosoftRedirect() {
  const instance = await getMsalInstance();
  const result = await instance.handleRedirectPromise();

  if (result?.account) {
    instance.setActiveAccount(result.account);
  }

  return result;
}

export async function logoutFromMicrosoft() {
  const instance = await getMsalInstance();
  const account = instance.getActiveAccount() ?? instance.getAllAccounts()[0];

  await instance.logoutRedirect({
    account,
    postLogoutRedirectUri: `${getBaseUrl()}/login`,
  });
}

export function consumeStoredNonce() {
  if (typeof window === "undefined") {
    return null;
  }

  const nonce = window.sessionStorage.getItem("roll-call-msal-nonce");
  window.sessionStorage.removeItem("roll-call-msal-nonce");
  return nonce;
}
