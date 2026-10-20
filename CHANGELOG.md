# Journal des versions

## 1.1.0
- `Contract\Contract` et `Contract\SchemaValidator` : contrat de l'API publique de Jibiat et de ses webhooks,
  `contract/v1` (14 schémas, 20 exemples), statuts et événements, règle de version (voir README).

## 1.0.0
- Première version, reprise de Delivery App et de Jibiat Marchand : `Money`, `PhoneNumber`, `Rules\PhoneNumber`,
  passerelles SMS (`log`, `twilio`), `Webhooks\WebhookSignature`.
- `Sms\SmsException::$retryable` : panne passagère (réseau, 5xx, 429), l'envoi peut être retenté.
