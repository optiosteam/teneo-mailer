<?php

namespace Optios\Mailer\Teneo\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class TeneoTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if (!\in_array($dsn->getScheme(), $this->getSupportedSchemes(), true)) {
            throw new UnsupportedSchemeException($dsn, 'teneo', $this->getSupportedSchemes());
        }

        $username = $this->getUser($dsn);
        $password = $this->getPassword($dsn);
        $host     = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port     = $dsn->getPort();

        return (new TeneoApiTransport($username, $password, $this->client, $this->dispatcher, $this->logger))
            ->setHost($host)
            ->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['teneo', 'teneo+api'];
    }
}
