<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin scolaire</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1f2937; }
        .entete { text-align: center; margin-bottom: 14px; }
        .entete h1 { font-size: 18px; margin: 0 0 2px; text-transform: uppercase; }
        .entete .sous-titre { color: #6b7280; font-size: 12px; }

        .infos-eleve { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .infos-eleve td { padding: 3px 6px; font-size: 12px; }
        .infos-eleve td.label { color: #6b7280; width: 130px; }
        .infos-eleve td.valeur { font-weight: bold; }

        table.matieres { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.matieres th, table.matieres td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; vertical-align: top; }
        table.matieres th { background: #f3f4f6; font-size: 10px; text-transform: uppercase; }
        table.matieres td.chiffre { text-align: center; white-space: nowrap; }

        .notes-detail { margin: 0; padding: 0; list-style: none; font-size: 9.5px; color: #4b5563; }
        .notes-detail li { padding: 1px 0; }

        tr.ligne-totaux td { font-weight: bold; background: #f9fafb; border-top: 2px solid #9ca3af; }

        .synthese { width: 100%; margin-top: 16px; border-collapse: collapse; }
        .synthese td { border: 1px solid #d1d5db; padding: 6px 8px; font-size: 11px; }
        .synthese td.label { color: #6b7280; }
        .synthese td.valeur { font-weight: bold; }

        .mention { margin-top: 14px; padding: 8px 12px; border: 1px solid #1f2937; text-align: center; font-weight: bold; font-size: 13px; }

        .signatures { width: 100%; margin-top: 40px; }
        .signatures td { width: 33%; text-align: center; font-size: 10px; color: #6b7280; }
        .signatures .ligne { margin-top: 40px; border-top: 1px solid #9ca3af; }

        .footer { margin-top: 20px; font-size: 9px; color: #9ca3af; text-align: right; }
    </style>
</head>
<body>
    <div class="entete">
        <h1>Bulletin scolaire</h1>
        <div class="sous-titre">
            Année scolaire #{{ $bulletin->annee_scolaire_id }}
            @if($bulletin->periode_id) — Période #{{ $bulletin->periode_id }} @else — Année complète @endif
        </div>
    </div>

    <table class="infos-eleve">
        <tr>
            <td class="label">Élève</td>
            <td class="valeur">{{ $bulletin->nom_eleve ?? ('#'.$bulletin->eleve_id) }}</td>
            <td class="label">Classe</td>
            <td class="valeur">{{ $bulletin->nom_classe ?? ('#'.$bulletin->classe_id) }}</td>
        </tr>
        <tr>
            <td class="label">Effectif de la classe</td>
            <td class="valeur">{{ $bulletin->effectif_classe }}</td>
            <td class="label">Rang général</td>
            <td class="valeur">{{ $bulletin->rang }} / {{ $bulletin->effectif_classe }}</td>
        </tr>
    </table>

    <table class="matieres">
        <thead>
            <tr>
                <th style="width: 20%;">Matière</th>
                <th style="width: 32%;">Notes obtenues</th>
                <th>Coeff.</th>
                <th>Moyenne /20</th>
                <th>Moy. × Coeff.</th>
                <th>Rang</th>
                <th>Moy. classe</th>
                <th style="width: 14%;">Appréciation</th>
            </tr>
        </thead>
        <tbody>
            @php $totalCoefficients = 0; $totalPoints = 0; @endphp
            @foreach($bulletin->matieres as $ligne)
                @php
                    $totalCoefficients += $ligne->coefficient;
                    $totalPoints += $ligne->moyenne * $ligne->coefficient;
                @endphp
                <tr>
                    <td>{{ $ligne->matiere->nom ?? ('Matière #'.$ligne->matiere_id) }}</td>
                    <td>
                        <ul class="notes-detail">
                            @forelse($ligne->notes as $note)
                                <li>
                                    {{ $note->titre_evaluation }}
                                    @if($note->date_evaluation) ({{ $note->date_evaluation->format('d/m/Y') }}) @endif
                                    : {{ rtrim(rtrim(number_format($note->valeur, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($note->bareme, 2), '0'), '.') }}
                                </li>
                            @empty
                                <li>—</li>
                            @endforelse
                        </ul>
                    </td>
                    <td class="chiffre">{{ rtrim(rtrim(number_format($ligne->coefficient, 2), '0'), '.') }}</td>
                    <td class="chiffre">{{ number_format($ligne->moyenne, 2) }}</td>
                    <td class="chiffre">{{ number_format($ligne->moyenne * $ligne->coefficient, 2) }}</td>
                    <td class="chiffre">{{ $ligne->rang ?? '—' }}</td>
                    <td class="chiffre">{{ $ligne->moyenne_classe !== null ? number_format($ligne->moyenne_classe, 2) : '—' }}</td>
                    <td>{{ $ligne->appreciation }}</td>
                </tr>
            @endforeach
            <tr class="ligne-totaux">
                <td colspan="2">Total</td>
                <td class="chiffre">{{ rtrim(rtrim(number_format($totalCoefficients, 2), '0'), '.') }}</td>
                <td colspan="2" class="chiffre">{{ number_format($totalPoints, 2) }} points</td>
                <td colspan="3"></td>
            </tr>
        </tbody>
    </table>

    <table class="synthese">
        <tr>
            <td class="label">Moyenne générale de l'élève</td>
            <td class="valeur">{{ number_format($bulletin->moyenne_generale, 2) }} / 20</td>
            <td class="label">Rang</td>
            <td class="valeur">{{ $bulletin->rang }} / {{ $bulletin->effectif_classe }}</td>
        </tr>
        <tr>
            <td class="label">Moyenne de la classe</td>
            <td class="valeur">{{ $bulletin->moyenne_classe_generale !== null ? number_format($bulletin->moyenne_classe_generale, 2) : '—' }} / 20</td>
            <td class="label">Appréciation générale</td>
            <td class="valeur">{{ $bulletin->appreciation_generale }}</td>
        </tr>
        <tr>
            <td class="label">Moyenne la plus forte</td>
            <td class="valeur">{{ $bulletin->moyenne_plus_forte !== null ? number_format($bulletin->moyenne_plus_forte, 2) : '—' }} / 20</td>
            <td class="label">Moyenne la plus faible</td>
            <td class="valeur">{{ $bulletin->moyenne_plus_faible !== null ? number_format($bulletin->moyenne_plus_faible, 2) : '—' }} / 20</td>
        </tr>
    </table>

    <div class="mention">{{ $bulletin->mention }}</div>

    <table class="signatures">
        <tr>
            <td>Le professeur principal<div class="ligne"></div></td>
            <td>Le parent / tuteur<div class="ligne"></div></td>
            <td>Le Directeur / La Directrice<div class="ligne"></div></td>
        </tr>
    </table>

    <div class="footer">Bulletin généré le {{ $genereLe->format('d/m/Y à H:i') }}.</div>
</body>
</html>
