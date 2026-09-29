<!DOCTYPE html>
<html lang="fr"><body style="font-family:Arial,sans-serif;color:#10264b">
<div style="background:#071a3a;color:white;padding:24px"><strong style="font-size:20px">AMBASSADORS EDUCATIONAL COMPLEX</strong><br><span style="color:#d7aa35">Faith · Vision · Discipline</span></div>
<h1>Facture provisoire de préinscription</h1>
<p>Référence : <strong>{{ $facture->reference }}</strong></p>
<p>Bonjour {{ $facture->parent_nom }}, la préinscription de {{ $facture->eleve_nom }} est enregistrée et reste en attente du versement.</p>
<table style="width:100%;border-collapse:collapse">
@foreach($facture->lignes as $ligne)
<tr><td style="padding:10px;border:1px solid #ddd">{{ $ligne->libelle }} {{ $ligne->obligatoire ? '(obligatoire)' : '(optionnel)' }}</td><td style="padding:10px;border:1px solid #ddd;text-align:right">{{ number_format((float) $ligne->montant, 0, ',', ' ') }} FCFA</td></tr>
@endforeach
@php($parGroupe = $facture->lignes->groupBy(fn ($ligne) => $ligne->groupe?->code ?? (in_array($ligne->type_frais, ['inscription', 'scolarite'], true) ? 'scolarite' : 'autres'))->map->sum('montant'))
<tr><td style="padding:10px;border:1px solid #ddd"><strong>Frais de scolarité</strong></td><td style="padding:10px;border:1px solid #ddd;text-align:right">{{ number_format((float)($parGroupe['scolarite'] ?? 0), 0, ',', ' ') }} FCFA</td></tr>
<tr><td style="padding:10px;border:1px solid #ddd"><strong>Autres frais</strong></td><td style="padding:10px;border:1px solid #ddd;text-align:right">{{ number_format((float)($parGroupe['autres'] ?? 0), 0, ',', ' ') }} FCFA</td></tr>
<tr><th style="padding:12px;border:1px solid #ddd;text-align:left">Total à verser</th><th style="padding:12px;border:1px solid #ddd;text-align:right">{{ number_format((float) $facture->montant_total, 0, ',', ' ') }} FCFA</th></tr>
</table>
<p style="padding:14px;background:#fff6df;color:#8a5718"><strong>En attente de versement.</strong> L'inscription ne sera définitive qu'après contrôle du paiement par la Comptable.</p>
</body></html>
