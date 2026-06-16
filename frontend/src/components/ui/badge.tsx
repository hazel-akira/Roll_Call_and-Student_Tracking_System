import { cn } from "@/lib/utils";

const badgeStyles: Record<string, string> = {
  present:
    "bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300",
  absent: "bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300",
  late: "bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300",
  excused:
    "bg-slate-100 text-slate-700 dark:bg-slate-700/40 dark:text-slate-200",
  open: "bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300",
  closed:
    "bg-slate-100 text-slate-700 dark:bg-slate-700/40 dark:text-slate-200",
  queued:
    "bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300",
  failed: "bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300",
  synced:
    "bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300",
};

export function Badge({
  value,
  className,
}: {
  value: string;
  className?: string;
}) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize",
        badgeStyles[value] ??
          "bg-slate-100 text-slate-700 dark:bg-slate-700/40 dark:text-slate-200",
        className,
      )}
    >
      {value.replaceAll("_", " ")}
    </span>
  );
}
