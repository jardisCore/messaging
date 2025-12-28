<?php

declare(strict_types=1);

namespace JardisCore\Messaging;

use JardisCore\Messaging\Factory\ConsumerFactory;
use JardisPsr\Messaging\ConsumerInterface;
use JardisPsr\Messaging\MessageConsumerInterface;
use JardisPsr\Messaging\MessageHandlerInterface;
use JardisPsr\Messaging\Exception\ConsumerException;

/**
 * Main message consumer class
 *
 * Provides a unified interface for consuming messages from different
 * message brokers (Redis, Kafka, RabbitMQ) with layered fallback support
 *
 * New fluent API:
 * $consumer = (new MessageConsumer())
 *     ->setRedis('localhost')
 *     ->setKafka('kafka:9092', 'group-id')
 *     ->consume('topic', $handler);
 *
 * Legacy API (still supported):
 * $consumer = new MessageConsumer($consumerInterface);
 */
class MessageConsumer implements MessageConsumerInterface
{
    private bool $autoDeserialize = true;
    private bool $gracefulShutdownEnabled = false;
    private readonly ConsumerFactory $factory;

    /** @var array<array{type: string, consumer: ConsumerInterface, priority: int}> */
    private array $consumers = [];

    /**
     * @param ConsumerInterface|null $consumer Legacy: single consumer (optional)
     * @param bool $autoDeserialize Automatically deserialize JSON messages (default: true)
     * @param ConsumerFactory|null $factory Consumer factory (auto-created if null)
     */
    public function __construct(
        ?ConsumerInterface $consumer = null,
        bool $autoDeserialize = true,
        ?ConsumerFactory $factory = null
    ) {
        $this->autoDeserialize = $autoDeserialize;
        $this->factory = $factory ?? new ConsumerFactory();

        // Legacy support: single consumer
        if ($consumer !== null) {
            $this->consumers[] = [
                'type' => 'legacy',
                'consumer' => $consumer,
                'priority' => 0
            ];
        }
    }

    /**
     * Configure Redis consumer
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
        $this->consumers[] = [
            'type' => 'redis',
            'consumer' => $this->factory->redis($host, $port, $password, $options),
            'priority' => $priority
        ];

        $this->sortConsumersByPriority();

        return $this;
    }

    /**
     * Configure Kafka consumer
     *
     * @param string $brokers Kafka brokers (e.g., 'kafka:9092')
     * @param string $groupId Consumer group ID
     * @param string|null $username SASL username
     * @param string|null $password SASL password
     * @param array<string, mixed> $options Additional Kafka options
     * @param int $priority Layer priority (lower = tried first)
     * @return self
     */
    public function setKafka(
        string $brokers,
        string $groupId,
        ?string $username = null,
        ?string $password = null,
        array $options = [],
        int $priority = 1
    ): self {
        $this->consumers[] = [
            'type' => 'kafka',
            'consumer' => $this->factory->kafka($brokers, $groupId, $username, $password, $options),
            'priority' => $priority
        ];

        $this->sortConsumersByPriority();

        return $this;
    }

    /**
     * Configure RabbitMQ consumer
     *
     * @param string $host RabbitMQ host
     * @param string $queueName Queue name
     * @param int $port RabbitMQ port
     * @param string $username RabbitMQ username
     * @param string $password RabbitMQ password
     * @param array<string, mixed> $options Additional options
     * @param int $priority Layer priority (lower = tried first)
     * @return self
     */
    public function setRabbitMq(
        string $host,
        string $queueName,
        int $port = 5672,
        string $username = 'guest',
        string $password = 'guest',
        array $options = [],
        int $priority = 2
    ): self {
        $this->consumers[] = [
            'type' => 'rabbitmq',
            'consumer' => $this->factory->rabbitMq($host, $queueName, $port, $username, $password, $options),
            'priority' => $priority
        ];

        $this->sortConsumersByPriority();

        return $this;
    }

