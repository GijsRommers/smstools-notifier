<?php

declare(strict_types=1);

namespace GijsRommers\SmstoolsNotifier;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

final class SmstoolsTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): SmstoolsTransport
    {
        $sender = $dsn->getRequiredOption('from');
        if (!is_string($sender)) {
            throw new InvalidArgumentException('The SMSTools "from" option must be a string.');
        }

        $transport = new SmstoolsTransport(
            $this->getUser($dsn),
            $this->getPassword($dsn),
            $sender,
            $this->client,
            $this->dispatcher,
        );

        if ($dsn->getHost() !== 'default') {
            $transport->setHost($dsn->getHost());
            $transport->setPort($dsn->getPort());
        }

        return $transport;
    }

    protected function getSupportedSchemes(): array
    {
        return ['smstools'];
    }
}
