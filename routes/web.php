<?php

use App\Filament\Support\ModuleCatalog;
use App\Http\Middleware\EnsureModuleAccess;
use App\Modules\Pedagogie\Http\Controllers\AnalyserProgrammeController;
use App\Modules\RH\Models\BulletinPaie;
use App\Modules\RH\Services\BulletinPaiePdfService;
use App\Modules\Scolarite\Http\Controllers\ImprimerFactureController;
use App\Modules\Scolarite\Http\Controllers\ImprimerInscriptionDocumentController;
use App\Modules\Scolarite\Http\Controllers\ImprimerInscriptionsController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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

Route::get('/impressions/inscriptions/{inscription}/recu', [ImprimerInscriptionDocumentController::class, 'recu'])
    ->middleware(['web', 'auth', EnsureModuleAccess::class])
    ->name('impressions.inscriptions.recu');

Route::get('/impressions/inscriptions/{inscription}/facture', [ImprimerInscriptionDocumentController::class, 'facture'])
    ->middleware(['web', 'auth', EnsureModuleAccess::class])
    ->name('impressions.inscriptions.facture');

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
        abort_unless(Auth::user()?->hasAnyRole(['Fondateur', 'Comptable']), 403);

        return app(BulletinPaiePdfService::class)->telecharger($bulletin);
    })->name('rh.bulletins-paie.telecharger');

    Route::get('/rh/bulletins-paie/{bulletin}/imprimer', function (BulletinPaie $bulletin) {
        abort_unless(Auth::user()?->hasAnyRole(['Fondateur', 'Comptable']), 403);

        return app(BulletinPaiePdfService::class)->imprimer($bulletin);
    })->name('rh.bulletins-paie.imprimer');

    Route::post('/pedagogie/programmes/{programme}/analyser', AnalyserProgrammeController::class)
        ->name('pedagogie.programmes.analyser');
});
