<?php

declare(strict_types=1);

namespace JardisCore\Messaging\Factory;

use JardisCore\Messaging\Config\ConnectionConfig;
use JardisCore\Messaging\Connection\KafkaConnection;
use JardisCore\Messaging\Connection\RabbitMqConnection;
use JardisCore\Messaging\Connection\RedisConnection;
use JardisCore\Messaging\Publisher\KafkaPublisher;
use JardisCore\Messaging\Publisher\RabbitMqPublisher;
use JardisCore\Messaging\Publisher\RedisPublisher;
use JardisPsr\Messaging\PublisherInterface;

/**
 * Factory for creating publisher instances
 *
 * Encapsulates the instantiation logic for all supported message brokers
 */
final class PublisherFactory
{
    /**
     * Create Redis publisher
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
    ): PublisherInterface {
        $config = new ConnectionConfig(
            host: $host,
            port: $port,
            password: $password,
            options: $options
        );

        $connection = new RedisConnection($config);
        $useStreams = $options['useStreams'] ?? true;

        return new RedisPublisher($connection, $useStreams);
    }

    /**
     * Create Kafka publisher
     *
     * @param string $brokers Kafka brokers (e.g., 'kafka:9092')
     * @param string|null $username SASL username
     * @param string|null $password SASL password
     * @param array<string, mixed> $options Additional Kafka options
     */
    public function kafka(
        string $brokers,
        ?string $username = null,
        ?string $password = null,
        array $options = []
    ): PublisherInterface {
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

        return new KafkaPublisher($connection);
    }

    /**
     * Create RabbitMQ publisher
     *
     * @param string $host RabbitMQ host
     * @param int $port RabbitMQ port
     * @param string $username RabbitMQ username
     * @param string $password RabbitMQ password
     * @param array<string, mixed> $options Additional options
     */
    public function rabbitMq(
        string $host,
        int $port = 5672,
        string $username = 'guest',
        string $password = 'guest',
        array $options = []
    ): PublisherInterface {
        $config = new ConnectionConfig(
            host: $host,
            port: $port,
            username: $username,
            password: $password,
            options: $options
        );

        $connection = new RabbitMqConnection($config);

        return new RabbitMqPublisher($connection);
    }
}
