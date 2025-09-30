<?php

namespace NotificationChannels\AwsSns;

class SnsPushMessage
{
    /**
     * The body of the message.
     *
     * @var string
     */
    protected $body = '';

    /**
     * The title of the message.
     *
     * @var string
     */
    protected $title = '';

    /**
     * The subtitle of the message.
     *
     * @var string
     */
    protected $subtitle = '';

    /**
     * The badge count for the notification.
     *
     * @var int
     */
    protected $badge = 0;

    /**
     * The sound for the notification.
     *
     * @var string
     */
    protected $sound = 'default';


    /**
     * The TTL (Time To Live) for the notification in seconds.
     *
     * @var int|null
     */
    protected $ttl = null;

    /**
     * Custom data to include in the notification payload.
     *
     * @var array
     */
    protected $data = [];

    public function __construct($content = [])
    {
        if (is_string($content)) {
            $this->body($content);
        }

        if (is_array($content)) {
            foreach ($content as $property => $value) {
                if (method_exists($this, $property)) {
                    $this->{$property}($value);
                }
            }
        }
    }

    /**
     * Creates a new instance of the push message.
     *
     * @return SnsPushMessage
     */
    public static function create(array $data = [])
    {
        return new self($data);
    }

    /**
     * Sets the message body.
     *
     * @return $this
     */
    public function body(string $content)
    {
        $this->body = trim($content);

        return $this;
    }

    /**
     * Get the message body.
     *
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Sets the message title.
     *
     * @return $this
     */
    public function title(string $title)
    {
        $this->title = trim($title);

        return $this;
    }

    /**
     * Get the message title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Sets the message subtitle.
     *
     * @return $this
     */
    public function subtitle(string $subtitle)
    {
        $this->subtitle = trim($subtitle);

        return $this;
    }

    /**
     * Get the message subtitle.
     *
     * @return string
     */
    public function getSubtitle()
    {
        return $this->subtitle;
    }

    /**
     * Sets the badge count.
     *
     * @return $this
     */
    public function badge(int $badge)
    {
        $this->badge = $badge;

        return $this;
    }

    /**
     * Get the badge count.
     *
     * @return int
     */
    public function getBadge()
    {
        return $this->badge;
    }

    /**
     * Sets the sound for the notification.
     *
     * @return $this
     */
    public function sound(string $sound)
    {
        $this->sound = $sound;

        return $this;
    }

    /**
     * Get the sound for the notification.
     *
     * @return string
     */
    public function getSound()
    {
        return $this->sound;
    }


    /**
     * Sets the TTL (Time To Live) for the notification.
     *
     * @return $this
     */
    public function ttl(?int $ttl)
    {
        $this->ttl = $ttl;

        return $this;
    }

    /**
     * Get the TTL (Time To Live) for the notification.
     *
     * @return int|null
     */
    public function getTtl()
    {
        return $this->ttl;
    }

    /**
     * Sets custom data for the notification.
     *
     * @return $this
     */
    public function data(array $data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get custom data for the notification.
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Build the APNS payload.
     *
     * @return array
     */
    public function buildApnsPayload()
    {
        $message = $this->getBody();
        $title = $this->getTitle();

        $apsAlert = [
            'body' => $message
        ];

        if (!empty($title)) {
            $apsAlert['title'] = $title;
        }

        if (!empty($this->getSubtitle())) {
            $apsAlert['subtitle'] = $this->getSubtitle();
        }

        $payload = [
            'aps' => [
                'alert' => $apsAlert,
                'sound' => $this->getSound(),
                'badge' => $this->getBadge(),
            ]
        ];

        // Add custom data to the payload
        if (!empty($this->getData())) {
            $payload = array_merge($payload, $this->getData());
        }

        return $payload;
    }

    /**
     * Build the GCM payload.
     *
     * @return array
     */
    public function buildGcmPayload()
    {
        $message = $this->getBody();
        $title = $this->getTitle();

        $payload = [
            'notification' => [
                'title' => $title,
                'body' => $message
            ]
        ];

        // Add custom data to the payload
        if (!empty($this->getData())) {
            $payload['data'] = $this->getData();
        }

        return $payload;
    }

    /**
     * Build the complete SNS message payload.
     *
     * @return string
     */
    public function buildPayload()
    {
        $message = $this->getBody();
        $apnsPayload = $this->buildApnsPayload();
        $gcmPayload = $this->buildGcmPayload();

        $payload = [
            'default' => $message,
            'APNS' => json_encode($apnsPayload),
            'APNS_SANDBOX' => json_encode($apnsPayload),
            'GCM' => json_encode($gcmPayload)
        ];

        return json_encode($payload);
    }

    /**
     * Build the message attributes for SNS.
     *
     * @return array
     */
    public function buildMessageAttributes()
    {
        $attributes = [];
        
        if ($this->getTtl() !== null) {
            $attributes['AWS.SNS.MOBILE.APNS.TTL'] = [
                'DataType' => 'String',
                'StringValue' => (string) $this->getTtl()
            ];
        }
        
        return $attributes;
    }

    /**
     * Check if this is an SMS message.
     *
     * @return bool
     */
    public function isSms()
    {
        return false;
    }

    /**
     * Check if this is a push message.
     *
     * @return bool
     */
    public function isPush()
    {
        return true;
    }
}
