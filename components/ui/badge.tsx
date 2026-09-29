import React from "react";
import { cn } from "@/lib/utils";

export type BadgeVariant =
  | "primary"
  | "secondary"
  | "outline"
  | "destructive"
  | "success"
  | "warning"
  | "info";

export interface BadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  variant?: BadgeVariant;
}

const badgeVariants: Record<BadgeVariant, string> = {
  primary: "bg-slate-950 text-white hover:bg-slate-800 border-transparent",
  secondary: "bg-slate-100 text-slate-900 hover:bg-slate-200 border-transparent",
  outline: "bg-white text-slate-900 border border-slate-300 hover:bg-slate-50",
  destructive: "bg-red-600 text-white hover:bg-red-700 border-transparent",
  success: "bg-emerald-600 text-white hover:bg-emerald-700 border-transparent",
  warning: "bg-amber-500 text-white hover:bg-amber-600 border-transparent",
  info: "bg-purple-600 text-white hover:bg-purple-700 border-transparent",
};

export function Badge({
  className,
  variant = "primary",
  children,
  ...props
}: BadgeProps) {
  return (
    <span
      className={cn(
        "inline-flex items-center justify-center px-4 py-1.5 text-xs font-semibold rounded-full transition-all duration-150 shadow-sm select-none",
        badgeVariants[variant],
        className
      )}
      {...props}
    >
      {children}
    </span>
  );
}
