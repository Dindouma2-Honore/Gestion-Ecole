<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Contracts\BulletinServiceInterface;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\Bulletin;
use App\Modules\Pedagogie\Models\BulletinMatiere;
use App\Modules\Pedagogie\Models\BulletinMatiereNote;
use App\Modules\Pedagogie\Models\BulletinVersion;
use App\Modules\Pedagogie\Models\Evaluation;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\Note;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Contracts\DocumentTemplateServiceContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BulletinService implements BulletinServiceInterface
{
    /**
     * Seuils d'appréciation / mention, façon bulletin scolaire classique.
     * Volontairement en dur ici (pas de contrat de paramétrage connu pour ces
     * seuils) — à externaliser facilement vers ParametrageServiceContract si
     * l'établissement souhaite les personnaliser.
     */
    private const SEUILS_APPRECIATION = [
        16 => 'Excellent',
        14 => 'Très bien',
        12 => 'Bien',
        10 => 'Assez bien',
        8 => 'Insuffisant',
        0 => 'Très insuffisant',
    ];

    private const SEUILS_MENTION = [
        16 => "Tableau d'honneur — Félicitations",
        14 => "Tableau d'honneur — Encouragements",
        12 => 'Satisfaisant',
        10 => 'Passable',
        0 => 'Avertissement travail',
    ];

    public function __construct(
        private readonly ?EleveServiceInterface $eleveService = null,
        private readonly ?ClasseServiceInterface $classeService = null,
        private readonly ?AnneeScolaireServiceContract $anneeScolaire = null,
        private readonly ?DocumentServiceContract $documentService = null,
        private readonly ?DocumentTemplateServiceContract $documentTemplates = null,
    ) {}

    public function genererPourEleve(int $eleveId, int $classeId, int $anneeScolaireId, ?int $periodeId = null): object
    {
        $donneesClasse = $this->calculerDonneesClasse($classeId, $anneeScolaireId, $periodeId);

        if (! isset($donneesClasse['eleves'][$eleveId])) {
            throw new RuntimeException("Aucune note validée trouvée pour l'élève #{$eleveId} sur ce périmètre.");
        }

        return $this->persister($eleveId, $classeId, $anneeScolaireId, $periodeId, $donneesClasse);
    }

    public function genererPourClasse(int $classeId, int $anneeScolaireId, ?int $periodeId = null): Collection
    {
        $donneesClasse = $this->calculerDonneesClasse($classeId, $anneeScolaireId, $periodeId);

        return collect(array_keys($donneesClasse['eleves']))
            ->map(fn (int $eleveId) => $this->persister($eleveId, $classeId, $anneeScolaireId, $periodeId, $donneesClasse))
            ->values();
    }

    /**
     * Calcule, pour toute la classe, les moyennes par matière et générale de
     * chaque élève, le détail des notes prises en compte, le rang de chaque
     * élève dans chaque matière et en général, ainsi que les statistiques de
     * la classe (moyenne, plus forte, plus faible).
     *
     * @return array{
     *     eleves: array<int, array{
     *         moyenne_generale: float,
     *         rang_general: int,
     *         matieres: array<int, array{moyenne: float, coefficient: float, rang: int, moyenne_classe: float, appreciation: string, notes: array<int, array{titre: string, date: ?string, valeur: float, bareme: float, note_sur_20: float}>}>
     *     }>,
     *     effectif: int,
     *     moyenne_classe_generale: float,
     *     moyenne_plus_forte: float,
     *     moyenne_plus_faible: float,
     * }
     */
    private function calculerDonneesClasse(int $classeId, int $anneeScolaireId, ?int $periodeId): array
    {
        [$debut, $fin] = $this->bornesPeriode($anneeScolaireId, $periodeId);

        $evaluations = Evaluation::where('classe_id', $classeId)
            ->where('statut', 'valide')
            ->when($debut !== null, fn ($q) => $q->where('date_evaluation', '>=', $debut))
            ->when($fin !== null, fn ($q) => $q->where('date_evaluation', '<=', $fin))
            ->get();

        if ($evaluations->isEmpty()) {
            return ['eleves' => [], 'effectif' => 0, 'moyenne_classe_generale' => 0.0, 'moyenne_plus_forte' => 0.0, 'moyenne_plus_faible' => 0.0];
        }

        $coefficients = Matiere::whereIn('id', $evaluations->pluck('matiere_id')->unique())->pluck('coefficient_defaut', 'id');
        AffectationPedagogique::query()
            ->where('classe_id', $classeId)
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->whereNotNull('coefficient')
            ->where('actif', true)
            ->get()
            ->each(fn (AffectationPedagogique $affectation) => $coefficients->put($affectation->matiere_id, $affectation->coefficient));
        $niveauId = $this->classeService?->getNiveauId($classeId);
        if ($niveauId !== null) {
            OffrePedagogique::query()
                ->whereIn('matiere_id', $evaluations->pluck('matiere_id')->unique())
                ->where('niveau_id', $niveauId)
                ->where('annee_scolaire_id', $anneeScolaireId)
                ->where('actif', true)
                ->get()
                ->each(fn (OffrePedagogique $offre) => $coefficients->put($offre->matiere_id, $offre->coefficient_matiere));
        }
        $notes = Note::whereIn('evaluation_id', $evaluations->pluck('id'))->where('absent', false)->whereNotNull('valeur')->get();

        // eleve_id => matiere_id => [['titre','date','valeur','bareme','note_sur_20'], ...]
        $detailParEleveEtMatiere = [];
        foreach ($notes as $note) {
            $evaluation = $evaluations->firstWhere('id', $note->evaluation_id);
            if ($evaluation === null || (float) $evaluation->bareme <= 0) {
                continue;
            }

            $noteSur20 = round(((float) $note->valeur / (float) $evaluation->bareme) * 20, $this->decimales());

            $detailParEleveEtMatiere[$note->eleve_id][$evaluation->matiere_id][] = [
                'titre' => $evaluation->titre,
                'date' => $evaluation->date_evaluation?->format('Y-m-d'),
                'valeur' => (float) $note->valeur,
                'bareme' => (float) $evaluation->bareme,
                'note_sur_20' => $noteSur20,
                'coefficient_evaluation' => (float) ($evaluation->coefficient_evaluation ?: 1),
            ];
        }

        // eleve_id => matiere_id => ['moyenne' => x, 'coefficient' => y, 'notes' => [...]]
        $moyennesBrutes = [];
        foreach ($detailParEleveEtMatiere as $eleveId => $parMatiere) {
            foreach ($parMatiere as $matiereId => $detailNotes) {
                $sommeCoefficientsEvaluations = array_sum(array_column($detailNotes, 'coefficient_evaluation'));
                $moyenne = $sommeCoefficientsEvaluations > 0
                    ? array_sum(array_map(fn (array $note): float => $note['note_sur_20'] * $note['coefficient_evaluation'], $detailNotes)) / $sommeCoefficientsEvaluations
                    : 0.0;
                $moyennesBrutes[$eleveId][$matiereId] = [
                    'moyenne' => round($moyenne, $this->decimales()),
                    'coefficient' => (float) ($coefficients[$matiereId] ?? 1),
                    'notes' => $detailNotes,
                ];
            }
        }

        // Rang par matière : pour chaque matière, on classe tous les élèves
        // qui ont une moyenne dans cette matière (avec gestion des ex-æquo).
        $matiereIds = $evaluations->pluck('matiere_id')->unique();
        $rangsParMatiere = [];
        $moyenneClasseParMatiere = [];
        foreach ($matiereIds as $matiereId) {
            $moyennesMatiere = [];
            foreach ($moyennesBrutes as $eleveId => $matieres) {
                if (isset($matieres[$matiereId])) {
                    $moyennesMatiere[$eleveId] = $matieres[$matiereId]['moyenne'];
                }
            }

            if ($moyennesMatiere === []) {
                continue;
            }

            $rangsParMatiere[$matiereId] = $this->attribuerRangs($moyennesMatiere);
            $moyenneClasseParMatiere[$matiereId] = round(array_sum($moyennesMatiere) / count($moyennesMatiere), $this->decimales());
        }

        // Moyenne générale pondérée par élève, puis rang général de la classe.
        $moyennesGenerales = [];
        foreach ($moyennesBrutes as $eleveId => $matieres) {
            $sommePonderee = 0.0;
            $sommeCoefficients = 0.0;
            foreach ($matieres as $detail) {
                $sommePonderee += $detail['moyenne'] * $detail['coefficient'];
                $sommeCoefficients += $detail['coefficient'];
            }
            $moyennesGenerales[$eleveId] = $sommeCoefficients > 0 ? round($sommePonderee / $sommeCoefficients, $this->decimales()) : 0.0;
        }

        $rangsGeneraux = $this->attribuerRangs($moyennesGenerales);
        $effectif = count($moyennesGenerales);

        $eleves = [];
        foreach ($moyennesBrutes as $eleveId => $matieres) {
            $matieresFormatees = [];
            foreach ($matieres as $matiereId => $detail) {
                $matieresFormatees[$matiereId] = [
                    'moyenne' => $detail['moyenne'],
                    'coefficient' => $detail['coefficient'],
                    'rang' => $rangsParMatiere[$matiereId][$eleveId] ?? null,
                    'moyenne_classe' => $moyenneClasseParMatiere[$matiereId] ?? null,
                    'appreciation' => $this->appreciation($detail['moyenne']),
                    'notes' => $detail['notes'],
                ];
            }

            $eleves[$eleveId] = [
                'moyenne_generale' => $moyennesGenerales[$eleveId],
                'rang_general' => $rangsGeneraux[$eleveId],
                'matieres' => $matieresFormatees,
            ];
        }

        $valeursGenerales = array_values($moyennesGenerales);

        return [
            'eleves' => $eleves,
            'effectif' => $effectif,
            'moyenne_classe_generale' => $effectif > 0 ? round(array_sum($valeursGenerales) / $effectif, $this->decimales()) : 0.0,
            'moyenne_plus_forte' => $valeursGenerales !== [] ? round(max($valeursGenerales), $this->decimales()) : 0.0,
            'moyenne_plus_faible' => $valeursGenerales !== [] ? round(min($valeursGenerales), $this->decimales()) : 0.0,
        ];
    }

    /**
     * Classement façon "1, 2, 2, 4" (ex-æquo = même rang, le rang suivant
     * saute en conséquence) — convention standard des bulletins scolaires.
     *
     * @param  array<int, float>  $moyennes  eleve_id => moyenne
     * @return array<int, int> eleve_id => rang
     */
    private function attribuerRangs(array $moyennes): array
    {
        $tries = $moyennes;
        arsort($tries);

        $rangs = [];
        $position = 0;
        $dernierValeur = null;
        $dernierRang = 0;

        foreach ($tries as $eleveId => $valeur) {
            $position++;
            if ($dernierValeur === null || $valeur < $dernierValeur) {
                $dernierRang = $position;
                $dernierValeur = $valeur;
            }
            $rangs[$eleveId] = $dernierRang;
        }

        return $rangs;
    }

    private function appreciation(float $moyenne): string
    {
        foreach (config('pedagogie.appreciations', self::SEUILS_APPRECIATION) as $seuil => $libelle) {
            if ($moyenne >= $seuil) {
                return $libelle;
            }
        }

        return self::SEUILS_APPRECIATION[0];
    }

    private function mention(float $moyenneGenerale): string
    {
        foreach (config('pedagogie.mentions', self::SEUILS_MENTION) as $seuil => $libelle) {
            if ($moyenneGenerale >= $seuil) {
                return $libelle;
            }
        }

        return self::SEUILS_MENTION[0];
    }

    /** @return array{0: ?string, 1: ?string} */
    private function bornesPeriode(int $anneeScolaireId, ?int $periodeId): array
    {
        if ($periodeId === null || $this->anneeScolaire === null) {
            return [null, null];
        }

        $periode = collect($this->anneeScolaire->getPeriodes($anneeScolaireId))->firstWhere('id', $periodeId);

        if ($periode === null) {
            return [null, null];
        }

        $debut = data_get($periode, 'date_debut', data_get($periode, 'debut'));
        $fin = data_get($periode, 'date_fin', data_get($periode, 'fin'));

        return [$debut, $fin];
    }

    private function persister(int $eleveId, int $classeId, int $anneeScolaireId, ?int $periodeId, array $donneesClasse): Bulletin
    {
        $donneesEleve = $donneesClasse['eleves'][$eleveId];

        return DB::transaction(function () use ($eleveId, $classeId, $anneeScolaireId, $periodeId, $donneesClasse, $donneesEleve): Bulletin {
            $eleve = $this->eleveService?->getEleve($eleveId);
            $nomEleve = $eleve ? trim(($eleve['nom'] ?? '').' '.($eleve['prenom'] ?? '')) : null;

            $nomClasse = $this->classeService
                ? data_get(
                    collect($this->classeService->getToutesLesClasses($anneeScolaireId))->firstWhere('id', $classeId),
                    'nom'
                )
                : null;

            $existant = Bulletin::query()->where([
                'eleve_id' => $eleveId, 'classe_id' => $classeId,
                'annee_scolaire_id' => $anneeScolaireId, 'periode_id' => $periodeId,
            ])->first();
            if ($existant && in_array($existant->statut, ['publie', 'archive'], true)) {
                throw new RuntimeException('Ce bulletin est publié et immuable. Archivez-le et créez une nouvelle version pour toute correction.');
            }

            $bulletin = Bulletin::updateOrCreate(
                [
                    'eleve_id' => $eleveId,
                    'classe_id' => $classeId,
                    'annee_scolaire_id' => $anneeScolaireId,
                    'periode_id' => $periodeId,
                ],
                [
                    'moyenne_generale' => $donneesEleve['moyenne_generale'],
                    'rang' => $donneesEleve['rang_general'],
                    'effectif_classe' => $donneesClasse['effectif'],
                    'moyenne_classe_generale' => $donneesClasse['moyenne_classe_generale'],
                    'moyenne_plus_forte' => $donneesClasse['moyenne_plus_forte'],
                    'moyenne_plus_faible' => $donneesClasse['moyenne_plus_faible'],
                    'appreciation_generale' => $this->appreciation($donneesEleve['moyenne_generale']),
                    'mention' => $this->mention($donneesEleve['moyenne_generale']),
                    'nom_eleve' => $nomEleve,
                    'nom_classe' => $nomClasse,
                    'genere_par' => Auth::id(),
                    'genere_le' => now(),
                    'statut' => 'calcule',
                ]
            );

            BulletinMatiere::where('bulletin_id', $bulletin->id)->delete();
            foreach ($donneesEleve['matieres'] as $matiereId => $detail) {
                $bulletinMatiere = BulletinMatiere::create([
                    'bulletin_id' => $bulletin->id,
                    'matiere_id' => $matiereId,
                    'moyenne' => $detail['moyenne'],
                    'coefficient' => $detail['coefficient'],
                    'rang' => $detail['rang'],
                    'moyenne_classe' => $detail['moyenne_classe'],
                    'appreciation' => $detail['appreciation'],
                ]);

                foreach ($detail['notes'] as $note) {
                    BulletinMatiereNote::create([
                        'bulletin_matiere_id' => $bulletinMatiere->id,
                        'titre_evaluation' => $note['titre'],
                        'date_evaluation' => $note['date'],
                        'valeur' => $note['valeur'],
                        'bareme' => $note['bareme'],
                        'note_sur_20' => $note['note_sur_20'],
                    ]);
                }
            }

            return $bulletin->fresh(['matieres.matiere', 'matieres.notes']);
        });
    }

    public function changerStatut(int $bulletinId, string $nouveauStatut): object
    {
        return DB::transaction(function () use ($bulletinId, $nouveauStatut): Bulletin {
            $bulletin = Bulletin::lockForUpdate()->findOrFail($bulletinId);
            $transitions = ['brouillon' => ['calcule'], 'calcule' => ['soumis'], 'soumis' => ['valide'], 'valide' => ['publie'], 'publie' => ['archive'], 'archive' => []];
            if (! in_array($nouveauStatut, $transitions[$bulletin->statut] ?? [], true)) {
                throw new RuntimeException("Transition {$bulletin->statut} → {$nouveauStatut} interdite.");
            }

            $acteur = Auth::id();
            $champs = ['statut' => $nouveauStatut];
            if ($nouveauStatut === 'soumis') {
                $champs += ['soumis_par' => $acteur, 'soumis_le' => now()];
            }
            if ($nouveauStatut === 'valide') {
                $champs += ['valide_par' => $acteur, 'valide_le' => now()];
            }
            if ($nouveauStatut === 'publie') {
                $champs += ['publie_par' => $acteur, 'publie_le' => now()];
            }
            $bulletin->update($champs);

            if ($nouveauStatut === 'publie') {
                BulletinVersion::create(['bulletin_id' => $bulletin->id, 'version' => $bulletin->version, 'donnees' => $bulletin->load('matieres.notes')->toArray(), 'cree_par' => $acteur]);
                $this->genererDocument($bulletin);
            }

            return $bulletin->fresh();
        });
    }

    private function decimales(): int
    {
        return max(0, min(4, (int) config('pedagogie.arrondi_decimales', 2)));
    }

    /**
     * Génère le rendu imprimable du bulletin (PDF si un moteur PDF est
     * disponible dans l'application hôte, sinon HTML en repli) et l'attache
     * via DocumentServiceContract. Dégradation silencieuse si aucune de ces
     * dépendances n'est disponible : le bulletin reste consultable dans
     * l'interface même sans document généré.
     */
    private function genererDocument(Bulletin $bulletin): void
    {
        if ($this->documentService === null) {
            return;
        }

        $donnees = [
            'bulletin' => $bulletin->loadMissing(['matieres.matiere', 'matieres.notes']),
            'genereLe' => Carbon::now(),
        ];
        $template = null;
        try {
            $template = $this->documentTemplates?->getTemplateActif('BUL-SEC');
        } catch (\Throwable) {
            // Compatibilité pendant une migration progressive des données.
        }
        if ($template) {
            $html = $this->documentTemplates->render($template, $donnees);
            $bulletin->update([
                'document_template_id' => $template->id,
                'document_template_version' => $template->version,
                'generated_at' => now(),
                'rendered_html' => $html,
            ]);
        } elseif (view()->exists('pedagogie::bulletin')) {
            $html = view('pedagogie::bulletin', $donnees)->render();
        } else {
            return;
        }

        $pdfDisponible = class_exists(Pdf::class);
        $extension = $pdfDisponible ? 'pdf' : 'html';
        $contenu = $pdfDisponible
            ? Pdf::loadHTML($html)->setPaper('a4')->output()
            : $html;

        $tmpPath = tempnam(sys_get_temp_dir(), 'bulletin_').'.'.$extension;
        file_put_contents($tmpPath, $contenu);

        try {
            $fichier = new UploadedFile(
                $tmpPath,
                "bulletin_eleve_{$bulletin->eleve_id}.{$extension}",
                $pdfDisponible ? 'application/pdf' : 'text/html',
                null,
                true
            );

            $document = $this->documentService->attacher($bulletin, $fichier, 'bulletin');
            if (isset($document->id)) {
                $bulletin->update(['document_id' => $document->id]);
            }
        } finally {
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }
}
