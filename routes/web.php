<?php

use App\Filament\Support\ModuleCatalog;
use App\Http\Middleware\EnsureModuleAccess;
use App\Modules\RH\Models\BulletinPaie;
use App\Modules\RH\Services\BulletinPaiePdfService;
use App\Modules\Scolarite\Http\Controllers\ImprimerFactureController;
use App\Modules\Scolarite\Http\Controllers\ImprimerInscriptionsController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/accueil', function () {
    return view('home', [
        'modules' => ModuleCatalog::visibleFor(Auth::user()),
    ]);
})->middleware('auth')->name('home');

Route::get('/impressions/inscriptions', ImprimerInscriptionsController::class)
    ->middleware(['web', 'auth', EnsureModuleAccess::class])
    ->name('impressions.inscriptions');

Route::get('/impressions/facture/{facture}', ImprimerFactureController::class)
    ->middleware(['web', 'auth', EnsureModuleAccess::class])
    ->name('impressions.facture');

Route::middleware(['auth'])->group(function () {
    Route::get('/rh/bulletins-paie/imprimer-tous/{mois}/{annee}', function (int $mois, int $annee) {
        abort_unless(Auth::user()?->hasAnyRole(['Fondateur', 'Comptable']), 403);
        abort_unless($mois >= 1 && $mois <= 12 && $annee >= 2000 && $annee <= 2100, 422);

        return app(BulletinPaiePdfService::class)->imprimerTous($mois, $annee);
    })->name('rh.bulletins-paie.imprimer-tous');

    Route::get('/rh/bulletins-paie/{bulletin}/pdf', function (BulletinPaie $bulletin) {
        return app(BulletinPaiePdfService::class)->telecharger($bulletin);
    })->name('rh.bulletins-paie.telecharger');

    Route::get('/rh/bulletins-paie/{bulletin}/imprimer', function (BulletinPaie $bulletin) {
        return app(BulletinPaiePdfService::class)->imprimer($bulletin);
    })->name('rh.bulletins-paie.imprimer');
});

// ============================================================
// 📱 WEBHOOK WHATSAPP BUSINESS
// ============================================================
Route::get('/webhook', function (\Illuminate\Http\Request $request) {
    $verifyToken = config('services.whatsapp.verify_token');

    $mode = $request->query('hub.mode', $request->query('hub_mode'));
    $token = $request->query('hub.verify_token', $request->query('hub_verify_token'));
    $challenge = $request->query('hub.challenge', $request->query('hub_challenge'));

    Log::info('Vérification webhook WhatsApp', [
        'mode' => $mode,
        'token_valide' => hash_equals((string) $verifyToken, (string) $token),
        'challenge_recu' => $challenge !== null,
    ]);

    if ($mode === 'subscribe' && $token === $verifyToken) {
        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    return response()->json(['error' => 'Token invalide'], 403);
});

// ============================================================
// 📥 RÉCEPTION DES MESSAGES WHATSAPP (à compléter plus tard)
// ============================================================
Route::post('/webhook', function (\Illuminate\Http\Request $request) {
    $rawBody = $request->getContent();
    $payload = $request->json()->all();

    if ($payload === [] && trim($rawBody) !== '') {
        $decodedPayload = json_decode($rawBody, true);

        if (is_array($decodedPayload)) {
            $payload = $decodedPayload;
        } else {
            Log::warning('Payload JSON WhatsApp invalide', [
                'json_error' => json_last_error_msg(),
                'content_type' => $request->header('Content-Type'),
            ]);

            return response()->json(['error' => 'Payload JSON invalide'], 400);
        }
    }

    Log::info('Webhook WhatsApp reçu', [
        'object' => $payload['object'] ?? null,
        'entries' => count($payload['entry'] ?? []),
        'raw_body_present' => $rawBody !== '',
        'content_type' => $request->header('Content-Type'),
    ]);

    foreach ($payload['entry'] ?? [] as $entry) {
        foreach ($entry['changes'] ?? [] as $change) {
            $value = $change['value'] ?? [];

            foreach ($value['messages'] ?? [] as $message) {
                Log::info('Message WhatsApp reçu', [
                    'from' => $message['from'] ?? 'inconnu',
                    'text' => $message['text']['body'] ?? 'pas de texte',
                    'type' => $message['type'] ?? 'inconnu',
                    'timestamp' => $message['timestamp'] ?? null,
                ]);
            }

            foreach ($value['statuses'] ?? [] as $status) {
                Log::info('Statut WhatsApp reçu', [
                    'message_id' => $status['id'] ?? null,
                    'status' => $status['status'] ?? 'inconnu',
                    'recipient_id' => $status['recipient_id'] ?? null,
                    'timestamp' => $status['timestamp'] ?? null,
                    'errors' => $status['errors'] ?? null,
                ]);
            }
        }
    }
    
    // WhatsApp attend une réponse 200 OK
    return response()->json(['status' => 'ok'], 200);
});