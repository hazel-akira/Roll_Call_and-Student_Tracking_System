import { PublicClientApplication } from "@azure/msal-browser";

const loginRequest = {
  scopes: ["openid", "profile", "email"] as string[],
  prompt: "select_account",
} as const;

let instancePromise: Promise<PublicClientApplication> | null = null;

function assertSecureBrowserContext(): void {
  if (typeof window === "undefined") {
    throw new Error("Microsoft sign-in is only available in the browser.");
  }

  if (!window.isSecureContext) {
    throw new Error(
      "Microsoft sign-in requires HTTPS or localhost. During development, open http://localhost:3000 instead of your LAN IP (for example http://192.168.x.x:3000).",
    );
  }

  if (!window.crypto?.subtle) {
    throw new Error(
      "This browser does not expose Web Crypto, which Microsoft sign-in requires. Try a modern browser or use localhost/HTTPS.",
    );
  }
}

function getRedirectUri() {
  if (typeof window !== "undefined") {
    return `${window.location.origin}/callback`;
  }

  return process.env.NEXT_PUBLIC_MICROSOFT_REDIRECT_URI ?? "http://localhost:3000/callback";
}

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
  assertSecureBrowserContext();

  return new PublicClientApplication({
    auth: {
      clientId: process.env.NEXT_PUBLIC_MICROSOFT_CLIENT_ID ?? "",
      authority: getAuthority(),
      redirectUri: getRedirectUri(),
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
    instancePromise = instance.initialize().then(() => instance).catch((error) => {
      instancePromise = null;
      throw error;
    });
  }

  return instancePromise;
}

export async function clearMicrosoftAuthCache() {
  if (typeof window === "undefined") {
    return;
  }

  instancePromise = null;

  for (const key of Object.keys(window.sessionStorage)) {
    if (key.startsWith("msal.") || key === "roll-call-msal-nonce") {
      window.sessionStorage.removeItem(key);
    }
  }
}

export async function loginWithMicrosoft() {
  await clearMicrosoftAuthCache();
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
