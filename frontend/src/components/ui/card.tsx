import { cn } from "@/lib/utils";

export function Card({
  className,
  ...props
}: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn(
        "rounded-2xl border border-slate-200 bg-blue/900 shadow-sm dark:border-slate-800 dark:bg-slate",
        className,
      )}
      {...props}
    />
  );
}
