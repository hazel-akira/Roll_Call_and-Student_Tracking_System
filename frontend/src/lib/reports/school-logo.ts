/**
 * Resolve the display logo for a school in report UIs.
 * Prefers API logo_url (upload or backend default), then bundled assets by school code.
 */
const DEFAULT_SCHOOL_LOGOS: Record<string, string> = {
  PS: "/assets/schools/ps.png",
  PGS: "/assets/schools/pgslogo.webp",
  PJA: "/assets/schools/pjaogo.png",
  PGJA: "/assets/schools/pgjalogo.webp",
  SPTA: "/assets/schools/ST PAULS THOMAS EMBLEM.png",
};

const FALLBACK_LOGO = "/assets/schools/pgos_logo.png";

export function schoolLogoSrc(school?: {
  code?: string | null;
  logo_url?: string | null;
} | null): string {
  if (school?.logo_url) {
    return school.logo_url;
  }

  const code = school?.code?.toUpperCase();
  if (code && DEFAULT_SCHOOL_LOGOS[code]) {
    return DEFAULT_SCHOOL_LOGOS[code];
  }

  return FALLBACK_LOGO;
}
