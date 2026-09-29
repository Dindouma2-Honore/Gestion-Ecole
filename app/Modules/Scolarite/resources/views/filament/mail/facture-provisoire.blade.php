@php
    $inscription = $facture->inscription;
    $eleve = $inscription?->eleve;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture provisoire {{ $facture->numero }}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color:#111827; font-size:14px;">
    <p>Bonjour {{ $facture->nom_destinataire ?? '' }},</p>

    <p>
        L'inscription de <strong>{{ trim(($eleve?->prenom ?? '').' '.($eleve?->nom ?? '')) }}</strong>
        en <strong>{{ $inscription?->classe?->nom ?? 'classe' }}</strong> a bien été enregistrée.
        Voici la facture provisoire correspondante — elle sera remplacée par une facture définitive
        dès confirmation de votre versement par la Comptabilité.
    </p>

    <table cellpadding="8" cellspacing="0" style="border-collapse:collapse; width:100%; margin:16px 0;">
        <tr>
            <td style="border:1px solid #d1d5db;">N° facture</td>
            <td style="border:1px solid #d1d5db;">{{ $facture->numero }}</td>
        </tr>
        <tr>
            <td style="border:1px solid #d1d5db;">Montant total à payer</td>
            <td style="border:1px solid #d1d5db;"><strong>{{ number_format((float) $facture->montant, 0, ',', ' ') }} XAF</strong></td>
        </tr>
        <tr>
            <td style="border:1px solid #d1d5db;">Date d'émission</td>
            <td style="border:1px solid #d1d5db;">{{ optional($facture->date_emission)->format('d/m/Y') }}</td>
        </tr>
    </table>

    <p>Merci de vous rapprocher de la Comptabilité pour effectuer le versement.</p>
</body>
</html>
