<?php

declare(strict_types=1);

namespace GijsRommers\SmstoolsNotifier\Tests;

use GijsRommers\SmstoolsNotifier\Webhook\SmstoolsRequestParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RemoteEvent\Event\Sms\SmsEvent;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class SmstoolsRequestParserTest extends TestCase
{
    private const SECRET = 'webhook-secret';

    public function testItParsesAnAuthenticatedDeliveryReport(): void
    {
        $request = $this->signedRequest([
            'webhook_id' => 'wh_123',
            'webhook_type' => 'delivery_report',
            'message' => [
                'messageid' => 'msg_123',
                'receiver' => '32470123456',
                'delivery_code' => '1',
                'delivery_status' => 'delivered',
            ],
        ]);

        $event = (new SmstoolsRequestParser())->parse($request, self::SECRET);

        self::assertInstanceOf(SmsEvent::class, $event);
        self::assertSame(SmsEvent::DELIVERED, $event->getName());
        self::assertSame('wh_123', $event->getId());
        self::assertSame('32470123456', $event->getRecipientPhone());
    }

    public function testItDoesNotTreatAnIntermediateDeliveryReportAsFailed(): void
    {
        $request = $this->signedRequest([
            'webhook_id' => 'wh_789',
            'webhook_type' => 'delivery_report',
            'message' => [
                'messageid' => 'msg_789',
                'receiver' => '32470123456',
                'delivery_code' => '0',
                'delivery_status' => 'submitted',
            ],
        ]);

        $event = (new SmstoolsRequestParser())->parse($request, self::SECRET);

        self::assertInstanceOf(RemoteEvent::class, $event);
        self::assertNotInstanceOf(SmsEvent::class, $event);
        self::assertSame('delivery_report', $event->getName());
    }

    public function testItPreservesOtherAuthenticatedWebhookTypes(): void
    {
        $request = $this->signedRequest([
            'webhook_id' => 'wh_456',
            'webhook_type' => 'incoming_message',
            'message' => ['messageid' => 'msg_456'],
        ]);

        $event = (new SmstoolsRequestParser())->parse($request, self::SECRET);

        self::assertInstanceOf(RemoteEvent::class, $event);
        self::assertNotInstanceOf(SmsEvent::class, $event);
        self::assertSame('incoming_message', $event->getName());
    }

    public function testItRejectsAnInvalidSignature(): void
    {
        $request = $this->signedRequest([
            'webhook_id' => 'wh_123',
            'webhook_type' => 'delivery_report',
            'message' => [],
        ]);
        $request->headers->set('X-Smstools-Signature', 't='.time().',v1=invalid');

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid SMSTools webhook signature');

        (new SmstoolsRequestParser())->parse($request, self::SECRET);
    }

    public function testItRejectsAStaleTimestamp(): void
    {
        $request = $this->signedRequest([
            'webhook_id' => 'wh_123',
            'webhook_type' => 'delivery_report',
            'message' => [],
        ], time() - 301);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('outside the allowed window');

        (new SmstoolsRequestParser())->parse($request, self::SECRET);
    }

    /** @param array<string, mixed> $payload */
    private function signedRequest(array $payload, ?int $timestamp = null): Request
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp ??= time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET);

        return Request::create('/', 'POST', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SMSTOOLS_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_SMSTOOLS_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], content: $body);
    }
}
