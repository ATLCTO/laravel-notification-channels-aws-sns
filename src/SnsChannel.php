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

            $result = $this->sns->send($message, $destination);

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
        
        if ($message->isSms()) {
            return $this->guessPhoneDestination($notifiable);
        } elseif ($message->isPush()) {
            return $this->guessPushDestination($notifiable);
        }

        throw CouldNotSendNotification::invalidReceiver();
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
            return new SnsSMSMessage($message);
        }

        if ($message instanceof SnsSMSMessage || $message instanceof SnsPushMessage) {
            return $message;
        }

        throw CouldNotSendNotification::invalidMessageObject($message);
    }
}
