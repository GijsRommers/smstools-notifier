<?php

declare(strict_types=1);

namespace GijsRommers\SmstoolsNotifier\Tests;

use GijsRommers\SmstoolsNotifier\DependencyInjection\SmstoolsNotifierExtension;
use GijsRommers\SmstoolsNotifier\SmstoolsNotifierBundle;
use GijsRommers\SmstoolsNotifier\SmstoolsTransportFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SmstoolsNotifierBundleTest extends TestCase
{
    public function testItExposesItsContainerExtension(): void
    {
        self::assertInstanceOf(
            SmstoolsNotifierExtension::class,
            (new SmstoolsNotifierBundle())->getContainerExtension(),
        );
    }

    public function testItsExtensionRegistersTheTransportFactory(): void
    {
        $container = new ContainerBuilder();

        (new SmstoolsNotifierExtension())->load([], $container);

        $definition = $container->getDefinition(SmstoolsTransportFactory::class);
        self::assertTrue($definition->isAutowired());
        self::assertTrue($definition->hasTag('texter.transport_factory'));
    }
}
