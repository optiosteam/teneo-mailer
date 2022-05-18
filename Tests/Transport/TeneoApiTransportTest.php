<?php

namespace Tests\Optios\Mailer\Teneo\Transport;

use Optios\Mailer\Teneo\Transport\TeneoApiTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
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

    public function testAttachmentSupport()
    {
        $email = new Email();
        $email->from(new Address('foo@example.com', 'Ms. Foo Bar'))
            ->to(new Address('bar@example.com', 'Mr. Recipient'))
            ->bcc('baz@example.com')
            ->text('content')
            ->attach('testcontent', 'test.jpg', 'image/jpeg');

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
        $httpClient->expects($this->once())
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
                                'from_name' => 'Ms. Foo Bar',
                                'attachments' => [
                                    0 => [
                                        'content_base64' => 'dGVzdGNvbnRlbnQ=',
                                        'filename' => 'test.jpg',
                                        'content_type' => 'image/jpeg',
                                    ]
                                ]
                            ]
                        ]
                    ],
                ]
            )
            ->willReturn($response);

        $mailer = new TeneoApiTransport('mailclass-test', 'bar', $httpClient);
        $mailer->send($email);
    }

    public function testHttpClientException()
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
            ->willThrowException(new TimeoutException('Oopsies'));

        $response
            ->expects($this->once())
            ->method('getInfo')
            ->willReturn(null);

        $httpClient = $this->createMock(HttpClientInterface::class);

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Could not reach the remote Teneo server.');

        $mailer = new TeneoApiTransport('mailclass-test', 'bar', $httpClient);
        $mailer->send($email);
    }

    public function testInvalidResponseCode()
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
            ->willReturn(300);

        $response
            ->expects($this->once())
            ->method('getInfo')
            ->willReturn(null);

        $httpClient = $this->createMock(HttpClientInterface::class);

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email (code 300).');

        $mailer = new TeneoApiTransport('mailclass-test', 'bar', $httpClient);
        $mailer->send($email);
    }

    public function testInvalidResponseContent()
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
            ->willThrowException(new JsonException());

        $response
            ->expects($this->once())
            ->method('getContent')
            ->willReturn(';invalid0json}');

        $httpClient = $this->createMock(HttpClientInterface::class);

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: ;invalid0json} (code 200).');

        $mailer = new TeneoApiTransport('mailclass-test', 'bar', $httpClient);
        $mailer->send($email);
    }

    public function testResponseGivesUnsuccessful()
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
            ->willReturn(['success' => false, 'messages' => [['success' => true, 'message_id' => 'mid-1']]]);

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

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email (unsuccessful response).');

        $mailer = new TeneoApiTransport('mailclass-test', 'bar', $httpClient);
        $mailer->send($email);
    }

    public function testResponseGivesUnsuccessfulForMessage()
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
            ->willReturn(['success' => true, 'messages' => [['success' => false, 'message_id' => 'mid-1']]]);

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

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: Unknown error.');

        $mailer = new TeneoApiTransport('mailclass-test', 'bar', $httpClient);
        $mailer->send($email);
    }
}
