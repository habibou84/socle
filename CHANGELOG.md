# Journal des versions

## 1.0.0
- Première version, reprise de Delivery App et de Jibiat Marchand : `Money`, `PhoneNumber`, `Rules\PhoneNumber`,
  passerelles SMS (`log`, `twilio`), `Webhooks\WebhookSignature`.
- `Sms\SmsException::$retryable` : panne passagère (réseau, 5xx, 429), l'envoi peut être retenté.
