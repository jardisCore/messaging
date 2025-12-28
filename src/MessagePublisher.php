<?php

declare(strict_types=1);

namespace JardisCore\Messaging;

use JardisCore\Messaging\Factory\PublisherFactory;
use JardisPsr\Messaging\MessagePublisherInterface;
use JardisPsr\Messaging\PublisherInterface;
use JardisPsr\Messaging\Exception\PublishException;
use JardisCore\Messaging\Validation\MessageValidator;
use JsonException;

/**
 * Main message publisher class
 *
 * Provides a unified interface for publishing messages to different
 * message brokers (Redis, Kafka, RabbitMQ) with layered fallback support
 *
 * New fluent API:
 * $publisher = (new MessagePublisher())
 *     ->setRedis('localhost')
 *     ->setKafka('kafka:9092')
 *     ->publish('topic', $message);
 *
 * Legacy API (still supported):
 * $publisher = new MessagePublisher($publisherInterface);
 */
class MessagePublisher implements MessagePublisherInterface
{
    private readonly MessageValidator $validator;
    private readonly PublisherFactory $factory;

    /** @var array<array{type: string, publisher: PublisherInterface, priority: int}> */
    private array $publishers = [];

    /**
     * @param PublisherInterface|null $publisher Legacy: single publisher (optional)
     * @param MessageValidator|null $validator Message validator (auto-created if null)
     * @param PublisherFactory|null $factory Publisher factory (auto-created if null)
     */
    public function __construct(
        ?PublisherInterface $publisher = null,
        ?MessageValidator $validator = null,
        ?PublisherFactory $factory = null
    ) {
        $this->validator = $validator ?? new MessageValidator();
        $this->factory = $factory ?? new PublisherFactory();

        // Legacy support: single publisher
        if ($publisher !== null) {
            $this->publishers[] = [
                'type' => 'legacy',
                'publisher' => $publisher,
                'priority' => 0
            ];
        }
    }

    /**
     * Configure Redis publisher
     *
     * @param string $host Redis host
     * @param int $port Redis port
     * @param string|null $password Redis password
     * @param array<string, mixed> $options Additional options (useStreams, etc.)
     * @param int $priority Layer priority (lower = tried first)
     * @return self
     */
    public function setRedis(
        string $host,
        int $port = 6379,
        ?string $password = null,
        array $options = [],
        int $priority = 0
    ): self {
        $this->publishers[] = [
            'type' => 'redis',
            'publisher' => $this->factory->redis($host, $port, $password, $options),
            'priority' => $priority
        ];

        $this->sortPublishersByPriority();

        return $this;
    }

    /**
     * Configure Kafka publisher
     *
     * @param string $brokers Kafka brokers (e.g., 'kafka:9092' or 'host:port')
     * @param string|null $username SASL username
     * @param string|null $password SASL password
     * @param array<string, mixed> $options Additional Kafka options
     * @param int $priority Layer priority (lower = tried first)
     * @return self
     */
    public function setKafka(
        string $brokers,
        ?string $username = null,
        ?string $password = null,
        array $options = [],
        int $priority = 1
    ): self {
        $this->publishers[] = [
            'type' => 'kafka',
            'publisher' => $this->factory->kafka($brokers, $username, $password, $options),
            'priority' => $priority
        ];

        $this->sortPublishersByPriority();

        return $this;
    }

    /**
     * Configure RabbitMQ publisher
     *
     * @param string $host RabbitMQ host
     * @param int $port RabbitMQ port
     * @param string $username RabbitMQ username
     * @param string $password RabbitMQ password
     * @param array<string, mixed> $options Additional options
     * @param int $priority Layer priority (lower = tried first)
     * @return self
     */
    public function setRabbitMq(
        string $host,
        int $port = 5672,
        string $username = 'guest',
        string $password = 'guest',
        array $options = [],
        int $priority = 2
    ): self {
        $this->publishers[] = [
            'type' => 'rabbitmq',
            'publisher' => $this->factory->rabbitMq($host, $port, $username, $password, $options),
            'priority' => $priority
        ];

        $this->sortPublishersByPriority();

        return $this;
    }

