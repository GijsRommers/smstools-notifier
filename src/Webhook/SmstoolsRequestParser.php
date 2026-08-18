<?php

declare(strict_types=1);

namespace GijsRommers\SmstoolsNotifier\Webhook;

use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\RemoteEvent\Event\Sms\SmsEvent;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\InvalidArgumentException;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class SmstoolsRequestParser extends AbstractRequestParser
{
    public function __construct(private readonly int $timestampTolerance = 300)
    {
        if ($timestampTolerance < 0) {
            throw new \InvalidArgumentException('The timestamp tolerance cannot be negative.');
        }
    }

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
        ]);
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): RemoteEvent
    {
        if ($secret === '') {
            throw new InvalidArgumentException('A non-empty SMSTools webhook secret is required.');
        }

        $timestampHeader = $request->headers->get('X-Smstools-Timestamp');
        $signatureHeader = $request->headers->get('X-Smstools-Signature');
        if ($timestampHeader === null || $signatureHeader === null) {
            throw new RejectWebhookException(406, 'Missing SMSTools webhook signature headers.');
        }

        if (!ctype_digit($timestampHeader)) {
            throw new RejectWebhookException(406, 'Invalid SMSTools webhook timestamp.');
        }

        $timestamp = (int) $timestampHeader;
        if (abs(time() - $timestamp) > $this->timestampTolerance) {
            throw new RejectWebhookException(406, 'SMSTools webhook timestamp is outside the allowed window.');
        }

        $signatureParts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key !== null && $value !== null) {
                $signatureParts[$key] = $value;
            }
        }

        if (($signatureParts['t'] ?? null) !== $timestampHeader || !isset($signatureParts['v1'])) {
            throw new RejectWebhookException(406, 'Invalid SMSTools webhook signature header.');
        }

        $expected = hash_hmac('sha256', $timestampHeader.'.'.$request->getContent(), $secret);
        if (!hash_equals($expected, $signatureParts['v1'])) {
            throw new RejectWebhookException(406, 'Invalid SMSTools webhook signature.');
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable $exception) {
            throw new RejectWebhookException(406, 'Invalid SMSTools webhook payload.', $exception);
        }

        $id = $this->stringValue($payload['webhook_id'] ?? null)
            ?? $this->stringValue($payload['message']['messageid'] ?? null);
        $type = $this->stringValue($payload['webhook_type'] ?? null);
        if ($id === null || $type === null) {
            throw new RejectWebhookException(406, 'The SMSTools webhook payload has no event identifier or type.');
        }

        if ($type !== 'delivery_report') {
            return new RemoteEvent($type, $id, $payload);
        }

        $message = $payload['message'] ?? null;
        if (!is_array($message)) {
            throw new RejectWebhookException(406, 'The SMSTools delivery report has no message payload.');
        }

        $deliveryCode = $this->stringValue($message['delivery_code'] ?? null);
        $name = match ($deliveryCode) {
            '1' => SmsEvent::DELIVERED,
            '2', '4', '5' => SmsEvent::FAILED,
            default => null,
        };
        if ($name === null) {
            return new RemoteEvent($type, $id, $payload);
        }

        $event = new SmsEvent($name, $id, $payload);
        $event->setRecipientPhone($this->stringValue($message['receiver'] ?? null) ?? '');

        return $event;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
