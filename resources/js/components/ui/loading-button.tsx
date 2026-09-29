import React, { useState } from "react";
import { MessageLoading } from "@/components/ui/message-loading";
import { cn } from "@/lib/utils";
import { CheckCircle2, XCircle } from "lucide-react";

export type ActionStatus = "idle" | "loading" | "success" | "error";

export interface LoadingButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  onAction?: () => Promise<boolean | void>;
  loadingText?: string;
  successText?: string;
  errorText?: string;
  variant?: "primary" | "secondary" | "danger" | "outline";
  showOverlay?: boolean;
}

export function LoadingButton({
  children,
  onAction,
  onClick,
  loadingText = "Chargement en cours...",
  successText = "Succès !",
  errorText = "Échec de l'action",
  className,
  disabled,
  variant = "primary",
  showOverlay = false,
  ...props
}: LoadingButtonProps) {
  const [status, setStatus] = useState<ActionStatus>("idle");

  const handleClick = async (e: React.MouseEvent<HTMLButtonElement>) => {
    if (status === "loading") return;

    if (onClick) {
      onClick(e);
    }

    if (onAction) {
      setStatus("loading");
      try {
        const result = await onAction();
        if (result === false) {
          setStatus("error");
        } else {
          setStatus("success");
        }
      } catch (err) {
        setStatus("error");
      } finally {
        setTimeout(() => {
          setStatus("idle");
        }, 3000);
      }
    }
  };

  const variantStyles = {
    primary: "bg-blue-600 hover:bg-blue-700 text-white border-transparent",
    secondary: "bg-slate-800 hover:bg-slate-900 text-white border-transparent",
    danger: "bg-red-600 hover:bg-red-700 text-white border-transparent",
    outline: "bg-transparent border-slate-300 hover:bg-slate-100 text-slate-700",
  };

  return (
    <div className="relative inline-block">
      <button
        {...props}
        onClick={handleClick}
        disabled={disabled || status === "loading"}
        className={cn(
          "inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg font-medium transition-all duration-200 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-70 disabled:cursor-not-allowed",
          variantStyles[variant],
          status === "success" && "bg-emerald-600 hover:bg-emerald-700 text-white",
          status === "error" && "bg-red-600 hover:bg-red-700 text-white",
          className
        )}
      >
        {status === "loading" && (
          <>
            <MessageLoading size={20} className="text-current" />
            <span>{loadingText}</span>
          </>
        )}

        {status === "success" && (
          <>
            <CheckCircle2 className="w-5 h-5 text-white animate-bounce" />
            <span>{successText}</span>
          </>
        )}

        {status === "error" && (
          <>
            <XCircle className="w-5 h-5 text-white animate-pulse" />
            <span>{errorText}</span>
          </>
        )}

        {status === "idle" && children}
      </button>

      {showOverlay && status === "loading" && (
        <div className="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex flex-col items-center justify-center text-white space-y-4 animate-in fade-in duration-200">
          <div className="bg-slate-900/90 border border-slate-700 p-6 rounded-2xl shadow-2xl flex flex-col items-center gap-3">
            <MessageLoading size={40} className="text-blue-400" />
            <p className="text-sm font-medium tracking-wide text-slate-200">
              {loadingText}
            </p>
          </div>
        </div>
      )}
    </div>
  );
}
