<?php

namespace Tests\Optios\Mailer\Teneo\Transport;

use Optios\Mailer\Teneo\Transport\TeneoApiTransport;
use Optios\Mailer\Teneo\Transport\TeneoTransportFactory;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class TeneoTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new TeneoTransportFactory($this->getDispatcher(), $this->getClient(), $this->getLogger());
    }

    public function supportsProvider(): iterable
    {
        yield [
            new Dsn('teneo+api', 'default'),
            true,
        ];

        yield [
            new Dsn('teneo', 'default'),
            true,
        ];

        yield [
            new Dsn('teneo+api', 'example.com'),
            true,
        ];

        yield [
            new Dsn('teneo', 'example.com'),
            true,
        ];

        yield [
            new Dsn('teneo+smtp', 'default'),
            false,
        ];
    }

    public function createProvider(): iterable
    {
        $dispatcher = $this->getDispatcher();
        $logger     = $this->getLogger();

        yield [
            new Dsn('teneo+api', 'default', self::USER, self::PASSWORD),
            new TeneoApiTransport(self::USER, self::PASSWORD, $this->getClient(), $dispatcher, $logger),
        ];

        yield [
            new Dsn('teneo+api', 'example.com', self::USER, self::PASSWORD),
            (new TeneoApiTransport(self::USER, self::PASSWORD, $this->getClient(), $dispatcher, $logger))
                ->setHost('example.com'),
        ];

        yield [
            new Dsn('teneo+api', 'example.com', self::USER, self::PASSWORD, 8080),
            (new TeneoApiTransport(self::USER, self::PASSWORD, $this->getClient(), $dispatcher, $logger))
                ->setHost('example.com')
                ->setPort(8080),
        ];

        yield [
            new Dsn('teneo', 'default', self::USER, self::PASSWORD),
            new TeneoApiTransport(self::USER, self::PASSWORD, $this->getClient(), $dispatcher, $logger),
        ];

        yield [
            new Dsn('teneo', 'example.com', self::USER, self::PASSWORD),
            (new TeneoApiTransport(self::USER, self::PASSWORD, $this->getClient(), $dispatcher, $logger))
                ->setHost('example.com'),
        ];

        yield [
            new Dsn('teneo', 'example.com', self::USER, self::PASSWORD, 8080),
            (new TeneoApiTransport(self::USER, self::PASSWORD, $this->getClient(), $dispatcher, $logger))
                ->setHost('example.com')
                ->setPort(8080),
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('teneo+foo', 'teneo', self::USER),
            'The "teneo+foo" scheme is not supported; supported schemes for mailer "teneo" are: "teneo", "teneo+api".',
        ];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('teneo+api', 'default')];

        yield [new Dsn('teneo+api', 'default', self::USER)];

        yield [new Dsn('teneo+api', 'default', null, self::PASSWORD)];
    }
}
