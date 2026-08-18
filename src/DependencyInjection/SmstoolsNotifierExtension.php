<?php

declare(strict_types=1);

namespace GijsRommers\SmstoolsNotifier\DependencyInjection;

use GijsRommers\SmstoolsNotifier\SmstoolsTransportFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

final class SmstoolsNotifierExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container
            ->register(SmstoolsTransportFactory::class)
            ->setAutowired(true)
            ->addTag('texter.transport_factory');
    }
}
