# SMSTools Notifier

An unofficial [SMSTools](https://www.smstools.com/en/sms-gateway-api/) transport for Symfony Notifier.

## Installation

```bash
composer require gijsrommers/smstools-notifier
```

Register the transport factory:

```yaml
# config/services.yaml
services:
    GijsRommers\SmstoolsNotifier\SmstoolsTransportFactory:
        autowire: true
        autoconfigure: false
        tags: ['texter.transport_factory']
```

Configure the transport:

```dotenv
SMS_TOOLS_DSN=smstools://CLIENT_ID:CLIENT_SECRET@default?from=MyApp
```

```yaml
# config/packages/notifier.yaml
framework:
    notifier:
        texter_transports:
            smstools: '%env(SMS_TOOLS_DSN)%'
```

Send an SMS using Symfony's standard API:

```php
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\TexterInterface;

$texter->send(new SmsMessage('32470123456', 'Hello from Symfony!'));
```

The `from` value in an individual `SmsMessage` overrides the sender configured in the DSN. URL-encode credentials containing reserved URI characters.

## Delivery webhooks

Install Symfony Webhook if it is not already present:

```bash
composer require symfony/webhook
```

Register the parser and configure a webhook route with the signing secret shown by SMSTools:

```yaml
# config/services.yaml
services:
    GijsRommers\SmstoolsNotifier\Webhook\SmstoolsRequestParser: ~
```

```yaml
# config/packages/webhook.yaml
framework:
    webhook:
        routing:
            smstools:
                service: GijsRommers\SmstoolsNotifier\Webhook\SmstoolsRequestParser
                secret: '%env(SMS_TOOLS_WEBHOOK_SECRET)%'
```

Point the SMSTools webhook at `/webhook/smstools`. The parser validates the
`X-Smstools-Timestamp` and `X-Smstools-Signature` headers against the unmodified
request body and rejects signatures older than five minutes. Delivery reports
with terminal delivered/not-delivered codes are exposed as Symfony `SmsEvent`
instances. Intermediate reports and other authenticated SMSTools events remain
available as generic `RemoteEvent` instances.

## Development

```bash
composer install
composer test
```

## License

MIT. This package is community-maintained and is not an official SMSTools product.
