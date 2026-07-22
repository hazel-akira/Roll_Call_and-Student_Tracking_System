import { cn } from "@/lib/utils";

const badgeStyles: Record<string, string> = {
  present:
    "bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300",
  missing: "bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300",
  absent: "bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300",
  sick: "bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300",
  on_leave: "bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300",
  late: "bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300",
  excused:
    "bg-(--surface-muted) text-foreground",
  open: "bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200",
  closed:
    "bg-(--surface-muted) text-foreground",
  pending:
    "bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200",
  queued:
    "bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300",
  failed: "bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300",
  synced:
    "bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300",
  draft:
    "bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200",
  published:
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
          "bg-(--surface-muted) text-foreground",
        className,
      )}
    >
      {value.replaceAll("_", " ")}
    </span>
  );
}