    /**
     * Add an already instantiated publisher (e.g., for external connections)
     *
     * This method allows adding pre-configured PublisherInterface instances,
     * which is useful when working with externally managed connections
     * (e.g., legacy applications sharing their Redis/Kafka/RabbitMQ connections).
     *
     * @param PublisherInterface $publisher The publisher instance to add
     * @param string $type Type identifier (e.g., 'redis', 'kafka', 'rabbitmq', 'external')
     * @param int $priority Layer priority (lower = tried first, default: 0)
     * @return self
     *
     * @example
     * // Using external Redis connection from legacy app
     * $externalRedis = $legacyApp->getRedis();
     * $connection = new ExternalRedisConnection($externalRedis);
     * $publisher = new RedisPublisher($connection);
     *
     * $messagePublisher = (new MessagePublisher())
     *     ->addPublisher($publisher, 'redis-external', 0);
     */
    public function addPublisher(PublisherInterface $publisher, string $type, int $priority = 0): self
    {
        $this->publishers[] = [
            'type' => $type,
            'publisher' => $publisher,
            'priority' => $priority
        ];

        $this->sortPublishersByPriority();

        return $this;
    }

    /**
     * Publish a message to the specified topic/channel/queue
     *
     * Tries each configured publisher in priority order (fallback on failure)
     *
     * @param string $topic The topic, channel or queue name
     * @param string|object|array<mixed> $message The message payload (strings passed as-is, arrays encoded to JSON)
     * @param array<string, mixed> $options Publisher-specific options
     * @return bool True on success
     * @throws PublishException if no publishers configured or all fail
     * @throws JsonException|JsonException
     */
    public function publish(string $topic, string|object|array $message, array $options = []): bool
    {
        if (empty($this->publishers)) {
            throw new PublishException(
                'No publishers configured. Call setRedis(), setKafka(), or setRabbitMq() first, ' .
                'or use the legacy constructor with a PublisherInterface.'
            );
        }

        $errors = [];
        $serialized = $this->serialize($message);

        // Try each publisher in priority order
        foreach ($this->publishers as $layer) {
            try {
                return $layer['publisher']->publish($topic, $serialized, $options);
            } catch (\Exception $e) {
                $errors[] = "{$layer['type']}: {$e->getMessage()}";
                // Continue to next layer
            }
        }

        // All layers failed
        throw new PublishException(
            'All publisher layers failed. Errors: ' . implode(' | ', $errors)
        );
    }

    /**
     * Publish to ALL configured publishers (broadcast)
     *
     * @param string $topic The topic, channel or queue name
     * @param string|object|array<mixed> $message The message payload
     * @param array<string, mixed> $options Publisher-specific options
     * @return array<string, bool> Success status per publisher type
     */
    public function publishToAll(string $topic, string|object|array $message, array $options = []): array
    {
        if (empty($this->publishers)) {
            throw new PublishException('No publishers configured.');
        }

        $results = [];
        $serialized = $this->serialize($message);

        foreach ($this->publishers as $layer) {
            try {
                $results[$layer['type']] = $layer['publisher']->publish($topic, $serialized, $options);
            } catch (\Exception $e) {
                $results[$layer['type']] = false;
            }
        }

        return $results;
    }

    /**
     * Serialize message for transmission
     *
     * @param string|object|array<mixed> $message
     * @throws PublishException|JsonException
     */
    private function serialize(string|object|array $message): string
    {
        if (is_string($message)) {
            return $message;
        }

        // Objects don't need validation (JsonSerializable check happens in json_encode)
        if (is_object($message)) {
            return json_encode($message, JSON_THROW_ON_ERROR);
        }

        // Validate array contents
        $this->validator->validate($message);

        return json_encode($message, JSON_THROW_ON_ERROR);
    }

    /**
     * Sort publishers by priority (lower priority = tried first)
     */
    private function sortPublishersByPriority(): void
    {
        usort($this->publishers, fn($a, $b) => $a['priority'] <=> $b['priority']);
    }
}
