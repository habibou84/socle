# Journal des versions

## 1.7.0
- Liste commune des lieux : `Places\Places` (Abidjan, ses communes et leurs quartiers, principales villes), codes
  fixes (`abidjan-cocody-angre`), variantes d'écriture, `all()`, `find()`, `label()`, `lineage()`, `inText()`,
  `named()`, `normalize()`. Reprend la liste de Jibiat Marchand.
- Contrat : champ facultatif `place` (code du lieu, ou `null`) sur chaque zone de `GET /zones` ; à la création
  d'une course et pour un devis, `delivery_place` et `pickup_place` (codes) remplacent au besoin `delivery_zone_id`
  et `pickup_zone_id` (README). Ajouts seulement : rien ne change pour la version 1.6.

## 1.6.0
- Cartes Membre Premium Jibiat Marchand offertes par les entreprises de Jibiat : schémas et exemples
  `premium-card-request`, `premium-card`, `premium-card-list` (`POST`, `GET` et `DELETE /api/partners/v1/premium-cards`
  chez Jibiat Marchand, README), `Contract::PREMIUM_CARD_STATES`, `Contract::PREMIUM_CARD_REASONS`, en-têtes de
  signature `Contract::PARTNER_SIGNATURE_HEADER` et `Contract::PARTNER_TIMESTAMP_HEADER`. Ajouts seulement : rien
  ne change pour la version 1.5.

## 1.5.0
- Étiquette de la course : champ facultatif `label_url` (lien signé vers l'étiquette à imprimer, sans connexion,
  valable 7 jours, renouvelé à chaque lecture de la course ou webhook), dans les exemples de course et de webhook.
  Ajout seulement : rien ne change pour la version 1.4.

## 1.4.0
- Parrainage des marchands par les entreprises de livraison : schéma et exemple `merchant-app`
  (`PUT /api/public/v1/merchant-app`, README), `Contract::MERCHANT_APP_PLAN_STATUSES`. Ajouts seulement : rien ne
  change pour la version 1.3.

## 1.3.1
- `PhoneNumber::modernize()` et `PhoneNumber::sameNumber()` : un compte WhatsApp encore sous l'ancien numéro
  ivoirien à 8 chiffres (+225 47 12 34 56) est reconnu comme le numéro à 10 chiffres (+225 07 47 12 34 56).

## 1.3.0
- Notation du livreur : champ facultatif `ratings` sur la course (`can_rate`, note de l'e-commerçant, note du
  destinataire), `Contract::MERCHANT_RATING_TAGS`, action `POST /api/public/v1/orders/{tracking_code}/rating`
  (README), exemple `order-delivered-rated`. Ajouts seulement : rien ne change pour la version 1.2.

## 1.2.0
- Annuaire des entreprises de livraison (`directory`) et connexion en un clic (`connect-token`, flux PKCE décrit
  dans le README), avec leurs exemples. Ajouts seulement : rien ne change pour la version 1.1.

## 1.1.0
- `Contract\Contract` et `Contract\SchemaValidator` : contrat de l'API publique de Jibiat et de ses webhooks,
  `contract/v1` (14 schémas, 20 exemples), statuts et événements, règle de version (voir README).

## 1.0.0
- Première version, reprise de Delivery App et de Jibiat Marchand : `Money`, `PhoneNumber`, `Rules\PhoneNumber`,
  passerelles SMS (`log`, `twilio`), `Webhooks\WebhookSignature`.
- `Sms\SmsException::$retryable` : panne passagère (réseau, 5xx, 429), l'envoi peut être retenté.
