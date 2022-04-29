<?php

namespace Optios\Mailer\Teneo\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Kevin Verschaeve
 */
class TeneoApiTransport extends AbstractApiTransport
{
    private const HOST = 'tlsrelay.teneo.be';

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @param string                        $username
     * @param string                        $password
     * @param HttpClientInterface|null      $client
     * @param EventDispatcherInterface|null $dispatcher
     * @param LoggerInterface|null          $logger
     */
    public function __construct(
        string $username,
        string $password,
        HttpClientInterface $client = null,
        EventDispatcherInterface $dispatcher = null,
        LoggerInterface $logger = null
    ) {
        parent::__construct($client, $dispatcher, $logger);
        $this->username = $username;
        $this->password = $password;
    }

    public function __toString(): string
    {
        return sprintf('teneo+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        if (0 < count($email->getAttachments())) {
            throw new TransportException('Teneo api does not support attachments.');
        }

        $response = $this->client->request(
            'POST',
            'https://' . $this->getEndpoint() . '/api/v1/send.json',
            [
                'verify_peer' => false,
                'verify_host' => false,
                'json' => $this->getPayload($email, $envelope),
            ]
        );

        try {
            $statusCode = $response->getStatusCode();
        } catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Teneo server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new HttpTransportException(
                sprintf(
                    'Unable to send an email (code %d).',
                    $statusCode
                ),
                $response
            );
        }

        try {
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new HttpTransportException(
                sprintf(
                    'Unable to send an email: %s (code %d).',
                    $response->getContent(false),
                    $statusCode
                ),
                $response,
                0,
                $e
            );
        }

        if (false === boolval($result[ 'success' ]) || 0 === count($result[ 'messages' ])) {
            throw new HttpTransportException(
                'Unable to send an email (unsuccessful response).',
                $response
            );
        }

        $messageResult = $result[ 'messages' ][ 0 ];
        if (false === $messageResult[ 'success' ]) {
            throw new HttpTransportException(
                sprintf('Unable to send an email: %s.', $messageResult[ 'error' ] ?? 'Unknown error'),
                $response
            );
        }

        $sentMessage->setMessageId($messageResult[ 'message_id' ]);

        return $response;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'username' => $this->username . '@' . self::HOST,
            'password' => $this->password,
            'messages' => [
                $this->getMessagePayload($email, $envelope),
            ],
        ];

        return $payload;
    }

    private function getMessagePayload(Email $email, Envelope $envelope): array
    {
        preg_match('/mailclass-([a-z0-9]+)/', $this->username, $matches);
        $mailClass = $matches[ 1 ];

        $payload = [
            'mailclass' => $mailClass,
            'html' => $email->getHtmlBody(),
            'text' => $email->getTextBody(),
            'subject' => $email->getSubject(),
            'to' => array_map([$this, 'getAddressPayload'], $email->getTo()),
            'from_email' => $envelope->getSender()->getAddress(),
            'headers' => $this->getHeaders($email->getHeaders()),
        ];

        $fromName = $envelope->getSender()->getName();
        if ($fromName) {
            $payload[ 'from_name' ] = $fromName;
        }

        return $payload;
    }

    /**
     * Private method is sued on line 138
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function getAddressPayload(Address $address): array
    {
        $payload = ['email' => $address->getAddress()];

        if ($address->getName()) {
            $payload[ 'name' ] = $address->getName();
        }

        return $payload;
    }

    private function getEndpoint(): ?string
    {
        return ($this->host ?: self::HOST) . ($this->port ? ':' . $this->port : '');
    }

    /**
     * @param Headers $headers
     *
     * @return array
     */
    private function getHeaders(Headers $headers): array
    {
        $result = [];
        /** @var MailboxListHeader|UnstructuredHeader $header */
        foreach ($headers->all() as $header) {
            if (
                $header instanceof UnstructuredHeader
                && (strpos($header->getName(), 'X-') === 0 || strpos($header->getName(), 'List-') === 0)
            ) {
                $result[ $header->getName() ] = $header->getBodyAsString();
            }
        }

        return $result;
    }
}
