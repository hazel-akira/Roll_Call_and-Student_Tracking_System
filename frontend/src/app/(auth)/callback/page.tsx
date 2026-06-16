import { Spinner } from "@/components/ui/spinner";

export default function CallbackPage() {
  return (
    <div className="flex min-h-screen items-center justify-center">
      <div className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <Spinner />
        Completing Microsoft sign-in...
      </div>
    </div>
  );
}
