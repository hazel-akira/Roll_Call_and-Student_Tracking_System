"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";

export default function ReportsIndexPage() {
  const router = useRouter();

  useEffect(() => {
    router.replace("/reports/attendance");
  }, [router]);

  return null;
}
