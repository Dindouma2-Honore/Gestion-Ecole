<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

/**
 * Surface publique d'envoi de notification, tous canaux confondus. Le
 * module Socle ne l'implémente pas encore concrètement (dispatch SMS,
 * WhatsApp, email...) — les modules consommateurs (ex: Finances E.42)
 * s'appuient dès maintenant sur ce Contract en attendant cette
 * implémentation, comme Scolarité\EleveServiceInterface avant elle.
 */
interface NotificationServiceContract
{
    /**
     * Envoie une notification au sujet d'un élève sur le canal demandé.
     * La résolution du destinataire réel (parent, tuteur...) et du
     * contenu (voir ParametrageServiceContract::getTemplateNotification)
     * est à la charge de l'implémentation.
     */
    public function envoyer(string $canal, string $code, int $eleveId, array $donnees = []): void;
}
