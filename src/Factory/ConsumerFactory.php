<?php

declare(strict_types=1);

namespace JardisCore\Messaging\Factory;

use JardisCore\Messaging\Config\ConnectionConfig;
use JardisCore\Messaging\Connection\KafkaConnection;
use JardisCore\Messaging\Connection\RabbitMqConnection;
use JardisCore\Messaging\Connection\RedisConnection;
use JardisCore\Messaging\Consumer\KafkaConsumer;
use JardisCore\Messaging\Consumer\RabbitMqConsumer;
use JardisCore\Messaging\Consumer\RedisConsumer;
use JardisPsr\Messaging\ConsumerInterface;

/**
 * Factory for creating consumer instances
 *
 * Encapsulates the instantiation logic for all supported message brokers
 */
final class ConsumerFactory
{
    /**
     * Create Redis consumer
     *
     * @param string $host Redis host
     * @param int $port Redis port
     * @param string|null $password Redis password
     * @param array<string, mixed> $options Additional options (useStreams, etc.)
     */
    public function redis(
        string $host,
        int $port = 6379,
        ?string $password = null,
        array $options = []
    ): ConsumerInterface {
        $config = new ConnectionConfig(
            host: $host,
            port: $port,
            password: $password,
            options: $options
        );

        $connection = new RedisConnection($config);
        $useStreams = $options['useStreams'] ?? true;

        return new RedisConsumer($connection, $useStreams);
    }

    /**
     * Create Kafka consumer
     *
     * @param string $brokers Kafka brokers (e.g., 'kafka:9092')
     * @param string $groupId Consumer group ID
     * @param string|null $username SASL username
     * @param string|null $password SASL password
     * @param array<string, mixed> $options Additional Kafka options
     */
    public function kafka(
        string $brokers,
        string $groupId,
        ?string $username = null,
        ?string $password = null,
        array $options = []
    ): ConsumerInterface {
        $parts = explode(':', $brokers);
        $host = $parts[0];
        $port = isset($parts[1]) ? (int) $parts[1] : 9092;

        $config = new ConnectionConfig(
            host: $host,
            port: $port,
            username: $username,
            password: $password,
            options: $options
        );

        $connection = new KafkaConnection($config);

        return new KafkaConsumer($connection, $groupId, $options);
    }

    /**
     * Create RabbitMQ consumer
     *
     * @param string $host RabbitMQ host
     * @param string $queueName Queue name
     * @param int $port RabbitMQ port
     * @param string $username RabbitMQ username
     * @param string $password RabbitMQ password
     * @param array<string, mixed> $options Additional options
     */
    public function rabbitMq(
        string $host,
        string $queueName,
        int $port = 5672,
        string $username = 'guest',
        string $password = 'guest',
        array $options = []
    ): ConsumerInterface {
        $config = new ConnectionConfig(
            host: $host,
            port: $port,
            username: $username,
            password: $password,
            options: $options
        );

        $connection = new RabbitMqConnection($config);

        return new RabbitMqConsumer($connection, $queueName, $options);
    }
}
