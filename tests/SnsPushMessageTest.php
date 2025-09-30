<?php

namespace NotificationChannels\AwsSns\Test;

use NotificationChannels\AwsSns\SnsPushMessage;

class SnsPushMessageTest extends TestCase
{
    public function test_it_can_accept_a_plain_string_when_constructing_a_message()
    {
        $message = new SnsPushMessage('Hello World');
        $this->assertEquals('Hello World', $message->getBody());
    }

    public function test_it_can_accept_some_initial_content_when_constructing_a_message()
    {
        $message = new SnsPushMessage(['body' => 'My message body']);
        $this->assertEquals('My message body', $message->getBody());
    }

    public function test_it_provides_a_create_method()
    {
        $message = SnsPushMessage::create(['body' => 'My body from create']);
        $this->assertEquals('My body from create', $message->getBody());
    }

    public function test_the_body_content_can_be_set_using_a_proper_method()
    {
        $message = SnsPushMessage::create();
        $this->assertEmpty($message->getBody());
        $message->body('The brand new body');
        $this->assertEquals('The brand new body', $message->getBody());
    }

    public function test_the_title_can_be_set_using_a_proper_method()
    {
        $message = SnsPushMessage::create();
        $this->assertEmpty($message->getTitle());
        $message->title('The title');
        $this->assertEquals('The title', $message->getTitle());
    }

    public function test_the_subtitle_can_be_set_using_a_proper_method()
    {
        $message = SnsPushMessage::create();
        $this->assertEmpty($message->getSubtitle());
        $message->subtitle('The subtitle');
        $this->assertEquals('The subtitle', $message->getSubtitle());
    }

    public function test_the_badge_can_be_set_using_a_proper_method()
    {
        $message = SnsPushMessage::create();
        $this->assertEquals(0, $message->getBadge());
        $message->badge(5);
        $this->assertEquals(5, $message->getBadge());
    }

    public function test_the_sound_can_be_set_using_a_proper_method()
    {
        $message = SnsPushMessage::create();
        $this->assertEquals('default', $message->getSound());
        $message->sound('custom.wav');
        $this->assertEquals('custom.wav', $message->getSound());
    }


    public function test_the_ttl_can_be_set_using_a_proper_method()
    {
        $message = SnsPushMessage::create();
        $this->assertNull($message->getTtl());
        $message->ttl(3600);
        $this->assertEquals(3600, $message->getTtl());
    }

    public function test_the_data_can_be_set_using_a_proper_method()
    {
        $message = SnsPushMessage::create();
        $this->assertEmpty($message->getData());
        $data = ['order_id' => 12345, 'url' => 'https://example.com'];
        $message->data($data);
        $this->assertEquals($data, $message->getData());
    }

    public function test_it_can_accept_all_the_contents_when_constructing_a_message()
    {
        $message = SnsPushMessage::create([
            'body' => 'My message body',
            'title' => 'My title',
            'subtitle' => 'My subtitle',
            'badge' => 3,
            'sound' => 'custom.wav',
            'ttl' => 3600,
            'data' => ['order_id' => 12345],
        ]);
        
        $this->assertEquals('My message body', $message->getBody());
        $this->assertEquals('My title', $message->getTitle());
        $this->assertEquals('My subtitle', $message->getSubtitle());
        $this->assertEquals(3, $message->getBadge());
        $this->assertEquals('custom.wav', $message->getSound());
        $this->assertEquals(3600, $message->getTtl());
        $this->assertEquals(['order_id' => 12345], $message->getData());
    }

    public function test_it_identifies_as_push_message()
    {
        $message = SnsPushMessage::create();
        $this->assertTrue($message->isPush());
        $this->assertFalse($message->isSms());
    }

    public function test_it_builds_apns_payload_correctly()
    {
        $message = SnsPushMessage::create([
            'body' => 'Hello World',
            'title' => 'Test Title',
            'subtitle' => 'Test Subtitle',
            'badge' => 5,
            'sound' => 'custom.wav',
            'data' => ['order_id' => 12345, 'url' => 'https://example.com'],
        ]);

        $apnsPayload = $message->buildApnsPayload();

        $this->assertEquals('Hello World', $apnsPayload['aps']['alert']['body']);
        $this->assertEquals('Test Title', $apnsPayload['aps']['alert']['title']);
        $this->assertEquals('Test Subtitle', $apnsPayload['aps']['alert']['subtitle']);
        $this->assertEquals(5, $apnsPayload['aps']['badge']);
        $this->assertEquals('custom.wav', $apnsPayload['aps']['sound']);
        $this->assertEquals(12345, $apnsPayload['order_id']);
        $this->assertEquals('https://example.com', $apnsPayload['url']);
    }

    public function test_it_builds_gcm_payload_correctly()
    {
        $message = SnsPushMessage::create([
            'body' => 'Hello World',
            'title' => 'Test Title',
            'data' => ['order_id' => 12345, 'url' => 'https://example.com'],
        ]);

        $gcmPayload = $message->buildGcmPayload();

        $this->assertEquals('Test Title', $gcmPayload['notification']['title']);
        $this->assertEquals('Hello World', $gcmPayload['notification']['body']);
        $this->assertEquals(12345, $gcmPayload['data']['order_id']);
        $this->assertEquals('https://example.com', $gcmPayload['data']['url']);
    }

    public function test_it_builds_complete_sns_payload_correctly()
    {
        $message = SnsPushMessage::create([
            'body' => 'Hello World',
            'title' => 'Test Title',
        ]);

        $payload = $message->buildPayload();
        $decodedPayload = json_decode($payload, true);

        $this->assertEquals('Hello World', $decodedPayload['default']);
        $this->assertArrayHasKey('APNS', $decodedPayload);
        $this->assertArrayHasKey('APNS_SANDBOX', $decodedPayload);
        $this->assertArrayHasKey('GCM', $decodedPayload);
    }

    public function test_it_builds_message_attributes_correctly()
    {
        $message = SnsPushMessage::create(['ttl' => 3600]);
        $attributes = $message->buildMessageAttributes();

        $this->assertArrayHasKey('AWS.SNS.MOBILE.APNS.TTL', $attributes);
        $this->assertEquals('String', $attributes['AWS.SNS.MOBILE.APNS.TTL']['DataType']);
        $this->assertEquals('3600', $attributes['AWS.SNS.MOBILE.APNS.TTL']['StringValue']);
    }

    public function test_it_builds_empty_message_attributes_when_no_ttl()
    {
        $message = SnsPushMessage::create();
        $attributes = $message->buildMessageAttributes();

        $this->assertEmpty($attributes);
    }

}
