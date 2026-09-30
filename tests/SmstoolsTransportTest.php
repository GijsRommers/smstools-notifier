<?php

declare(strict_types=1);

namespace GijsRommers\SmstoolsNotifier\Tests;

use GijsRommers\SmstoolsNotifier\SmstoolsTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\SmsMessage;

final class SmstoolsTransportTest extends TestCase
{
    public function testItSendsAnSmsAndExposesTheProviderMessageId(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.smsgatewayapi.com/v1/message/send', $url);

            $headers = $options['headers'] ?? null;
            self::assertIsArray($headers);
            self::assertContains('X-Client-Id: client-id', $headers);
            self::assertContains('X-Client-Secret: client-secret', $headers);

            $body = $options['body'] ?? null;
            if (!is_string($body)) {
                self::fail('Expected an encoded JSON request body.');
            }
            self::assertSame([
                'message' => 'Hello from Symfony!',
                'to' => '32470123456',
                'sender' => 'MyApp',
            ], json_decode($body, true, 512, JSON_THROW_ON_ERROR));

            return new MockResponse('{"messageid":"message-123"}');
        });
        $transport = new SmstoolsTransport('client-id', 'client-secret', 'Fallback', $httpClient);

        $sent = $transport->send(new SmsMessage('32470123456', 'Hello from Symfony!', 'MyApp'));

        self::assertSame('message-123', $sent->getMessageId());
        self::assertSame('smstools://api.smsgatewayapi.com?from=Fallback', $sent->getTransport());
    }

    public function testItUsesTheConfiguredSenderWhenTheMessageDoesNotOverrideIt(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            $body = $options['body'] ?? null;
            if (!is_string($body)) {
                self::fail('Expected an encoded JSON request body.');
            }
            self::assertSame('DefaultApp', json_decode($body, true, 512, JSON_THROW_ON_ERROR)['sender'] ?? null);

            return new MockResponse('{"messageid":"message-123"}');
        });

        (new SmstoolsTransport('client-id', 'client-secret', 'DefaultApp', $httpClient))
            ->send(new SmsMessage('32470123456', 'Hello from Symfony!'));
    }

    public function testItWrapsAnUnsuccessfulApiResponse(): void
    {
        $transport = new SmstoolsTransport(
            'client-id',
            'client-secret',
            'MyApp',
            new MockHttpClient(new MockResponse('', ['http_code' => 503])),
        );

        $this->expectException(TransportException::class);

        $transport->send(new SmsMessage('32470123456', 'Hello from Symfony!'));
    }

    #[DataProvider('invalidResponseProvider')]
    public function testItRejectsAMalformedSuccessfulApiResponse(string $body, bool $invalidJson): void
    {
        $response = new MockResponse($body);
        $transport = new SmstoolsTransport('client-id', 'client-secret', 'MyApp', new MockHttpClient($response));

        try {
            $transport->send(new SmsMessage('32470123456', 'Hello from Symfony!'));
            self::fail('Expected a malformed response to be rejected.');
        } catch (TransportException $exception) {
            self::assertSame(200, $exception->getResponse()->getStatusCode());
            self::assertSame($body, $exception->getResponse()->getContent());
            if ($invalidJson) {
                self::assertInstanceOf(\JsonException::class, $exception->getPrevious());
            } else {
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public static function invalidResponseProvider(): iterable
    {
        yield 'empty body' => ['', true];
        yield 'malformed JSON' => ['{"messageid":', true];
        yield 'HTML response' => ['<html>Bad gateway</html>', true];
        yield 'null' => ['null', false];
        yield 'boolean' => ['true', false];
        yield 'number' => ['123', false];
        yield 'string' => ['"message-123"', false];
        yield 'empty array' => ['[]', false];
        yield 'list' => ['[{"messageid":"message-123"}]', false];
        yield 'empty object' => ['{}', false];
        yield 'API error without message ID' => ['{"error":"Invalid recipient"}', false];
        yield 'null message ID' => ['{"messageid":null}', false];
        yield 'boolean message ID' => ['{"messageid":true}', false];
        yield 'integer message ID' => ['{"messageid":123}', false];
        yield 'float message ID' => ['{"messageid":1.5}', false];
        yield 'array message ID' => ['{"messageid":[]}', false];
        yield 'object message ID' => ['{"messageid":{}}', false];
        yield 'empty message ID' => ['{"messageid":""}', false];
        yield 'blank message ID' => ['{"messageid":" \\t\\r\\n"}', false];
    }

    public function testItPreservesAValidNumericStringMessageId(): void
    {
        $transport = new SmstoolsTransport(
            'client-id',
            'client-secret',
            'MyApp',
            new MockHttpClient(new MockResponse('{"messageid":"0","status":"queued"}')),
        );

        $sent = $transport->send(new SmsMessage('32470123456', 'Hello from Symfony!'));

        self::assertSame('0', $sent->getMessageId());
    }
}
