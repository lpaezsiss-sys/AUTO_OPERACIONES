import { ButtonHTMLAttributes, ReactNode } from "react";

type Variant = "primary" | "secondary" | "danger" | "ghost";

const styles: Record<Variant, string> = {
  primary:
    "bg-accent text-white hover:bg-accent-hover shadow-sm disabled:opacity-60",
  secondary:
    "bg-surface text-ink border border-border hover:bg-bg-deep disabled:opacity-60",
  danger:
    "bg-danger text-white hover:opacity-90 disabled:opacity-60",
  ghost: "text-ink-muted hover:bg-bg-deep hover:text-ink disabled:opacity-60",
};

export function Button({
  children,
  variant = "primary",
  className = "",
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & {
  children: ReactNode;
  variant?: Variant;
}) {
  return (
    <button
      className={`inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-all active:scale-[0.98] ${styles[variant]} ${className}`}
      {...props}
    >
      {children}
    </button>
  );
}
