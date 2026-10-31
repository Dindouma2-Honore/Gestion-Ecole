<?php

declare(strict_types=1);

namespace App\Modules\Pedagogie\Services;

use App\Modules\Pedagogie\Exceptions\DocumentAnalyseException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiAnalysisService
{
    public function __construct(
        protected ?ProgramDocumentAnalyzer $localAnalyzer = null
    ) {
        $this->localAnalyzer ??= app(ProgramDocumentAnalyzer::class);
    }

    /**
     * Analyse le texte du programme via le fournisseur IA configuré.
     *
     * @param  array<string, mixed>  $contexte  Informations additionnelles (matière, niveau, cycle)
     * @return array{
     *     success: bool,
     *     data: array<string, mixed>,
     *     raw_json: string,
     *     provider: string,
     *     model: string,
     *     tokens_used: ?int,
     *     error: ?string
     * }
     */
    public function analyserProgrammeTexte(string $texteExtrait, array $contexte = []): array
    {
        $provider = (string) config('ai.provider', 'openai');
        $apiKey = (string) config('ai.api_key', '');
        $model = (string) config('ai.model', 'gpt-4o-mini');

        Log::info("AiAnalysisService : Début de l'analyse du document", [
            'provider' => $provider,
            'model' => $model,
            'longueur_texte' => mb_strlen($texteExtrait),
            'has_api_key' => ! empty($apiKey),
        ]);

        // Si aucune clé API n'est configurée ou mode local demandé, basculer sur l'analyseur heuristique
        if (empty($apiKey) || $provider === 'local') {
            Log::info("AiAnalysisService : Aucune clé API configurée ou mode local actif, utilisation du moteur d'analyse déterministe local.");

            return $this->analyserViaMoteurLocal($texteExtrait, $contexte);
        }

        try {
            $resultat = match ($provider) {
                'gemini' => $this->appelerGemini($texteExtrait, $apiKey, $model, $contexte),
                'openai' => $this->appelerOpenAi($texteExtrait, $apiKey, $model, $contexte),
                default => $this->appelerOpenAi($texteExtrait, $apiKey, $model, $contexte),
            };

            $donneesValidees = $this->validerEtNormaliserStructure($resultat['data'], $texteExtrait, $contexte);

            return [
                'success' => true,
                'data' => $donneesValidees,
                'raw_json' => $resultat['raw_json'],
                'provider' => $provider,
                'model' => $model,
                'tokens_used' => $resultat['tokens_used'] ?? null,
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::warning("AiAnalysisService : Échec de l'appel IA ({$provider}), repli vers l'analyseur local", [
                'erreur' => $e->getMessage(),
            ]);

            // Fallback robuste : si l'API IA a échoué (réseau, quota, etc.), utiliser l'analyseur local
            $fallback = $this->analyserViaMoteurLocal($texteExtrait, $contexte);
            $fallback['error'] = 'Repli local suite à indisponibilité IA : '.$e->getMessage();

            return $fallback;
        }
    }

    /**
     * Appel à l'API OpenAI Chat Completions avec mode JSON strict.
     */
    protected function appelerOpenAi(string $texte, string $apiKey, string $model, array $contexte): array
    {
        $baseUrl = (string) config('ai.openai.base_url', 'https://api.openai.com/v1');
        $timeout = (int) config('ai.openai.timeout', 60);

        $promptSystem = $this->genererPromptSystem($contexte);
        $promptUtilisateur = $this->genererPromptUtilisateur($texte);

        $response = Http::withToken($apiKey)
            ->timeout($timeout)
            ->post(rtrim($baseUrl, '/').'/chat/completions', [
                'model' => $model ?: 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $promptSystem],
                    ['role' => 'user', 'content' => $promptUtilisateur],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => (float) config('ai.temperature', 0.2),
            ]);

        if (! $response->successful()) {
            throw new DocumentAnalyseException('Erreur API OpenAI ('.$response->status().') : '.$response->body());
        }

        $json = $response->json();
        $contenu = $json['choices'][0]['message']['content'] ?? '';
        $tokens = $json['usage']['total_tokens'] ?? null;

        $decoded = json_decode($contenu, true);
        if (! is_array($decoded)) {
            throw new DocumentAnalyseException("La réponse de l'IA n'est pas un JSON valide.");
        }

        return [
            'data' => $decoded,
            'raw_json' => $contenu,
            'tokens_used' => $tokens,
        ];
    }

    /**
     * Appel à l'API Google Gemini avec mode JSON strict.
     */
    protected function appelerGemini(string $texte, string $apiKey, string $model, array $contexte): array
    {
        $baseUrl = (string) config('ai.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $timeout = (int) config('ai.gemini.timeout', 60);
        $geminiModel = $model ?: 'gemini-1.5-flash';

        $promptSystem = $this->genererPromptSystem($contexte);
        $promptUtilisateur = $this->genererPromptUtilisateur($texte);

        $url = rtrim($baseUrl, '/')."/models/{$geminiModel}:generateContent?key={$apiKey}";

        $response = Http::timeout($timeout)
            ->post($url, [
                'systemInstruction' => [
                    'parts' => [['text' => $promptSystem]],
                ],
                'contents' => [
                    [
                        'parts' => [['text' => $promptUtilisateur]],
                    ],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => (float) config('ai.temperature', 0.2),
                ],
            ]);

        if (! $response->successful()) {
            throw new DocumentAnalyseException('Erreur API Gemini ('.$response->status().') : '.$response->body());
        }

        $json = $response->json();
        $contenu = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $tokens = $json['usageMetadata']['totalTokenCount'] ?? null;

        $decoded = json_decode($contenu, true);
        if (! is_array($decoded)) {
            throw new DocumentAnalyseException("La réponse de Gemini n'est pas un JSON valide.");
        }

        return [
            'data' => $decoded,
            'raw_json' => $contenu,
            'tokens_used' => $tokens,
        ];
    }

    /**
     * Moteur d'analyse local intelligent (sans dépendance externe).
     */
    protected function analyserViaMoteurLocal(string $texte, array $contexte): array
    {
        $cycle = $contexte['cycle'] ?? null;
        $structure = $this->localAnalyzer->interpreterTexte($texte, $cycle);

        $modules = [];
        $totalHeures = 0.0;
        $doublons = [];
        $titresLecons = [];

        foreach ($structure as $chap) {
            $leconsChap = [];
            $heuresChap = (float) ($chap['volume_horaire_prevu'] ?? 0);

            foreach ($chap['lecons'] ?? [] as $lec) {
                $titreLec = $lec['titre'];
                if (in_array(mb_strtolower($titreLec), $titresLecons, true)) {
                    $doublons[] = "Titre de notion récurrent : \"{$titreLec}\"";
                }
                $titresLecons[] = mb_strtolower($titreLec);

                $vol = (float) ($lec['volume_horaire_prevu'] ?? 1.0);
                $totalHeures += $vol;

                $leconsChap[] = [
                    'titre' => $titreLec,
                    'ordre' => (int) ($lec['ordre'] ?? count($leconsChap) + 1),
                    'volume_horaire' => $vol,
                    'objectifs' => $lec['objectifs'] ?? null,
                    'type_element' => $lec['type_element'] ?? 'lecon',
                ];
            }

            $modules[] = [
                'titre' => $chap['titre'],
                'ordre' => (int) ($chap['ordre'] ?? count($modules) + 1),
                'volume_horaire' => $heuresChap ?: array_sum(array_column($leconsChap, 'volume_horaire')),
                'objectif' => $chap['objectif'] ?? null,
                'lecons' => $leconsChap,
            ];
        }

        $matiereNom = $contexte['matiere'] ?? 'Matière';
        $niveauNom = $contexte['niveau'] ?? 'Général';

        $data = [
            'program_name' => "Programme {$matiereNom} - {$niveauNom}",
            'description' => "Programme pédagogique structuré en ".count($modules)." chapitres comprenant ".count($titresLecons)." notions clés pour un volume d'environ {$totalHeures} heures.",
            'objectifs' => [
                "Acquérir les compétences fondamentales en {$matiereNom}",
                "Maîtriser les notions clés du niveau {$niveauNom}",
                'Appliquer les méthodes et raisonnements aux évaluations périodiques',
            ],
            'duration' => $totalHeures > 0 ? (int) ceil($totalHeures) : 60,
            'target_audience' => "Élèves de classe {$niveauNom}",
            'prerequisites' => [
                'Prérequis du niveau précédent validés',
            ],
            'modules' => $modules,
            'skills' => [
                "Capacité d'analyse et de synthèse en {$matiereNom}",
                'Autonomie dans la résolution d’exercices pratiques',
            ],
            'teaching_methods' => [
                'Cours magistral interactif',
                'Travaux dirigés et exercices d’application',
                'Activités d’évaluation formative',
            ],
            'evaluation_methods' => [
                'Contrôles continus et devoirs surveillés',
                'Interrogations orales et participation',
                'Évaluations de fin de période',
            ],
            'career_opportunities' => [
                'Poursuite dans la classe supérieure',
            ],
            'level' => (string) $niveauNom,
            'missing_information' => empty($structure) ? ['Aucune leçon distincte identifiée'] : [],
            'inconsistencies' => [],
            'duplicates' => array_values(array_unique($doublons)),
            'summary' => "Ce syllabus a été analysé et découpé avec succès en ".count($modules)." chapitres et ".count($titresLecons)." leçons prêtes à être intégrées au calendrier pédagogique.",
        ];

        return [
            'success' => true,
            'data' => $data,
            'raw_json' => (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'provider' => 'local_rules',
            'model' => 'deterministic-curriculum-analyzer',
            'tokens_used' => null,
            'error' => null,
        ];
    }

    /**
     * Valide et harmonise la structure retournée par l'IA.
     */
    protected function validerEtNormaliserStructure(array $data, string $texte, array $contexte): array
    {
        $data['program_name'] = (string) ($data['program_name'] ?? $contexte['matiere'] ?? 'Programme Pédagogique');
        $data['description'] = (string) ($data['description'] ?? '');
        $data['objectifs'] = is_array($data['objectifs'] ?? null) ? $data['objectifs'] : [];
        $data['prerequisites'] = is_array($data['prerequisites'] ?? null) ? $data['prerequisites'] : [];
        $data['duration'] = (int) ($data['duration'] ?? 60);
        $data['target_audience'] = (string) ($data['target_audience'] ?? '');
        $data['skills'] = is_array($data['skills'] ?? null) ? $data['skills'] : [];
        $data['teaching_methods'] = is_array($data['teaching_methods'] ?? null) ? $data['teaching_methods'] : [];
        $data['evaluation_methods'] = is_array($data['evaluation_methods'] ?? null) ? $data['evaluation_methods'] : [];
        $data['career_opportunities'] = is_array($data['career_opportunities'] ?? null) ? $data['career_opportunities'] : [];
        $data['level'] = (string) ($data['level'] ?? $contexte['niveau'] ?? '');
        $data['missing_information'] = is_array($data['missing_information'] ?? null) ? $data['missing_information'] : [];
        $data['inconsistencies'] = is_array($data['inconsistencies'] ?? null) ? $data['inconsistencies'] : [];
        $data['duplicates'] = is_array($data['duplicates'] ?? null) ? $data['duplicates'] : [];
        $data['summary'] = (string) ($data['summary'] ?? $data['description'] ?? '');

        // Normalisation des modules / chapitres
        $modules = [];
        $modulesRaw = $data['modules'] ?? $data['chapitres'] ?? [];
        if (is_array($modulesRaw)) {
            foreach ($modulesRaw as $indexMod => $mod) {
                if (! is_array($mod) || empty($mod['titre'])) {
                    continue;
                }

                $lecons = [];
                $leconsRaw = $mod['lecons'] ?? $mod['notions'] ?? $mod['parties'] ?? [];
                if (is_array($leconsRaw)) {
                    foreach ($leconsRaw as $indexLec => $lec) {
                        $titreLec = is_array($lec) ? ($lec['titre'] ?? '') : (string) $lec;
                        if (empty($titreLec)) {
                            continue;
                        }

                        $lecons[] = [
                            'titre' => $titreLec,
                            'ordre' => is_array($lec) ? (int) ($lec['ordre'] ?? ($indexLec + 1)) : ($indexLec + 1),
                            'volume_horaire' => is_array($lec) ? (float) ($lec['volume_horaire'] ?? 1.0) : 1.0,
                            'objectifs' => is_array($lec) ? ($lec['objectifs'] ?? null) : null,
                            'type_element' => is_array($lec) ? ($lec['type_element'] ?? 'lecon') : 'lecon',
                        ];
                    }
                }

                $modules[] = [
                    'titre' => (string) $mod['titre'],
                    'ordre' => (int) ($mod['ordre'] ?? ($indexMod + 1)),
                    'volume_horaire' => (float) ($mod['volume_horaire'] ?? array_sum(array_column($lecons, 'volume_horaire'))),
                    'objectif' => isset($mod['objectif']) ? (string) $mod['objectif'] : null,
                    'lecons' => $lecons,
                ];
            }
        }

        // Si aucun module n'a été extrait par l'IA, utiliser le parseur local en renfort
        if (empty($modules)) {
            $local = $this->analyserViaMoteurLocal($texte, $contexte);
            $modules = $local['data']['modules'] ?? [];
        }

        $data['modules'] = $modules;

        return $data;
    }

    protected function genererPromptSystem(array $contexte): string
    {
        $matiere = $contexte['matiere'] ?? 'Général';
        $cycle = $contexte['cycle'] ?? 'Secondaire';
        $niveau = $contexte['niveau'] ?? 'Indéterminé';

        return <<<PROMPT
Tu es un inspecteur pédagogique senior et expert en ingénierie de curriculum scolaire pour le système éducatif international (Ambassadors ERP).
Tu analyses des documents de programmes pédagogiques annuels pour la matière "{$matiere}", niveau "{$niveau}", cycle "{$cycle}".

Ton rôle est d'analyser le document fourni et de produire STRICTEMENT un objet JSON valide, sans texte additionnel, sans markdown avant ou après, conforme au schéma suivant :

{
    "program_name": "Titre officiel du programme",
    "description": "Description générale concise du cours",
    "objectifs": ["Objectif global 1", "Objectif global 2"],
    "duration": 120, // durée globale en heures (nombre entier)
    "target_audience": "Public cible (ex: Élèves de 6e)",
    "prerequisites": ["Prérequis nécessaire 1"],
    "modules": [
        {
            "titre": "Chapitre 1 : ...",
            "ordre": 1,
            "volume_horaire": 15.0,
            "objectif": "Objectif pédagogique du chapitre",
            "lecons": [
                {
                    "titre": "Leçon 1.1 : ...",
                    "ordre": 1,
                    "volume_horaire": 2.0,
                    "objectifs": "Compétence visée",
                    "type_element": "lecon" // ou "tp", "activite", "evaluation"
                }
            ]
        }
    ],
    "skills": ["Compétence 1", "Compétence 2"],
    "teaching_methods": ["Méthode 1"],
    "evaluation_methods": ["Modalité d'évaluation 1"],
    "career_opportunities": ["Débouché ou utilité future"],
    "level": "Niveau scolaire",
    "missing_information": ["Informations manquantes importantes relevées dans le document"],
    "inconsistencies": ["Incohérences ou anomalies de volume horaire/progression détectées"],
    "duplicates": ["Doublons ou redondances constatées dans les titres de chapitres ou leçons"],
    "summary": "Synthèse globale de la cohérence et pertinence du programme"
}

RÈGLES IMPORTANTES :
1. Découpe TOUTES les notions et leçons identifiées dans le document sous les chapitres correspondants.
2. Évalue rigoureusement les volumes horaires et la faisabilité sur une année scolaire.
3. Remplis minutieusement "missing_information", "inconsistencies" et "duplicates" pour aider l'équipe pédagogique.
PROMPT;
    }

    protected function genererPromptUtilisateur(string $texte): string
    {
        // Limiter la taille du texte envoyé si nécessaire (ex: 25 000 caractères max)
        $texteTronque = mb_substr($texte, 0, 25000);

        return "Voici le document du programme annuel à analyser :\n\n".$texteTronque;
    }
}
