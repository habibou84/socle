<?php

namespace Jibiat\Socle\Contract;

use InvalidArgumentException;

/**
 * Contrat de l'API publique de Jibiat (/api/public/v1) et de ses webhooks, tel que Jibiat Marchand l'utilise :
 * schémas des réponses (contract/v1/schemas), exemples réels (contract/v1/examples), statuts et événements.
 *
 * Jibiat vérifie que ses vraies réponses respectent les schémas ; Jibiat Marchand simule Jibiat avec les exemples.
 * Règle : on peut ajouter un champ, un statut ou un événement (version 1.x suivante) ; on ne retire ni ne renomme
 * rien sans une version majeure annoncée des deux côtés.
 */
final class Contract
{
    public const VERSION = '1.5';

    /** Statuts d'une course (champ « status ») */
    public const ORDER_STATUSES = [
        'pending', 'confirmed', 'rejected', 'pickup_assigned', 'pickup_in_progress', 'picked_up', 'at_hub',
        'delivery_assigned', 'out_for_delivery', 'delivered', 'delivery_failed', 'rescheduled',
        'return_assigned', 'returning', 'returned', 'cancelled', 'lost',
    ];

    /** Événements envoyés par webhook (en-tête X-Webhook-Event, champ « event ») */
    public const WEBHOOK_EVENTS = ['order.created', 'order.status_changed', 'order.incident', 'order.updated', 'payout.paid', 'stock.low'];

    /** Événements qui portent une course dans data.order */
    public const ORDER_EVENTS = ['order.created', 'order.status_changed', 'order.incident', 'order.updated'];

    /**
     * Remarques de l'e-commerçant quand il note le livreur (champ « ratings.merchant.tags », POST …/rating) :
     * les quatre premières sont des points forts, les autres des points à améliorer.
     */
    public const MERCHANT_RATING_TAGS = [
        'pickup_on_time' => 'Ramassage à l\'heure',
        'careful' => 'Colis bien traité',
        'good_with_customers' => 'Bon contact avec mes clients',
        'cash_returned' => 'Argent bien rendu',
        'pickup_late' => 'Ramassage en retard',
        'customer_mistreated' => 'Client mal traité',
        'unreachable' => 'Pas joignable',
    ];

    /** Statuts de l'abonnement Jibiat Marchand d'un marchand parrainé (champ « plan.status » de merchant-app) */
    public const MERCHANT_APP_PLAN_STATUSES = ['trialing', 'active', 'past_due', 'cancelled', 'expired', 'none'];

    public static function path(string $relative = ''): string
    {
        return dirname(__DIR__, 2).'/contract/v1'.($relative !== '' ? '/'.ltrim($relative, '/') : '');
    }

    /**
     * @return list<string>
     */
    public static function schemas(): array
    {
        return self::names('schemas');
    }

    /**
     * @return list<string>
     */
    public static function examples(): array
    {
        return self::names('examples');
    }

    /**
     * @return array<string, mixed>
     */
    public static function schema(string $name): array
    {
        return self::read("schemas/{$name}.json");
    }

    /**
     * Exemple réel (réponse ou webhook) ; les dates relatives « {{today}} », « {{today+N}} » et « {{now}} » sont
     * remplacées par les dates du jour.
     *
     * @return array<string, mixed>
     */
    public static function example(string $name): array
    {
        return self::read("examples/{$name}.json", dates: true);
    }

    /**
     * Schéma d'un exemple (son champ « $schema » en tête du fichier)
     */
    public static function schemaOf(string $example): string
    {
        $raw = json_decode((string) file_get_contents(self::path("examples/{$example}.json")), true);

        return (string) ($raw['$schema'] ?? throw new InvalidArgumentException("Exemple {$example} sans \$schema"));
    }

    /**
     * @return list<string> erreurs, vide si les données respectent le schéma
     */
    public static function validate(string $schema, mixed $data): array
    {
        return (new SchemaValidator(fn (string $name) => self::schema($name)))->validate($data, self::schema($schema));
    }

    /**
     * @return array<string, mixed>
     */
    private static function read(string $relative, bool $dates = false): array
    {
        $file = self::path($relative);
        if (! is_file($file)) {
            throw new InvalidArgumentException("Contrat : {$relative} introuvable");
        }
        $json = (string) file_get_contents($file);
        if ($dates) {
            $json = preg_replace_callback('/\{\{today([+-]\d+)?\}\}/', fn ($m) => date('Y-m-d', strtotime(($m[1] ?? '+0').' days')), $json);
            $json = str_replace('{{now}}', gmdate('Y-m-d\TH:i:s\Z'), $json);
        }
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        unset($data['$schema']);

        return $data;
    }

    /**
     * @return list<string>
     */
    private static function names(string $dir): array
    {
        $names = array_map(fn (string $f) => basename($f, '.json'), glob(self::path("{$dir}/*.json")) ?: []);
        sort($names);

        return $names;
    }
}
