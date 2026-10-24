# habibou84/socle

Briques techniques communes à **Jibiat** (`habibou84/deliverynew`) et **Jibiat Marchand** (`habibou84/ecommerce`),
sans logique métier (voir `docs/PLAN.md` §4.5) :

| Classe | Rôle |
|---|---|
| `Money` | Affichage des montants (`12 500 F`) |
| `PhoneNumber` | Normalisation E.164, affichage, mobiles ivoiriens |
| `Rules\PhoneNumber` | Règle de validation Laravel |
| `Sms\SmsGateway`, `Sms\LogSmsGateway`, `Sms\TwilioSmsGateway` | Envoi de SMS (pilote `log` en développement) |
| `Webhooks\WebhookSignature` | Signature et vérification HMAC des webhooks (`X-Webhook-Signature`) |
| `Contract\Contract` | Contrat de l'API publique de Jibiat et de ses webhooks (schémas, exemples, statuts, événements) |

Installation (dépôt public, versions étiquetées `vX.Y.Z`) :

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/habibou84/socle" }],
"require": { "habibou84/socle": "^1.0@dev" }
```

La branche `main` porte la version 1.3.x (alias Composer `1.3.x-dev`) ; le `composer.lock` de chaque application
fixe le commit exact. Toute modification passe par ce dépôt, puis `composer update habibou84/socle` dans chaque
application. Quand des étiquettes `vX.Y.Z` seront publiées, les applications pourront exiger `^1.0`. La signature des webhooks fait partie du contrat entre les deux applications : ne la changez qu'avec
une nouvelle version majeure.

Configuration (`config/services.php` de l'application) :

```php
'sms' => [
    'driver' => env('SMS_DRIVER', 'log'), // log | twilio | none
    'sender' => env('SMS_SENDER', 'Jibiat'),
    'twilio' => ['sid' => env('TWILIO_SID'), 'token' => env('TWILIO_TOKEN'), 'from' => env('TWILIO_FROM')],
],
```

## Contrat entre Jibiat et Jibiat Marchand

`contract/v1/` décrit ce que Jibiat Marchand lit de l'API publique de Jibiat (`/api/public/v1`) et de ses webhooks :

- `schemas/` : un schéma JSON par réponse ou webhook (`order`, `order-response`, `order-list`, `webhook`,
  `order-webhook`, `payout-webhook`, `payout`, `payout-list`, `me`, `zones`, `delivery-day`, `quote`,
  `webhook-created`, `error`, `directory`, `connect-token`). La course porte `ratings` depuis la 1.3. Seuls les champs réellement lus sont obligatoires ; les champs en plus sont permis.
- `examples/` : des réponses et webhooks réels, chacun avec son schéma (`"$schema": "order-response"`). Les dates
  relatives s'écrivent `{{today}}`, `{{today+1}}`, `{{now}}`.

```php
use Jibiat\Socle\Contract\Contract;

Contract::validate('order-response', $response->json()); // [] si conforme, sinon la liste des écarts
Contract::example('order-failed-needs-decision');      // réponse simulée, dates du jour
Contract::ORDER_STATUSES; Contract::WEBHOOK_EVENTS;
```

Les deux côtés le testent :

- **Jibiat** (fournisseur) appelle ses vraies routes et capture ses vrais webhooks, puis les valide contre les
  schémas ; ses énumérations `OrderStatus` et `WebhookEvent` doivent égaler `Contract::ORDER_STATUSES` et
  `Contract::WEBHOOK_EVENTS`.
- **Jibiat Marchand** (client) simule Jibiat avec les exemples et vérifie qu'il les comprend tous (chaque statut
  a son équivalent, dates, décision attendue, retour, encaissement, photos).

### Annuaire et connexion en un clic (1.2)

**Annuaire** : `GET https://jibiat.com/api/public/directory` (sans clé), schéma `directory`. Seules les entreprises
qui l'ont choisi y figurent. `stats` porte sur les 90 derniers jours ; `success_rate` (0 à 1) et
`average_delivery_hours` valent `null` sous 20 courses. `accepts` : `signup` (le marchand ouvre son compte seul) ou
`request` (sa demande est validée par l'entreprise).

**Connexion en un clic** (autorisation de type OAuth 2 avec PKCE, sans secret) :

1. Jibiat Marchand ouvre `connect_url` avec `client_id=jibiat-marchand`, `redirect_uri` (adresse de retour, hôte
   déclaré chez Jibiat), `state`, `code_challenge` (SHA-256 en base64url du `code_verifier`),
   `code_challenge_method=S256`, et pour préremplir : `business_name`, `phone`, `city`.
2. Le marchand se connecte, ouvre son compte ou demande à devenir client, puis autorise l'accès.
3. Retour sur `redirect_uri` avec `code` et `state` (valable 10 minutes, une seule fois) ; sinon `error` :
   `access_denied` (refusé) ou `request_pending` (demande envoyée, l'entreprise doit la valider ; le marchand
   recommence ensuite).
4. `POST {url}/api/public/connect/token` avec `grant_type=authorization_code`, `client_id`, `code`,
   `code_verifier`, `redirect_uri` : réponse `connect-token` (clé `lv_…` avec ses portées, dont `webhooks:write`).
   Erreur : `400` avec `error` = `invalid_grant` ou `invalid_request`.

### Notation du livreur (1.3)

La course porte `ratings` (absent avec une version plus ancienne de Jibiat : rien à afficher) :

- `can_rate` : l'e-commerçant peut noter le livreur (course livrée, pas encore notée, 7 jours au plus).
- `merchant` : sa note (`score` de 1 à 5, `tags` parmi `Contract::MERCHANT_RATING_TAGS`, `comment`, `rated_at`), ou `null`.
- `recipient` : la note laissée par son client (`score`, `comment`, `rated_at`), ou `null`. Anonyme : ni nom ni numéro.

Noter : `POST /api/public/v1/orders/{tracking_code}/rating` (portée `orders:write`) avec `score` (1 à 5), `tags`
(facultatif) et `comment` (facultatif, 500 caractères) ; réponse `order-response`. `422` si la course n'est pas
livrée, déjà notée ou livrée depuis plus de 7 jours.

**Règle de version** (`Contract::VERSION`) :

- *Ajouter* un champ, un statut, un événement ou un exemple : version 1.x suivante. Jibiat Marchand doit d'abord
  savoir le recevoir (un statut inconnu ne doit rien casser), puis Jibiat l'envoie.
- *Retirer, renommer ou changer le type* d'un champ, retirer un statut ou un événement : nouvelle version majeure
  (`contract/v2`), annoncée et déployée des deux côtés ; Jibiat garde `v1` tant que Jibiat Marchand l'utilise.
- Après chaque changement : `composer update habibou84/socle` dans les deux applications, leurs tests du contrat
  doivent passer avant la mise en production.
