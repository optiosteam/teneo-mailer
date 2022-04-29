<?php

namespace Tests\Optios\Mailer\Teneo\Transport;

use Optios\Mailer\Teneo\Transport\TeneoApiTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TeneoApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(TeneoApiTransport $transport, string $expectedDsn)
    {
        $this->assertEquals($expectedDsn, (string)$transport);
    }

    public function getTransportData()
    {
        return [
            [
                new TeneoApiTransport('KEY', 'PA$$'),
                'teneo+api://tlsrelay.teneo.be',
            ],
            [
                (new TeneoApiTransport('KEY', 'PA$$'))->setHost('example.com'),
                'teneo+api://example.com',
            ],
            [
                (new TeneoApiTransport('KEY', 'PA$$'))->setHost('example.com')->setPort(99),
                'teneo+api://example.com:99',
            ],
        ];
    }

    public function testSend()
    {
        $email = new Email();
        $email->from(new Address('foo@example.com', 'Ms. Foo Bar'))
              ->to(new Address('bar@example.com', 'Mr. Recipient'))
              ->bcc('baz@example.com')
              ->text('content');

        $response = $this->createMock(ResponseInterface::class);

        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $response
            ->expects($this->once())
            ->method('getInfo')
            ->willReturn(null);

        $response
            ->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn(['success' => true, 'messages' => [['success' => true, 'message_id' => 'mid-1']]]);

        $httpClient = $this->createMock(HttpClientInterface::class);

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://tlsrelay.teneo.be/api/v1/send.json',
                [
                    'verify_peer' => false,
                    'verify_host' => false,
                    'json' => [
                        'username' => 'mailclass-test@tlsrelay.teneo.be',
                        'password' => 'bar',
                        'messages' => [
                            [
                                'mailclass' => 'test',
                                'html' => null,
                                'text' => 'content',
                                'subject' => null,
                                'to' => [
                                    [
                                        'email' => 'bar@example.com',
                                        'name' => 'Mr. Recipient'
                                    ]
                                ],
                                'from_email' => 'foo@example.com',
                                'headers' => [],
                                'from_name' => 'Ms. Foo Bar'
                            ]
                        ]
                    ],
                ]
            )
            ->willReturn($response);

        $mailer = new TeneoApiTransport('mailclass-test', 'bar', $httpClient);
        $mailer->send($email);
    }
}
