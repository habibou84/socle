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
| `Places\Places` | Liste commune des lieux (villes, communes, quartiers), codes fixes, recherche dans un texte |
| `Contract\Contract` | Contrat de l'API publique de Jibiat et de ses webhooks (schémas, exemples, statuts, événements) |

Installation (dépôt public, versions étiquetées `vX.Y.Z`) :

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/habibou84/socle" }],
"require": { "habibou84/socle": "^1.0@dev" }
```

La branche `main` porte la version 1.7.x (alias Composer `1.7.x-dev`) ; le `composer.lock` de chaque application
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
  `webhook-created`, `error`, `directory`, `connect-token`, `merchant-app`, `premium-card-request`, `premium-card`, `premium-card-list`). La course porte `ratings` depuis la 1.3, `label_url` depuis la 1.5. Seuls les champs réellement lus sont obligatoires ; les champs en plus sont permis.
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

### Lieux communs (1.7)

Les deux applications utilisent la même liste de lieux, `Jibiat\Socle\Places\Places` :

```php
use Jibiat\Socle\Places\Places;

Places::all();                     // [['code' => 'abidjan-cocody-angre', 'type' => 'neighborhood', 'name' => 'Angré', 'parent' => 'abidjan-cocody', 'aliases' => []], …]
Places::label('abidjan-cocody-angre');   // « Angré, Cocody »
Places::lineage('abidjan-cocody-angre'); // ['abidjan-cocody-angre', 'abidjan-cocody', 'abidjan']
Places::inText('yop siporex');      // le quartier Siporex de Yopougon (le plus précis d'abord)
Places::named('riv 3');             // Riviera 3, par son nom ou une variante exacte
```

Un code ne change jamais. Chez Jibiat, chaque zone d'une entreprise porte le code de son lieu (`place` dans
`GET /zones`, `null` pour une zone propre à l'entreprise). Pour créer une course (`POST /orders`) ou demander un
devis (`POST /quotes`), Jibiat Marchand peut envoyer `delivery_place` et `pickup_place` (codes) à la place de
`delivery_zone_id` et `pickup_zone_id` : Jibiat prend la zone de ce lieu, sinon celle du lieu qui le contient
(quartier, puis commune). Un identifiant de zone envoyé passe avant le code.

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

### Parrainage des marchands par les entreprises (1.4)

Une entreprise invite ses marchands sur Jibiat Marchand (lien `…/creer-ma-boutique?partenaire={slug}`) ; elle reçoit
une part de l'abonnement de chaque marchand parrainé (taux et durée dans `commission`), payée par Jibiat Marchand.

Jibiat Marchand tient l'entreprise informée avec la clé de la connexion du marchand à cette entreprise :
`PUT /api/public/v1/merchant-app` (toute portée), corps = l'objet `data` du schéma `merchant-app` sans `reported_at` ;
réponse `merchant-app` (ce que Jibiat a enregistré, avec `reported_at`). Envoyé seulement pour une boutique parrainée
par l'entreprise de la clé (`referred` vaut alors `true`), à chaque paiement d'abonnement et une fois par jour.

- `plan.status` : parmi `Contract::MERCHANT_APP_PLAN_STATUSES` (`none` : pas d'abonnement).
- `commission` : `rate` (0 à 1), `months` (durée à compter du premier paiement), `ends_at` (`null` avant le premier
  paiement), montants entiers en `currency` : `earned_total` (gagné depuis le début), `earned_month` (mois en cours),
  `paid_total` (déjà versé à l'entreprise).

### Étiquette de la course (1.5)

La course porte `label_url` (absent avec une version plus ancienne de Jibiat : pas de bouton) : l'étiquette
10 × 15 cm à imprimer (QR code de suivi, expéditeur, destinataire, montant à encaisser selon les réglages de
l'entreprise), ouverte sans connexion. Le lien est signé et valable 7 jours : prenez celui de la dernière lecture de
la course ou du dernier webhook, ne le gardez pas plus longtemps. L'impression est notée chez l'entreprise.

### Cartes Membre Premium Jibiat Marchand (1.6)

Une entreprise de Jibiat offre à ses meilleurs marchands la carte « Membre Premium Jibiat Marchand » : à la main,
ou toute seule quand le marchand remplit les conditions de l'entreprise (carte automatique). C'est **Jibiat** qui
appelle **Jibiat Marchand**, de serveur à serveur, avec un secret partagé (variable d'environnement des deux côtés,
jamais dans le code) :

- `X-Jibiat-Timestamp` (secondes) et `X-Jibiat-Signature` = `WebhookSignature::sign(secret, timestamp, contenu)`,
  refusé au-delà de 5 minutes. Le contenu signé est le corps brut pour `POST`, le chemin avec sa requête pour `GET`
  et `DELETE` (ex. `/api/partners/v1/premium-cards?company=livraison-express-ci`). `401` si la signature est fausse.
- `POST {Jibiat Marchand}/api/partners/v1/premium-cards`, corps `premium-card-request` : `201` et `premium-card`
  (carte créée), ou `200` et la carte déjà offerte à ce marchand (`company.slug` + `merchant.reference`) si elle
  n'est pas annulée. `merchant.reference` est l'identifiant du marchand chez Jibiat.
- `GET …/premium-cards?company={slug}` : `premium-card-list`, les cartes de l'entreprise, les plus récentes d'abord.
- `DELETE …/premium-cards/{code}?company={slug}` : annule une carte de l'entreprise ; réponse `premium-card`.

La carte : `url` (page du QR code, où le marchand ouvre ou rattache sa boutique ; la boutique créée est parrainée par
l'entreprise, voir 1.4), `label` à afficher tel quel, offre `plan` pendant `free_months` mois à partir de
l'activation (`free_until`), puis `discount_rate` (0 à 1) de réduction sur l'abonnement tant que le marchand livre
avec l'entreprise. `state` parmi `Contract::PREMIUM_CARD_STATES`. Qui paie les cartes et combien chaque entreprise
peut en offrir se règle chez Jibiat ; l'offre (offre, mois, réduction) se règle chez Jibiat Marchand.

**Règle de version** (`Contract::VERSION`) :

- *Ajouter* un champ, un statut, un événement ou un exemple : version 1.x suivante. Jibiat Marchand doit d'abord
  savoir le recevoir (un statut inconnu ne doit rien casser), puis Jibiat l'envoie.
- *Retirer, renommer ou changer le type* d'un champ, retirer un statut ou un événement : nouvelle version majeure
  (`contract/v2`), annoncée et déployée des deux côtés ; Jibiat garde `v1` tant que Jibiat Marchand l'utilise.
- Après chaque changement : `composer update habibou84/socle` dans les deux applications, leurs tests du contrat
  doivent passer avant la mise en production.
