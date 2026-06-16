import * as React from "react";
import { cva, type VariantProps } from "class-variance-authority";
import { cn } from "@/lib/utils";

const buttonVariants = cva(
  "inline-flex items-center justify-center rounded-xl text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-(--color-accent) disabled:pointer-events-none disabled:opacity-50",
  {
    variants: {
      variant: {
        default:
          "bg-(--color-primary) px-4 py-2.5 text-white shadow-[var(--shadow-card)] hover:bg-(--color-primary-deep)",
        secondary:
          "bg-(--surface-muted) px-4 py-2.5 text-(--color-primary) hover:bg-[rgba(212,174,43,0.18)] dark:text-(--foreground)",
        outline:
          "border px-4 py-2.5 text-(--color-primary) hover:bg-(--surface-muted) dark:bg-transparent dark:text-(--foreground)",
        ghost:
          "px-3 py-2 text-(--color-primary) hover:bg-(--surface-muted) dark:text-(--foreground)",
      },
      size: {
        default: "h-11",
        sm: "h-9 px-3",
        lg: "h-12 px-5",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
);

export interface ButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<typeof buttonVariants> {}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, ...props }, ref) => (
    <button
      className={cn(buttonVariants({ variant, size, className }))}
      ref={ref}
      {...props}
    />
  ),
);

Button.displayName = "Button";
