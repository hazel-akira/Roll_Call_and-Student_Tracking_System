import { NextRequest, NextResponse } from "next/server";

export function middleware(request: NextRequest) {
  if (process.env.NODE_ENV === "production") {
    return NextResponse.next();
  }

  const host = request.headers.get("host") ?? "";

  if (!host.startsWith("127.0.0.1")) {
    return NextResponse.next();
  }

  const url = request.nextUrl.clone();
  url.hostname = "localhost";

  return NextResponse.redirect(url);
}

export const config = {
  matcher: "/:path*",
};
