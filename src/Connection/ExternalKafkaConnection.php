<?php

declare(strict_types=1);

namespace JardisCore\Messaging\Connection;

use JardisPsr\Messaging\ConnectionInterface;
use JardisPsr\Messaging\Exception\ConnectionException;
use RdKafka\KafkaConsumer;
use RdKafka\Producer;

/**
 * External Kafka connection wrapper
 *
 * Wraps an externally managed Kafka producer or consumer for reuse in messaging.
 * The external system is responsible for client lifecycle.
 *
 * Use case: Legacy applications with existing Kafka clients
 *
 * Example (Producer):
 * ```php
 * $legacyProducer = new \RdKafka\Producer();
 * $legacyProducer->addBrokers('kafka1:9092');
 *
 * $connection = new ExternalKafkaConnection($legacyProducer);
 * $publisher = new KafkaPublisher($connection);
 * ```
 *
 * Example (Consumer):
 * ```php
 * $legacyConsumer = new \RdKafka\KafkaConsumer($conf);
 *
 * $connection = new ExternalKafkaConnection($legacyConsumer);
 * $consumer = new KafkaConsumer($connection, 'my-group');
 * ```
 */
class ExternalKafkaConnection implements ConnectionInterface
{
    private bool $connected = true;

    /**
     * @param Producer|KafkaConsumer $client Externally managed Kafka client instance
     * @param bool $flushOnDisconnect If true, flush producer on disconnect (default: false)
     */
    public function __construct(
        private readonly Producer|KafkaConsumer $client,
        private readonly bool $flushOnDisconnect = false
    ) {
    }

    /**
     * No-op for external connections - client already initialized
     */
    public function connect(): void
    {
        // External client is already initialized
        $this->connected = true;
    }

    /**
     * Optionally flush producer based on lifecycle management
     *
     * Note: If flushOnDisconnect is false, this will not flush the producer
     * as it may still be used by the external system.
     */
    public function disconnect(): void
    {
        if ($this->flushOnDisconnect && $this->connected) {
            // Only flush for producers
            if ($this->client instanceof Producer) {
                $this->client->flush(10000); // 10 second timeout
            }
        }
        $this->connected = false;
    }

    /**
     * Kafka client has no built-in connection state check
     *
     * Assume connected if not explicitly disconnected
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Get the external Kafka client (Producer or Consumer)
     *
     * @return Producer|KafkaConsumer
     * @throws ConnectionException if disconnected
     */
    public function getClient(): Producer|KafkaConsumer
    {
        if (!$this->isConnected()) {
            throw new ConnectionException(
                'External Kafka client is not available. ' .
                'The external system must provide a valid client.'
            );
        }

        return $this->client;
    }
}
