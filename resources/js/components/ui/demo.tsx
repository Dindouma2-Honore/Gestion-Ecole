import { useState } from "react";
import { MessageLoading } from "@/components/ui/message-loading";
import { LoadingButton } from "@/components/ui/loading-button";
import AnimatedLoadingSkeleton from "@/components/ui/animated-loading-skeleton";
import { CheckCircle2, XCircle, RefreshCw } from "lucide-react";

export function SkeletonDemo() {
  return <AnimatedLoadingSkeleton />;
}

function MessageLoadingDemo() {
  const [activeState, setActiveState] = useState<"idle" | "loading" | "success" | "error">("idle");
  const [feedbackMessage, setFeedbackMessage] = useState<string>("");

  const simulateSuccessAction = async (): Promise<boolean> => {
    return new Promise((resolve) => {
      setTimeout(() => {
        resolve(true);
      }, 2000);
    });
  };

  const simulateErrorAction = async (): Promise<boolean> => {
    return new Promise((resolve) => {
      setTimeout(() => {
        resolve(false);
      }, 2000);
    });
  };

  const handleManualAction = (shouldSucceed: boolean) => {
    setActiveState("loading");
    setFeedbackMessage("Traitement de la requête en cours...");

    setTimeout(() => {
      if (shouldSucceed) {
        setActiveState("success");
        setFeedbackMessage("Action exécutée avec succès !");
      } else {
        setActiveState("error");
        setFeedbackMessage("Erreur lors de l'exécution de la requête.");
      }
    }, 2500);
  };

  return (
    <div className="max-w-xl mx-auto p-6 bg-slate-900 border border-slate-800 text-slate-100 rounded-2xl shadow-xl space-y-8 font-sans">
      <div className="space-y-2 text-center">
        <h2 className="text-2xl font-bold tracking-tight text-white">
          Démonstration des Boutons & États de Chargement
        </h2>
        <p className="text-slate-400 text-sm">
          Cliquez sur un bouton pour voir le composant <code className="text-blue-400">MessageLoading</code> s'activer avant d'afficher le résultat.
        </p>
      </div>

      {/* Section 1: Composants Bouton Interactifs Auto-Gérés */}
      <div className="space-y-4 bg-slate-800/50 p-5 rounded-xl border border-slate-700/50">
        <h3 className="text-sm font-semibold text-slate-300 uppercase tracking-wider">
          1. Boutons d'Action Auto-Gérés
        </h3>
        <div className="flex flex-wrap gap-4 items-center">
          <LoadingButton
            onAction={simulateSuccessAction}
            loadingText="Enregistrement..."
            successText="Enregistré !"
            variant="primary"
          >
            Tester Succès (2s)
          </LoadingButton>

          <LoadingButton
            onAction={simulateErrorAction}
            loadingText="Connexion..."
            errorText="Échec de connexion"
            variant="danger"
          >
            Tester Échec (2s)
          </LoadingButton>
        </div>
      </div>

      {/* Section 2: Page / Carte de Chargement Global avec États */}
      <div className="space-y-4 bg-slate-800/50 p-5 rounded-xl border border-slate-700/50">
        <div className="flex items-center justify-between">
          <h3 className="text-sm font-semibold text-slate-300 uppercase tracking-wider">
            2. Carte d'Écran avec Loader Global
          </h3>
          {activeState !== "idle" && (
            <button
              onClick={() => {
                setActiveState("idle");
                setFeedbackMessage("");
              }}
              className="inline-flex items-center gap-1 text-xs text-slate-400 hover:text-white transition-colors"
            >
              <RefreshCw className="w-3.5 h-3.5" /> Réinitialiser
            </button>
          )}
        </div>

        <div className="min-h-[140px] flex flex-col items-center justify-center p-6 bg-slate-950 rounded-xl border border-slate-800 transition-all duration-300">
          {activeState === "idle" && (
            <div className="text-center space-y-3">
              <p className="text-slate-400 text-sm">
                Aucune action en cours. Déclenchez une simulation ci-dessous :
              </p>
              <div className="flex justify-center gap-3">
                <button
                  onClick={() => handleManualAction(true)}
                  className="px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white transition-all shadow"
                >
                  Simuler Succès
                </button>
                <button
                  onClick={() => handleManualAction(false)}
                  className="px-4 py-2 text-xs font-semibold rounded-lg bg-rose-600 hover:bg-rose-500 text-white transition-all shadow"
                >
                  Simuler Échec
                </button>
              </div>
            </div>
          )}

          {activeState === "loading" && (
            <div className="flex flex-col items-center gap-3 text-blue-400 animate-in fade-in duration-200">
              <MessageLoading size={36} className="text-blue-400" />
              <span className="text-sm font-medium text-slate-300">{feedbackMessage}</span>
            </div>
          )}

          {activeState === "success" && (
            <div className="flex flex-col items-center gap-3 text-emerald-400 animate-in zoom-in-95 duration-200">
              <CheckCircle2 className="w-10 h-10 text-emerald-400 animate-bounce" />
              <span className="text-sm font-semibold text-emerald-300">{feedbackMessage}</span>
            </div>
          )}

          {activeState === "error" && (
            <div className="flex flex-col items-center gap-3 text-rose-400 animate-in zoom-in-95 duration-200">
              <XCircle className="w-10 h-10 text-rose-400 animate-pulse" />
              <span className="text-sm font-semibold text-rose-300">{feedbackMessage}</span>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

export { MessageLoadingDemo };