    /**
     * Add an already instantiated consumer (e.g., for external connections)
     *
     * This method allows adding pre-configured ConsumerInterface instances,
     * which is useful when working with externally managed connections
     * (e.g., legacy applications sharing their Redis/Kafka/RabbitMQ connections).
     *
     * @param ConsumerInterface $consumer The consumer instance to add
     * @param string $type Type identifier (e.g., 'redis', 'kafka', 'rabbitmq', 'external')
     * @param int $priority Layer priority (lower = tried first, default: 0)
     * @return self
     *
     * @example
     * // Using external Kafka consumer from legacy app
     * $externalKafkaConsumer = $legacyApp->getKafkaConsumer();
     * $connection = new ExternalKafkaConnection($externalKafkaConsumer);
     * $consumer = new KafkaConsumer($connection, 'my-group-id');
     *
     * $messageConsumer = (new MessageConsumer())
     *     ->addConsumer($consumer, 'kafka-external', 1);
     */
    public function addConsumer(ConsumerInterface $consumer, string $type, int $priority = 0): self
    {
        $this->consumers[] = [
            'type' => $type,
            'consumer' => $consumer,
            'priority' => $priority
        ];

        $this->sortConsumersByPriority();

        return $this;
    }

    /**
     * Enable graceful shutdown via system signals (SIGTERM, SIGINT)
     *
     * Automatically calls stop() when receiving termination signals
     * Requires pcntl extension (available on Unix/Linux systems)
     *
     * @return self
     */
    public function enableGracefulShutdown(): self
    {
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn() => $this->stop());
            pcntl_signal(SIGINT, fn() => $this->stop());
            $this->gracefulShutdownEnabled = true;
        }
        return $this;
    }

    /**
     * Start consuming messages with a handler
     *
     * Tries each configured consumer in priority order (fallback on failure)
     * Automatically enables graceful shutdown if not already enabled
     *
     * @param string $topic The topic, channel or queue name
     * @param MessageHandlerInterface $handler Message handler
     * @param array<string, mixed> $options Consumer-specific options
     * @throws ConsumerException if no consumers configured or all fail
     */
    public function consume(string $topic, MessageHandlerInterface $handler, array $options = []): void
    {
        if (empty($this->consumers)) {
            throw new ConsumerException(
                'No consumers configured. Call setRedis(), setKafka(), or setRabbitMq() first, ' .
                'or use the legacy constructor with a ConsumerInterface.'
            );
        }

        // Auto-enable graceful shutdown if not already enabled
        if (!$this->gracefulShutdownEnabled) {
            $this->enableGracefulShutdown();
        }

        $callback = function (string $rawMessage, array $metadata) use ($handler): bool {
            $message = $this->autoDeserialize ? $this->deserialize($rawMessage) : $rawMessage;
            return $handler->handle($message, $metadata);
        };

        $errors = [];

        // Try each consumer in priority order
        foreach ($this->consumers as $layer) {
            try {
                $layer['consumer']->consume($topic, $callback, $options);
                return; // Success
            } catch (\Exception $e) {
                $errors[] = "{$layer['type']}: {$e->getMessage()}";
                // Continue to next layer
            }
        }

        // All layers failed
        throw new ConsumerException(
            'All consumer layers failed. Errors: ' . implode(' | ', $errors)
        );
    }

    /**
     * Stop consuming messages
     */
    public function stop(): void
    {
        foreach ($this->consumers as $layer) {
            $layer['consumer']->stop();
        }
    }

    /**
     * Enable or disable auto-deserialization
     *
     * @param bool $enabled
     * @return self
     */
    public function autoDeserialize(bool $enabled = true): self
    {
        $this->autoDeserialize = $enabled;
        return $this;
    }

    /**
     * Try to deserialize JSON, fallback to raw string
     *
     * @param string $raw Raw message
     * @return string|array<mixed> Deserialized message or raw string
     */
    private function deserialize(string $raw): string|array
    {
        if ($raw === '') {
            return $raw;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : $raw;
        } catch (\JsonException) {
            return $raw;
        }
    }

    /**
     * Sort consumers by priority (lower priority = tried first)
     */
    private function sortConsumersByPriority(): void
    {
        usort($this->consumers, fn($a, $b) => $a['priority'] <=> $b['priority']);
    }
}
