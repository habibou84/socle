# Journal des versions

## 1.15.0
- Colis en retour réaffecté à une autre course du même e-commerçant (même article) : nouveau statut final
  `reassigned` pour la course d'origine, champs facultatifs `reassigned_to` (code de la nouvelle course) et
  `reassigned_from` (sur la nouvelle course, qui commence à `picked_up` ou `at_hub`, sans ramassage). Exemple
  `order-reassigned`. Un statut inconnu ne doit rien casser chez l'e-commerçant. Ajouts seulement : rien ne change
  pour la version 1.14.

## 1.14.0
- Remise des retours confirmée par le marchand : quand le livreur rapporte des colis, Jibiat crée une remise
  (« R-4821 ») que le marchand confirme (QR code WhatsApp, ou Jibiat Marchand). Schémas `return-handover`,
  `return-handover-list` (`GET /api/public/v1/return-handovers` : remises encore confirmables),
  `return-handover-response` (`POST /api/public/v1/return-handovers/{reference}/confirm` : tout reçu ;
  `…/dispute` : il en manque), `return-handover-webhook` ; événement `return_handover.updated` ;
  `Contract::RETURN_HANDOVER_STATUSES`, `Contract::RETURN_HANDOVER_OPEN_STATUSES`. Les colis ne passent « returned »
  qu'une fois la remise confirmée (ou tranchée par l'agence) : la remise en stock suit le statut de la course comme
  avant. Ajouts seulement : rien ne change pour la version 1.12 (`Contract::VERSION` passe de 1.12 à 1.14, la 1.13
  ne touchant que les lieux).

## 1.13.0
- Référentiel des lieux complété, tenu par la plateforme : quartiers des 13 communes d'Abidjan (228 quartiers en
  tout), principaux quartiers des grandes villes (Bouaké, Yamoussoukro, San-Pédro, Daloa, Korhogo, Man, Gagnoa,
  Grand-Bassam), 37 villes. Les codes existants ne changent pas. `Places::checksum()` : empreinte de la liste, pour
  resynchroniser les applications quand elle change. `Places::directCities()` et `Places::isDirect()` : depuis Abidjan,
  seul Grand-Bassam est livré directement ; toutes les autres villes passent par l'expédition. Le contrat de l'API ne
  change pas (toujours 1.12).

## 1.12.0
- Connexion directe de Jibiat Marchand à une entreprise, de serveur à serveur, sans redirection ni mot de passe :
  schémas `connect-direct-request`, `connect-direct` (`POST`, `GET`, `DELETE {url}/api/public/connect/direct`) et
  `connect-decision` (`POST {Jibiat Marchand}/api/partners/v1/connections`), requêtes signées comme en 1.6,
  `Contract::CONNECT_DIRECT_STATUSES`, `Contract::CONNECT_DECISION_STATUSES`, `Contract::CONNECT_DIRECT_WAIT_HOURS`
  (24 h quand un colis attend). Un compte existant n'est jamais rattaché sans l'accord du marchand.
- Devis de l'annuaire pour un lieu, sans clé : schéma `directory-quotes` (`POST https://jibiat.com/api/public/directory/quotes`) ;
  champ facultatif `direct` dans l'annuaire. Ajouts seulement : rien ne change pour la version 1.11.

## 1.11.0
- Raison du retour : champ facultatif `return_cause` sur la course (`refused`, `max_attempts`, `company`, `merchant`
  ou `null`), `Contract::RETURN_CAUSES` (phrase à montrer à l'e-commerçant), exemple `order-refused`. Ajouts
  seulement : rien ne change pour la version 1.10.

## 1.10.0
- Colis pas ramassé à sa date : champ facultatif `reschedule` sur la course (`status` pending, `missed_day`,
  `requested_at`, `max_date`, `answer_url`), `Contract::RESCHEDULE_MAX_DAYS`, `POST …/reschedule` avec `date`,
  exemple `order-reschedule-requested`. Ajouts seulement : rien ne change pour la version 1.9.

## 1.9.0
- Annulation : champ facultatif `cancellation` sur la course (`by` merchant ou company, `name`, `role`, `reason`,
  `reason_label`, `note`, `rejected`, `cancelled_at`), `Contract::CANCEL_REASONS_MERCHANT`,
  `Contract::CANCEL_REASONS_COMPANY`, `reason_code` sur `POST …/cancel`, exemple `order-cancelled`. Ajouts seulement :
  rien ne change pour la version 1.8 (une annulation sans `reason_code` reste acceptée avec une précision `reason`).

## 1.8.0
- Cartes Membre Premium : champ facultatif `offer` (`plan`, `free_months`, `discount_rate`) dans
  `premium-card-request`, la formule choisie par l'entreprise ; `Contract::PREMIUM_CARD_PLANS`. Ajouts seulement :
  rien ne change pour la version 1.7.

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
