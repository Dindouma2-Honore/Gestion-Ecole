<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pedagogie\Contracts\ProgrammeServiceInterface;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\RH\Contracts\EnseignantServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AnalyserProgrammeController extends Controller
{
    public function __invoke(Request $request, Programme $programme): JsonResponse
    {
        $user = Auth::user();
        if ($user === null) {
            return response()->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        // Vérification des autorisations : enseignant propriétaire, Direction / Fondateur ou supervision
        $enseignantService = app()->bound(EnseignantServiceInterface::class)
            ? app(EnseignantServiceInterface::class)
            : null;
        $enseignantId = $enseignantService !== null ? $enseignantService->getEnseignantIdPourUtilisateur((int) $user->id) : null;

        $estProprietaire = $programme->enseignant_id !== null && (
            ($enseignantId !== null && (int) $programme->enseignant_id === (int) $enseignantId)
            || (int) $programme->enseignant_id === (int) $user->id
        );

        $estDirection = method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Fondateur', 'Directeur', 'Admin', 'Super Admin', 'Administrateur']);
        $peutSuperviser = method_exists($user, 'can') && $user->can('pedagogie.progression.superviser');

        if (! $estProprietaire && ! $estDirection && ! $peutSuperviser) {
            $peutVoir = method_exists($user, 'can') && $user->can('view', $programme);
            if (! $peutVoir) {
                return response()->json(['error' => 'Action non autorisée sur ce programme.'], Response::HTTP_FORBIDDEN);
            }
        }

        try {
            /** @var ProgrammeServiceInterface $service */
            $service = app(ProgrammeServiceInterface::class);
            $programmeMisAJour = $service->lancerAnalyseIntelligente($programme->id, (int) $user->id);

            return response()->json([
                'success' => true,
                'message' => 'L’analyse intelligente du programme a été lancée avec succès.',
                'programme' => [
                    'id' => $programmeMisAJour->id,
                    'statut' => $programmeMisAJour->statut,
                    'version' => $programmeMisAJour->version,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors du lancement de l’analyse : '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
