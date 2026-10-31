<?php

declare(strict_types=1);

namespace CoreAdapter;

/**
 * La configuration du client, en valeurs simples — sans framework, sans
 * `env()`. L'adaptateur Laravel la construit depuis `config/core.php` ; une
 * application qui n'est pas en Laravel la construit elle-même.
 */
final readonly class CoreConfig
{
    /**
     * @param string $baseUrl l'adresse du Core, sans barre finale : `https://core.example.com`
     * @param string $apiKey la clé du projet (`pk_…`)
     * @param int $timeout le délai total d'une requête, en secondes
     * @param int $connectTimeout le délai de connexion, en secondes
     * @param string|null $webhookSecret le secret de signature des événements (`whsec_…`)
     * @param string|null $whatsappSession la session WhatsApp liée au projet
     * @param int $webhookTolerance la tolérance d'horloge des événements, en secondes
     * @param int $entitlementsTtl la durée de cache des droits, en secondes (`0` désactive)
     */
    public function __construct(
        public string $baseUrl,
        public string $apiKey,
        public int $timeout = 30,
        public int $connectTimeout = 5,
        public ?string $webhookSecret = null,
        public ?string $whatsappSession = null,
        public int $webhookTolerance = 300,
        public int $entitlementsTtl = 60,
    ) {}

    /** L'URL absolue d'un point d'entrée du Core. */
    public function endpoint(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/v1/'.ltrim($path, '/');
    }
}
