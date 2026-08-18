<?php

declare(strict_types=1);

namespace GijsRommers\SmstoolsNotifier;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SmstoolsTransport extends AbstractTransport
{
    protected const HOST = 'api.smsgatewayapi.com';

    public function __construct(
        private readonly string $clientId,
        #[\SensitiveParameter]
        private readonly string $clientSecret,
        private readonly string $sender,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('smstools://%s?from=%s', $this->getEndpoint(), rawurlencode($this->sender));
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(self::class, SmsMessage::class, $message);
        }

        $client = $this->client ?? throw new \LogicException('The HTTP client is not available.');
        $response = $client->request('POST', 'https://'.$this->getEndpoint().'/v1/message/send', [
            'headers' => [
                'X-Client-Id' => $this->clientId,
                'X-Client-Secret' => $this->clientSecret,
            ],
            'json' => [
                'message' => $message->getSubject(),
                'to' => $message->getPhone(),
                'sender' => $message->getFrom() !== '' ? $message->getFrom() : $this->sender,
            ],
        ]);

        try {
            $content = $response->getContent();
        } catch (HttpExceptionInterface $exception) {
            throw new TransportException('Unable to send an SMS through SMSTools.', $response, previous: $exception);
        }

        $decoded = json_decode($content, true);
        $info = is_array($decoded) ? $decoded : [];
        $sentMessage = new SentMessage($message, (string) $this, $info);
        if (is_string($info['messageid'] ?? null)) {
            $sentMessage->setMessageId($info['messageid']);
        }

        return $sentMessage;
    }
}
