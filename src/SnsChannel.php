<?php

namespace NotificationChannels\AwsSns;

use Aws\Result;
use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Notification;
use NotificationChannels\AwsSns\Exceptions\CouldNotSendNotification;

class SnsChannel
{
    public function __construct(protected Sns $sns, protected Dispatcher $events)
    {
        //
    }

    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification): ?Result
    {
        try {
            $destination = $this->getDestination($notifiable, $notification);
            $message = $this->getMessage($notifiable, $notification);

            // If unified message, route child per destination type for single destination
            if ($message instanceof SnsMessage && is_string($destination)) {
                if (str_starts_with($destination, 'arn:aws:sns:')) {
                    $child = $message->getPush();
                } else {
                    $child = $message->getSms();
                }

                $result = $this->sns->send($child, $destination);
            } else {
                $result = $this->sns->send($message, $destination);
            }

            if (is_array($result)) {
                // Return the last result if multiple destinations were provided
                return empty($result) ? null : end($result);
            }

            return $result;
        } catch (Exception $e) {
            $this->events->dispatch(new NotificationFailed(
                $notifiable,
                $notification,
                'sns',
                ['message' => $e->getMessage(), 'exception' => $e]
            ));

            return null;
        }
    }

    /**
     * Get the destination (phone number or endpoint ARN) to send a notification to.
     *
     * @throws CouldNotSendNotification
     */
    protected function getDestination($notifiable, Notification $notification)
    {
        if ($to = $notifiable->routeNotificationFor('sns', $notification)) {
            return $to;
        }

        return $this->guessDestination($notifiable, $notification);
    }

    /**
     * Try to get the destination from some commonly used attributes.
     * For SMS: looks for phone-related attributes
     * For Push: looks for endpoint ARN or device token attributes
     *
     * @throws CouldNotSendNotification
     */
    protected function guessDestination($notifiable, Notification $notification)
    {
        $message = $this->getMessage($notifiable, $notification);

        // Collect possible destinations
        $destinations = [];

        // Phone destination only if message supports SMS
        $phone = $this->tryGuess($fn = fn() => $this->guessPhoneDestination($notifiable));
        if ($phone !== null) {
            if (($message instanceof SnsSMSMessage) || ($message instanceof SnsMessage && $message->getSms())) {
                $destinations[] = $phone;
            }
        }

        // Endpoint destination only if message supports Push
        $endpoint = $this->tryGuess($fn = fn() => $this->guessPushDestination($notifiable));
        if ($endpoint !== null) {
            if (($message instanceof SnsPushMessage) || ($message instanceof SnsMessage && $message->getPush())) {
                $destinations[] = $endpoint;
            }
        }

        if (count($destinations) === 1) {
            return $destinations[0];
        }

        if (count($destinations) > 1) {
            return $destinations;
        }

        throw CouldNotSendNotification::invalidReceiver();
    }

    /**
     * Helper to attempt a guess without throwing.
     */
    private function tryGuess(callable $callback)
    {
        try {
            return $callback();
        } catch (CouldNotSendNotification $e) {
            return null;
        }
    }

    /**
     * Try to get the phone number from some commonly used attributes.
     *
     * @throws CouldNotSendNotification
     */
    protected function guessPhoneDestination($notifiable)
    {
        $commonAttributes = ['phone', 'phone_number', 'full_phone'];
        foreach ($commonAttributes as $attribute) {
            if (isset($notifiable->{$attribute})) {
                return $notifiable->{$attribute};
            }
        }

        throw CouldNotSendNotification::invalidReceiver();
    }

    /**
     * Try to get the endpoint ARN from some commonly used attributes.
     *
     * @throws CouldNotSendNotification
     */
    protected function guessPushDestination($notifiable)
    {
        $commonAttributes = ['sns_endpoint_arn', 'endpoint_arn', 'endpoint', 'device_endpoint', 'push_endpoint'];
        foreach ($commonAttributes as $attribute) {
            if (isset($notifiable->{$attribute})) {
                return $notifiable->{$attribute};
            }
        }

        throw CouldNotSendNotification::invalidReceiver();
    }

    /**
     * Get the SNS Message object.
     *
     * @throws CouldNotSendNotification
     */
    protected function getMessage($notifiable, Notification $notification)
    {
        $message = $notification->toSns($notifiable);
        if (is_string($message)) {
            // Convert plain string to unified message with SMS child
            return SnsMessage::create()->sms(SnsSMSMessage::create(['body' => $message]));
        }

        if ($message instanceof SnsMessage || $message instanceof SnsSMSMessage || $message instanceof SnsPushMessage) {
            // For backward compatibility, wrap single child into unified message
            if ($message instanceof SnsSMSMessage) {
                return SnsMessage::create()->sms($message);
            }
            if ($message instanceof SnsPushMessage) {
                return SnsMessage::create()->push($message);
            }
            return $message;
        }

        throw CouldNotSendNotification::invalidMessageObject($message);
    }
}
