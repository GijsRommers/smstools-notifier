<?php

declare(strict_types=1);

namespace GijsRommers\SmstoolsNotifier\Tests;

use GijsRommers\SmstoolsNotifier\SmstoolsTransportFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Transport\Dsn;

final class SmstoolsTransportFactoryTest extends TestCase
{
    public function testItCreatesTheTransportFromItsDsn(): void
    {
        $factory = new SmstoolsTransportFactory(null, new MockHttpClient());
        $dsn = new Dsn('smstools://client-id:client-secret@default?from=MyApp');

        self::assertTrue($factory->supports($dsn));
        self::assertSame('smstools://api.smsgatewayapi.com?from=MyApp', (string) $factory->create($dsn));
    }

    public function testItSupportsAConfiguredApiHost(): void
    {
        $factory = new SmstoolsTransportFactory(null, new MockHttpClient());
        $transport = $factory->create(new Dsn('smstools://client-id:client-secret@sms.example.test:8443?from=MyApp'));

        self::assertSame('smstools://sms.example.test:8443?from=MyApp', (string) $transport);
    }

    public function testItRequiresCredentials(): void
    {
        $factory = new SmstoolsTransportFactory(null, new MockHttpClient());

        $this->expectException(IncompleteDsnException::class);

        $factory->create(new Dsn('smstools://default?from=MyApp'));
    }
}
