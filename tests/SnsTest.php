<?php

namespace NotificationChannels\AwsSns\Test;

use Aws\Result;
use Aws\Sns\SnsClient as SnsService;
use Illuminate\Contracts\Events\Dispatcher;
use Mockery;
use NotificationChannels\AwsSns\Sns;
use NotificationChannels\AwsSns\SnsSMSMessage;
use NotificationChannels\AwsSns\SnsPushMessage;

class SnsTest extends TestCase
{
    /**
     * @var Mockery\LegacyMockInterface|Mockery\MockInterface|SnsService
     */
    protected $snsService;

    /**
     * @var Dispatcher|Mockery\LegacyMockInterface|Mockery\MockInterface
     */
    protected $dispatcher;

    /**
     * @var Sns
     */
    protected $sns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->snsService = Mockery::mock(SnsService::class);
        $this->dispatcher = Mockery::mock(Dispatcher::class);

        $this->sns = new Sns($this->snsService);
    }

    public function test_it_can_send_a_promotional_sms_message_to_sns()
    {
        $message = new SnsSMSMessage('Message text');

        $this->snsService->shouldReceive('publish')
            ->atLeast()
            ->once()
            ->with([
                'Message' => 'Message text',
                'PhoneNumber' => '+1111111111',
                'MessageAttributes' => [
                    'AWS.SNS.SMS.SMSType' => [
                        'DataType' => 'String',
                        'StringValue' => 'Promotional',
                    ],
                ],
            ])
            ->andReturn(new Result);

        $this->sns->send($message, '+1111111111');
    }

    public function test_it_can_send_a_transactional_sms_message_to_sns()
    {
        $message = new SnsSMSMessage(['body' => 'Message text', 'transactional' => true]);

        $this->snsService->shouldReceive('publish')
            ->atLeast()
            ->once()
            ->with([
                'Message' => 'Message text',
                'PhoneNumber' => '+22222222222',
                'MessageAttributes' => [
                    'AWS.SNS.SMS.SMSType' => [
                        'DataType' => 'String',
                        'StringValue' => 'Transactional',
                    ],
                ],
            ])
            ->andReturn(new Result);

        $this->sns->send($message, '+22222222222');
    }

    public function test_it_can_send_a_sms_message_with_sender_id()
    {
        $message = new SnsSMSMessage(['body' => 'Message text', 'sender' => 'CompanyInc']);

        $this->snsService->shouldReceive('publish')
            ->atLeast()
            ->once()
            ->with([
                'Message' => 'Message text',
                'PhoneNumber' => '+33333333333',
                'MessageAttributes' => [
                    'AWS.SNS.SMS.SMSType' => [
                        'DataType' => 'String',
                        'StringValue' => 'Promotional',
                    ],
                    'AWS.SNS.SMS.SenderID' => [
                        'DataType' => 'String',
                        'StringValue' => 'CompanyInc',
                    ],
                ],
            ])
            ->andReturn(new Result);

        $this->sns->send($message, '+33333333333');
    }

    public function test_it_can_send_a_push_message_to_sns()
    {
        $message = new SnsPushMessage([
            'body' => 'Hello World',
            'title' => 'Test Title',
            'badge' => 5,
        ]);

        $endpointArn = 'arn:aws:sns:us-east-1:123456789012:endpoint/APNS/MyApp/12345678-1234-1234-1234-123456789012';

        $this->snsService->shouldReceive('publish')
            ->atLeast()
            ->once()
            ->with(Mockery::on(function ($parameters) use ($endpointArn) {
                return $parameters['TargetArn'] === $endpointArn
                    && $parameters['MessageStructure'] === 'json'
                    && isset($parameters['Message'])
                    && isset($parameters['MessageAttributes']);
            }))
            ->andReturn(new Result);

        $this->sns->sendPush($message, $endpointArn);
    }

    public function test_it_can_auto_detect_sms_message_type()
    {
        $message = new SnsSMSMessage('SMS Message');

        $this->snsService->shouldReceive('publish')
            ->atLeast()
            ->once()
            ->with(Mockery::on(function ($parameters) {
                return isset($parameters['PhoneNumber'])
                    && isset($parameters['MessageAttributes']['AWS.SNS.SMS.SMSType']);
            }))
            ->andReturn(new Result);

        $this->sns->send($message, '+1111111111');
    }

    public function test_it_can_auto_detect_push_message_type()
    {
        $message = new SnsPushMessage(['body' => 'Push Message']);

        $endpointArn = 'arn:aws:sns:us-east-1:123456789012:endpoint/APNS/MyApp/12345678-1234-1234-1234-123456789012';

        $this->snsService->shouldReceive('publish')
            ->atLeast()
            ->once()
            ->with(Mockery::on(function ($parameters) use ($endpointArn) {
                return $parameters['TargetArn'] === $endpointArn
                    && $parameters['MessageStructure'] === 'json';
            }))
            ->andReturn(new Result);

        $this->sns->send($message, $endpointArn);
    }

    public function test_it_can_send_to_multiple_sms_destinations()
    {
        $message = new SnsSMSMessage('Message text');

        $destinations = ['+1111111111', '+22222222222'];

        $this->snsService->shouldReceive('publish')
            ->atLeast()->once()
            ->with([
                'Message' => 'Message text',
                'PhoneNumber' => '+1111111111',
                'MessageAttributes' => [
                    'AWS.SNS.SMS.SMSType' => [
                        'DataType' => 'String',
                        'StringValue' => 'Promotional',
                    ],
                ],
            ])
            ->andReturn(new Result);

        $this->snsService->shouldReceive('publish')
            ->atLeast()->once()
            ->with([
                'Message' => 'Message text',
                'PhoneNumber' => '+22222222222',
                'MessageAttributes' => [
                    'AWS.SNS.SMS.SMSType' => [
                        'DataType' => 'String',
                        'StringValue' => 'Promotional',
                    ],
                ],
            ])
            ->andReturn(new Result);

        $this->sns->send($message, $destinations);
    }

    public function test_it_can_send_to_multiple_push_destinations()
    {
        $message = new SnsPushMessage(['body' => 'Push Message']);

        $destinations = [
            'arn:aws:sns:us-east-1:123456789012:endpoint/APNS/MyApp/aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'arn:aws:sns:us-east-1:123456789012:endpoint/APNS/MyApp/bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        ];

        foreach ($destinations as $endpoint) {
            $this->snsService->shouldReceive('publish')
                ->atLeast()->once()
                ->with(\Mockery::on(function ($parameters) use ($endpoint) {
                    return $parameters['TargetArn'] === $endpoint
                        && $parameters['MessageStructure'] === 'json'
                        && isset($parameters['Message']);
                }))
                ->andReturn(new Result);
        }

        $this->sns->send($message, $destinations);
    }

    public function test_it_handles_endpoint_disabled_error_on_push_send()
    {
        $endpointArn = 'arn:aws:sns:us-east-1:123456789012:endpoint/APNS/MyApp/aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $message = new SnsPushMessage(['body' => 'Push Message']);

        $exception = new \Aws\Exception\AwsException(
            'Endpoint is disabled',
            new \Aws\Command('Publish'),
            [
                'code' => 'EndpointDisabled',
                'request_id' => 'req-123',
                'type' => 'client',
            ]
        );

        $this->snsService->shouldReceive('publish')
            ->once()
            ->andThrow($exception);

        $result = $this->sns->sendPush($message, $endpointArn);

        $this->assertIsArray($result);
        $this->assertEquals('EndpointDisabled', $result['error']);
        $this->assertEquals($endpointArn, $result['endpoint']);
    }

    public function test_it_can_create_platform_endpoint()
    {
        $token = 'device-token-123';
        $platformArn = 'arn:aws:sns:us-east-1:123456789012:app/APNS/MyApp';
        $customUserData = 'user-123';

        $this->snsService->shouldReceive('createPlatformEndpoint')
            ->atLeast()
            ->once()
            ->with([
                'Token' => $token,
                'PlatformApplicationArn' => $platformArn,
                'CustomUserData' => $customUserData,
            ])
            ->andReturn(new Result(['EndpointArn' => 'arn:aws:sns:us-east-1:123456789012:endpoint/APNS/MyApp/12345678-1234-1234-1234-123456789012']));

        $result = $this->sns->createPlatformEndpoint($token, $platformArn, $customUserData);
        $this->assertInstanceOf(Result::class, $result);
    }

    public function test_it_can_set_endpoint_attributes()
    {
        $endpointArn = 'arn:aws:sns:us-east-1:123456789012:endpoint/APNS/MyApp/12345678-1234-1234-1234-123456789012';
        $attributes = [
            'Token' => 'new-device-token',
            'Enabled' => 'True',
        ];

        $this->snsService->shouldReceive('setEndpointAttributes')
            ->atLeast()
            ->once()
            ->with([
                'EndpointArn' => $endpointArn,
                'Attributes' => $attributes,
            ])
            ->andReturn(new Result);

        $result = $this->sns->setEndpointAttributes($endpointArn, $attributes);
        $this->assertInstanceOf(Result::class, $result);
    }

    public function test_it_can_register_endpoint_successfully()
    {
        $token = 'device-token-123';
        $platformArn = 'arn:aws:sns:us-east-1:123456789012:app/APNS/MyApp';
        $expectedEndpointArn = 'arn:aws:sns:us-east-1:123456789012:endpoint/APNS/MyApp/12345678-1234-1234-1234-123456789012';

        $this->snsService->shouldReceive('createPlatformEndpoint')
            ->atLeast()
            ->once()
            ->with([
                'Token' => $token,
                'PlatformApplicationArn' => $platformArn,
            ])
            ->andReturn(new Result(['EndpointArn' => $expectedEndpointArn]));

        $result = $this->sns->registerEndpoint($token, $platformArn);
        $this->assertEquals($expectedEndpointArn, $result);
    }

    public function test_it_can_handle_existing_endpoint_during_registration()
    {
        $token = 'device-token-123';
        $platformArn = 'arn:aws:sns:us-east-1:123456789012:app/APNS/MyApp';
        $existingEndpointArn = 'arn:aws:sns:us-east-1:123456789012:endpoint/APNS/MyApp/12345678-1234-1234-1234-123456789012';

        // Mock the exception for existing endpoint
        $exception = Mockery::mock(\Aws\Exception\AwsException::class);
        $exception->shouldReceive('getAwsErrorMessage')
            ->andReturn('endpoint ' . $existingEndpointArn . ' already exists with the same token');

        $this->snsService->shouldReceive('createPlatformEndpoint')
            ->atLeast()
            ->once()
            ->andThrow($exception);

        $this->snsService->shouldReceive('setEndpointAttributes')
            ->atLeast()
            ->once()
            ->with([
                'EndpointArn' => $existingEndpointArn,
                'Attributes' => [
                    'Token' => $token,
                    'CustomUserData' => '',
                    'Enabled' => 'True'
                ],
            ])
            ->andReturn(new Result);

        $result = $this->sns->registerEndpoint($token, $platformArn);
        $this->assertEquals($existingEndpointArn, $result);
    }
}
