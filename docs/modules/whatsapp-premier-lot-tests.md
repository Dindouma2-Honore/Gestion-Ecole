# Premier lot de templates à tester

État vérifié le 14 septembre 2026 après renouvellement du jeton Meta.

Les 13 modèles sont créés et validés localement. Douze nouvelles soumissions ont été confirmées par Meta ; le modèle d’inscription déjà soumis a été conservé sans doublon. Les deux exemples PDF fictifs ont été téléversés avec succès. La synchronisation distante des 18 modèles du compte confirme que les 13 modèles de ce lot sont encore `PENDING`.

| Code | Identifiant Meta | État |
| --- | --- | --- |
| `parent_inscription_confirmee` | 2021320848566752 | READY / PENDING / SUBMITTED |
| `parent_absence_eleve` | 3528378953998377 | READY / PENDING / SUBMITTED |
| `parent_bulletin_disponible` | 1560690898442474 | READY / PENDING / SUBMITTED |
| `parent_bulletin_document` | 1857350322128138 | READY / PENDING / SUBMITTED |
| `parent_paiement_confirme` | 1866469944517287 | READY / PENDING / SUBMITTED |
| `parent_urgence_medicale` | 841934385607568 | READY / PENDING / SUBMITTED |
| `parent_rendez_vous` | 5454324941458726 | READY / PENDING / SUBMITTED |
| `teacher_schedule_changed` | 939082905942617 | READY / PENDING / SUBMITTED |
| `staff_leave_approved` | 28291425247209506 | READY / PENDING / SUBMITTED |
| `staff_leave_rejected` | 1105977015265587 | READY / PENDING / SUBMITTED |
| `parent_recu_document` | 1508525761041597 | READY / PENDING / SUBMITTED |
| `staff_payslip_available` | 2013310329354073 | READY / PENDING / SUBMITTED |
| `staff_salary_paid` | 1099355942545435 | READY / PENDING / SUBMITTED |

Source du lot : `app/Modules/Communication/Support/first-whatsapp-test-batch.json`. Les créations/mises à jour/soumissions utilisent WhatsAppTemplateService et son audit, sous le profil administrateur actif. Les trois créations nouvelles sont `parent_recu_document`, `staff_payslip_available` et `staff_salary_paid`. Les exemples des brouillons utilisent des identités fictives marquées TEST ; l’inscription déjà soumise n’a pas été modifiée.

Le précédent blocage HTTP 401 / 190 / 463 est résolu par le nouveau jeton enregistré dans `.env`, sans reproduction dans ce document. Le worker dédié `whatsapp-templates` a été arrêté proprement puis relancé avec le nouveau jeton.

Le contrôle de test a parcouru les 13 modèles : aucun n’étant APPROVED, aucun nouveau message n’a été créé ou envoyé. Cela ne constitue pas une validation de livraison des modèles personnalisés. Le transport avait été validé précédemment avec hello_world.

Pour reprendre : synchroniser, activer uniquement les modèles APPROVED puis lancer les tests autorisés au +237670097386 avec exemples fictifs. Utiliser les commandes `whatsapp:templates sync`, `activate` et `test` avec `--actor=1`, le code du modèle et `--confirm` pour l’envoi. Vérifier ensuite les accusés réels delivered. Ne pas soumettre à nouveau les modèles PENDING et ne pas relancer aveuglément un envoi au résultat incertain. Aucune activation ni aucun test automatique futur n’a été planifié.

Les événements scolaires et RH réels restent à raccorder séparément ; le présent lot prépare les modèles et leurs essais contrôlés.

## Contrôle du 14 septembre à 11:20 UTC

Le nouveau jeton utilisateur système fonctionne et les 18 modèles distants ont été synchronisés. Les 13 modèles du premier lot restent tous READY / PENDING ; aucun nouveau test n’a été envoyé. Le worker dédié whatsapp-templates, absent au contrôle, a été relancé avec ce jeton.

La réponse officielle de debug_token indique SYSTEM_USER et une expiration au 13 novembre 2026 à 09:16:06 UTC. Ce jeton est de longue durée, mais n’est pas sans expiration. Aucun secret n’est consigné dans ce document. La publication et le déploiement restent arrêtés conformément à la demande de l’utilisateur.
