import { Spinner } from "@/components/ui/spinner";

export default function CallbackPage() {
  return (
    <div className="flex min-h-screen items-center justify-center">
      <div className="flex items-center gap-3 rounded-2xl border bg-(--surface-solid) px-6 py-4 text-foreground shadow-sm">
        <Spinner />
        Completing Microsoft sign-in...
      </div>
    </div>
  );
}
