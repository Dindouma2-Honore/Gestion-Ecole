<!DOCTYPE html>
<html lang="fr"><body style="font-family:Arial,sans-serif;color:#10264b">
<div style="background:#071a3a;color:white;padding:24px"><strong style="font-size:20px">AMBASSADORS EDUCATIONAL COMPLEX</strong></div>
<h1>Inscription confirmée</h1>
<p>Bonjour {{ $facture->parent_nom }},</p>
<p>Le versement de <strong>{{ number_format((float) $facture->montant_total, 0, ',', ' ') }} FCFA</strong> a été contrôlé par la Comptable.</p>
<p>L'inscription de <strong>{{ $facture->eleve_nom }}</strong> est maintenant définitivement validée.</p>
<p>Référence : {{ $facture->reference }}</p>
<p>Votre reçu définitif officiel est joint à ce message au format PDF.</p>
</body></html>
