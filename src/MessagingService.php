<?php

declare(strict_types=1);

namespace JardisCore\Messaging;

use Closure;
use JardisPsr\Messaging\Exception\ConnectionException;
use JardisPsr\Messaging\Exception\PublishException;
use JardisPsr\Messaging\MessageConsumerInterface;
use JardisPsr\Messaging\MessageHandlerInterface;
use JardisPsr\Messaging\MessagePublisherInterface;
use JardisPsr\Messaging\MessagingServiceInterface;

/**
 * Messaging service wrapper with lazy loading
 *
 * Provides a unified interface for both publishing and consuming messages
 * Publisher and Consumer are created lazily only when needed
 *
 * Usage in DDD context:
 * $messaging = new MessagingService(
 *     publisherFactory: fn() => (new MessagePublisher())->setRedis('localhost'),
 *     consumerFactory: fn() => (new MessageConsumer())->setRedis('localhost')
 * );
 * $messaging->publish('topic', $data);  // Publisher created here on first use
 * $messaging->consume('topic', $handler); // Consumer created here on first use
 *
 * Note: Will implement MessagingServiceInterface once it's available in jardispsr/messaging
 */
final class MessagingService implements MessagingServiceInterface
{
    private ?MessagePublisherInterface $publisher = null;
    private ?MessageConsumerInterface $consumer = null;

    /**
     * @param Closure(): MessagePublisherInterface $publisherFactory Factory closure to create publisher
     * @param Closure(): MessageConsumerInterface $consumerFactory Factory closure to create consumer
     */
    public function __construct(
        private readonly Closure $publisherFactory,
        private readonly Closure $consumerFactory
    ) {
    }

    /**
     * Publish a message to the specified topic/channel/queue
     *
     * Lazily creates the publisher on first call
     *
     * @param string $topic The topic, channel or queue name
     * @param string|object|array<mixed> $message The message payload
     * @param array<string, mixed> $options Publisher-specific options
     * @return bool True on success
     * @throws PublishException if publishing fails
     * @throws ConnectionException
     */
    public function publish(string $topic, string|object|array $message, array $options = []): bool
    {
        if ($this->publisher === null) {
            $this->publisher = ($this->publisherFactory)();
        }

        return $this->publisher->publish($topic, $message, $options);
    }

    /**
     * Start consuming messages with a handler
     *
     * Lazily creates the consumer on first call
     *
     * @param string $topic The topic, channel or queue name
     * @param MessageHandlerInterface $handler Message handler
     * @param array<string, mixed> $options Consumer-specific options
     * @throws \JardisPsr\Messaging\Exception\ConsumerException if consuming fails
     */
    public function consume(string $topic, MessageHandlerInterface $handler, array $options = []): void
    {
        if ($this->consumer === null) {
            $this->consumer = ($this->consumerFactory)();
        }

        $this->consumer->consume($topic, $handler, $options);
    }

    /**
     * Get the underlying publisher instance (creates it if not yet instantiated)
     *
     * @return MessagePublisherInterface
     */
    public function getPublisher(): MessagePublisherInterface
    {
        if ($this->publisher === null) {
            $this->publisher = ($this->publisherFactory)();
        }

        return $this->publisher;
    }

    /**
     * Get the underlying consumer instance (creates it if not yet instantiated)
     *
     * @return MessageConsumerInterface
     */
    public function getConsumer(): MessageConsumerInterface
    {
        if ($this->consumer === null) {
            $this->consumer = ($this->consumerFactory)();
        }

        return $this->consumer;
    }
}
