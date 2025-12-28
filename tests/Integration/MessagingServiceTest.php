<?php

declare(strict_types=1);

namespace JardisCore\Messaging\Tests\Integration;

use JardisCore\Messaging\Handler\CallbackHandler;
use JardisCore\Messaging\MessageConsumer;
use JardisCore\Messaging\MessagePublisher;
use JardisCore\Messaging\MessagingService;
use PHPUnit\Framework\TestCase;

class MessagingServiceTest extends TestCase
{
    public function testPublishWithRedis(): void
    {
        $service = new MessagingService(
            publisherFactory: fn() => (new MessagePublisher())->setRedis($_ENV['REDIS_HOST'] ?? 'redis'),
            consumerFactory: fn() => (new MessageConsumer())->setRedis($_ENV['REDIS_HOST'] ?? 'redis')
        );

        $result = $service->publish('test.service.channel', 'Test message from MessagingService');

        $this->assertTrue($result);
    }

    public function testPublishArrayWithRedis(): void
    {
        $service = new MessagingService(
            publisherFactory: fn() => (new MessagePublisher())->setRedis($_ENV['REDIS_HOST'] ?? 'redis'),
            consumerFactory: fn() => (new MessageConsumer())->setRedis($_ENV['REDIS_HOST'] ?? 'redis')
        );

        $result = $service->publish('test.service.array', [
            'type' => 'order',
            'id' => 12345,
            'amount' => 99.99
        ]);

        $this->assertTrue($result);
    }

    public function testPublishWithFallback(): void
    {
        $service = new MessagingService(
            publisherFactory: fn() => (new MessagePublisher())->setRedis($_ENV['REDIS_HOST'] ?? 'redis'),
            consumerFactory: fn() => (new MessageConsumer())->setRedis($_ENV['REDIS_HOST'] ?? 'redis')
        );

        $result = $service->publish('test.service.fallback', 'Fallback test message');

        $this->assertTrue($result);
    }

    public function testGetPublisher(): void
    {
        $service = new MessagingService(
            publisherFactory: fn() => (new MessagePublisher())->setRedis($_ENV['REDIS_HOST'] ?? 'redis'),
            consumerFactory: fn() => (new MessageConsumer())->setRedis($_ENV['REDIS_HOST'] ?? 'redis')
        );

        $retrievedPublisher = $service->getPublisher();

        $this->assertInstanceOf(MessagePublisher::class, $retrievedPublisher);
    }

    public function testGetConsumer(): void
    {
        $service = new MessagingService(
            publisherFactory: fn() => (new MessagePublisher())->setRedis($_ENV['REDIS_HOST'] ?? 'redis'),
            consumerFactory: fn() => (new MessageConsumer())->setRedis($_ENV['REDIS_HOST'] ?? 'redis')
        );

        $retrievedConsumer = $service->getConsumer();

        $this->assertInstanceOf(MessageConsumer::class, $retrievedConsumer);
    }

    public function testLazyLoadingPublisher(): void
    {
        $publisherCreated = false;

        $service = new MessagingService(
            publisherFactory: function () use (&$publisherCreated) {
                $publisherCreated = true;
                return (new MessagePublisher())->setRedis($_ENV['REDIS_HOST'] ?? 'redis');
            },
            consumerFactory: fn() => (new MessageConsumer())->setRedis($_ENV['REDIS_HOST'] ?? 'redis')
        );

        // Publisher should not be created yet
        $this->assertFalse($publisherCreated);

        // Call publish - now publisher should be created
        $service->publish('test.lazy.publisher', 'test');

        $this->assertTrue($publisherCreated);
    }

    public function testLazyLoadingConsumer(): void
    {
        $consumerCreated = false;

        $service = new MessagingService(
            publisherFactory: fn() => (new MessagePublisher())->setRedis($_ENV['REDIS_HOST'] ?? 'redis'),
            consumerFactory: function () use (&$consumerCreated) {
                $consumerCreated = true;
                return (new MessageConsumer())->setRedis($_ENV['REDIS_HOST'] ?? 'redis');
            }
        );

        // Consumer should not be created yet
        $this->assertFalse($consumerCreated);

        // Call getConsumer - now consumer should be created
        $service->getConsumer();

        $this->assertTrue($consumerCreated);
    }

    public function testPublishAndConsumeRoundTrip(): void
    {
        $service = new MessagingService(
            publisherFactory: fn() => (new MessagePublisher())->setRedis($_ENV['REDIS_HOST'] ?? 'redis'),
            consumerFactory: fn() => (new MessageConsumer())->setRedis($_ENV['REDIS_HOST'] ?? 'redis')
        );

        // Publish message
        $testMessage = ['user' => 'test', 'action' => 'roundtrip', 'timestamp' => time()];
        $service->publish('test.service.roundtrip', $testMessage);

        // Consume message
        $receivedMessage = null;
        $handler = new CallbackHandler(function ($message) use (&$receivedMessage, $service) {
            $receivedMessage = $message;
            $service->getConsumer()->stop();
            return true;
        });

        $service->consume('test.service.roundtrip', $handler, [
            'group' => 'test-roundtrip-group',
            'consumer' => 'test-consumer'
        ]);

        $this->assertNotNull($receivedMessage);
        $this->assertIsArray($receivedMessage);
        $this->assertEquals('test', $receivedMessage['user']);
        $this->assertEquals('roundtrip', $receivedMessage['action']);
    }
}
