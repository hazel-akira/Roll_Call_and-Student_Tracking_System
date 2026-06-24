import { cn } from "@/lib/utils";

export function Card({
  className,
  ...props
}: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn(
        "rounded-2xl border bg-(--surface-solid) text-foreground shadow-sm",
        className,
      )}
      {...props}
    />
  );
}
