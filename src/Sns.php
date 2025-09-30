<?php

namespace NotificationChannels\AwsSns;

use Aws\Result;
use Aws\Sns\SnsClient;

class Sns
{
    /**
     * Create a new instance of the class.
     */
    public function __construct(protected SnsClient $sns)
    {
        //
    }

    /**
     * Send the message to the given destination(s).
     * Accepts a single destination string or an array of destination strings.
     * Automatically detects if it's SMS or Push based on message type.
     *
     * @return Result|array<Result>
     *
     * @throws Aws\Exception\AwsException
     */
    public function send($message, string|array $destination): Result|array
    {
        // If the message is the unified SnsMessage, route based on destination type(s)
        if ($message instanceof SnsMessage) {
            if (is_array($destination)) {
                $results = [];
                foreach ($destination as $singleDestination) {
                    $results[] = $this->send($message, $singleDestination);
                }
                return $results;
            }

            // Decide by destination hint: E.164 phone vs ARN
            if (str_starts_with($destination, 'arn:aws:sns:')) {
                if (! $message->getPush()) {
                    throw new \InvalidArgumentException('Push message required to send to endpoint ARN');
                }
                return $this->sendPush($message->getPush(), $destination);
            }

            if (! $message->getSms()) {
                throw new \InvalidArgumentException('SMS message required to send to phone number');
            }
            return $this->sendSms($message->getSms(), $destination);
        }

        if (is_array($destination)) {
            $results = [];
            foreach ($destination as $singleDestination) {
                $results[] = $this->send($message, $singleDestination);
            }

            return $results;
        }

        if ($message->isSms()) {
            return $this->sendSms($message, $destination);
        } elseif ($message->isPush()) {
            return $this->sendPush($message, $destination);
        }

        throw new \InvalidArgumentException('Unsupported message type');
    }

    /**
     * Send the SMS message to the given E.164 destination phone number.
     *
     * @throws Aws\Exception\AwsException
     */
    public function sendSms(SnsSMSMessage $message, string $destination): Result
    {
        $attributes = [
            'AWS.SNS.SMS.SMSType' => [
                'DataType' => 'String',
                'StringValue' => $message->getDeliveryType(),
            ],
        ];

        if (! empty($message->getSender())) {
            $attributes += [
                'AWS.SNS.SMS.SenderID' => [
                    'DataType' => 'String',
                    'StringValue' => $message->getSender(),
                ],
            ];
        }

        if (! empty($message->getOriginationNumber())) {
            $attributes += [
                'AWS.MM.SMS.OriginationNumber' => [
                    'DataType' => 'String',
                    'StringValue' => $message->getOriginationNumber(),
                ],
            ];
        }

        $parameters = [
            'Message' => $message->getBody(),
            'PhoneNumber' => $destination,
            'MessageAttributes' => $attributes,
        ];

        return $this->sns->publish($parameters);
    }

    /**
     * Send the push message to the given endpoint ARN.
     *
     * @throws Aws\Exception\AwsException
     */
    /**
     * @return Result|array
     */
    public function sendPush(SnsPushMessage $message, string $endpointArn): Result|array
    {
        $parameters = [
            'Message' => $message->buildPayload(),
            'MessageStructure' => 'json',
            'MessageAttributes' => $message->buildMessageAttributes(),
            'TargetArn' => $endpointArn,
        ];

        try {
            return $this->sns->publish($parameters);
        } catch (\Aws\Exception\AwsException $e) {
            $errorCode = $e->getAwsErrorCode();

            if ($errorCode === 'EndpointDisabled') {
                return [
                    'error' => $errorCode,
                    'message' => $e->getAwsErrorMessage(),
                    'request_id' => method_exists($e, 'getAwsRequestId') ? $e->getAwsRequestId() : null,
                    'endpoint' => $endpointArn,
                ];
            }

            throw $e;
        }
    }

    /**
     * Create a platform endpoint for push notifications.
     *
     * @throws Aws\Exception\AwsException
     */
    public function createPlatformEndpoint(string $token, string $platformArn, ?string $customUserData = null): Result
    {
        $parameters = [
            'Token' => $token,
            'PlatformApplicationArn' => $platformArn,
        ];

        if ($customUserData !== null) {
            $parameters['CustomUserData'] = $customUserData;
        }

        return $this->sns->createPlatformEndpoint($parameters);
    }

    /**
     * Set endpoint attributes for push notifications.
     *
     * @throws Aws\Exception\AwsException
     */
    public function setEndpointAttributes(string $endpointArn, array $attributes): Result
    {
        return $this->sns->setEndpointAttributes([
            'EndpointArn' => $endpointArn,
            'Attributes' => $attributes,
        ]);
    }

    /**
     * Register or update a platform endpoint for push notifications.
     * This method handles the case where an endpoint already exists with the same token.
     *
     * @throws Aws\Exception\AwsException
     */
    public function registerEndpoint(string $token, string $platformArn, ?string $customUserData = null): ?string
    {
        try {
            $result = $this->createPlatformEndpoint($token, $platformArn, $customUserData);
            return $result['EndpointArn'];
        } catch (\Aws\Exception\AwsException $e) {
            // Check if endpoint already exists with the same token
            $errorMessage = $e->getAwsErrorMessage();
            if (preg_match('/endpoint (arn:aws:sns[^ ]+) already exists with the same token/i', $errorMessage, $matches)) {
                $endpointArn = $matches[1];
                
                // Update the existing endpoint
                $this->setEndpointAttributes($endpointArn, [
                    'Token' => $token,
                    'CustomUserData' => $customUserData ?? '',
                    'Enabled' => 'True'
                ]);
                
                return $endpointArn;
            }
            
            // Re-throw if it's a different error
            throw $e;
        }
    }
}
