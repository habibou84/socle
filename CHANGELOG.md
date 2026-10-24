# Journal des versions

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
