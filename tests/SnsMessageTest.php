<?php

namespace NotificationChannels\AwsSns\Test;

use NotificationChannels\AwsSns\SnsSMSMessage;

class SnsMessageTest extends TestCase
{
    public function test_it_can_accept_a_plain_string_when_constructing_a_message()
    {
        $message = new SnsSMSMessage('Do not touch my booty');
        $this->assertEquals('Do not touch my booty', $message->getBody());
    }

    public function test_it_can_accept_some_initial_content_when_constructing_a_message()
    {
        $message = new SnsSMSMessage(['body' => 'My message body']);
        $this->assertEquals('My message body', $message->getBody());
    }

    public function test_it_provides_a_create_method()
    {
        $message = SnsSMSMessage::create(['body' => 'My body from create']);
        $this->assertEquals('My body from create', $message->getBody());
    }

    public function test_the_body_content_can_be_set_using_a_proper_method()
    {
        $message = SnsSMSMessage::create();
        $this->assertEmpty($message->getBody());
        $message->body('The brand new body');
        $this->assertEquals('The brand new body', $message->getBody());
    }

    public function test_the_default_sms_delivery_type_is_promotional()
    {
        $message = SnsSMSMessage::create();
        $this->assertEquals('Promotional', $message->getDeliveryType());
    }

    public function test_the_sms_delivery_type_can_be_changed_using_a_proper_method()
    {
        $message = SnsSMSMessage::create()->transactional();
        $this->assertEquals('Transactional', $message->getDeliveryType());
    }

    public function test_the_sms_delivery_type_can_be_explicitly_as_promotional()
    {
        $message = SnsSMSMessage::create()->promotional();
        $this->assertEquals('Promotional', $message->getDeliveryType());
    }

    public function test_the_default_sms_sender_id_is_empty()
    {
        $message = SnsSMSMessage::create();
        $this->assertEmpty($message->getSender());
    }

    public function test_the_sms_sender_id_can_be_changed_using_a_proper_method()
    {
        $message = SnsSMSMessage::create()->sender('Test');
        $this->assertEquals('Test', $message->getSender());
    }

    public function test_it_can_accept_all_the_contents_when_constructing_a_message()
    {
        $message = SnsSMSMessage::create([
            'body' => 'My mass body',
            'transactional' => true,
            'sender' => 'Test',
        ]);
        $this->assertEquals('My mass body', $message->getBody());
        $this->assertEquals('Transactional', $message->getDeliveryType());
        $this->assertEquals('Test', $message->getSender());
    }

    public function test_it_can_send_sms_message_with_origination_number()
    {
        $originationNumber = '+13347814073';
        $message = SnsSMSMessage::create([
            'body' => 'Message text',
            'sender' => 'Test',
            'originationNumber' => $originationNumber,
        ]);

        $this->assertEquals('Message text', $message->getBody());
        $this->assertEquals('Test', $message->getSender());
        $this->assertEquals($originationNumber, $message->getOriginationNumber());
    }
}
