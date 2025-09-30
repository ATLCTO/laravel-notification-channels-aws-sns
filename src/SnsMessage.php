<?php

namespace NotificationChannels\AwsSns;

class SnsMessage
{
    /**
     * @var SnsSMSMessage|null
     */
    protected $sms = null;

    /**
     * @var SnsPushMessage|null
     */
    protected $push = null;

    public function __construct()
    {
        //
    }

    public static function create()
    {
        return new self();
    }

    /**
     * Set the SMS child message.
     */
    public function sms(SnsSMSMessage $sms)
    {
        $this->sms = $sms;

        return $this;
    }

    /**
     * Get the SMS child message.
     */
    public function getSms(): ?SnsSMSMessage
    {
        return $this->sms;
    }

    /**
     * Set the Push child message.
     */
    public function push(SnsPushMessage $push)
    {
        $this->push = $push;

        return $this;
    }

    /**
     * Get the Push child message.
     */
    public function getPush(): ?SnsPushMessage
    {
        return $this->push;
    }
}


