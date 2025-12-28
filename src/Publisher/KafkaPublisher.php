<?php

declare(strict_types=1);

namespace JardisCore\Messaging\Publisher;

use JardisCore\Messaging\Connection\KafkaConnection;
use JardisCore\Messaging\Connection\ExternalKafkaConnection;
use JardisPsr\Messaging\PublisherInterface;
use JardisPsr\Messaging\Exception\ConnectionException;
use JardisPsr\Messaging\Exception\PublishException;
use RdKafka\ProducerTopic;
use RdKafka\Topic;

/**
 * Kafka message publisher
 *
 * Uses Kafka producer for message publishing
 */
class KafkaPublisher implements PublisherInterface
{
    /** @var array<string, ProducerTopic|Topic> */
    private array $topics = [];

    /**
     * @param KafkaConnection|ExternalKafkaConnection $connection Kafka connection instance
     */
    public function __construct(
        private readonly KafkaConnection|ExternalKafkaConnection $connection
    ) {
    }

    /**
     * Publish a message to the specified topic
     *
     * @param string $topic The Kafka topic name
     * @param string $message The message payload (already serialized)
     * @param array<string, mixed> $options Publisher-specific options (partition, key)
     * @return bool True on success
     * @throws PublishException
     * @throws ConnectionException
     */
    public function publish(string $topic, string $message, array $options = []): bool
    {
        if (!$this->connection->isConnected()) {
            $this->connection->connect();
        }

        try {
            $producerTopic = $this->getOrCreateTopic($topic);

            $partition = $options['partition'] ?? RD_KAFKA_PARTITION_UA;
            $key = $options['key'] ?? null;

            // @phpstan-ignore-next-line (Both ProducerTopic and Topic have produce() method)
            $producerTopic->produce($partition, 0, $message, $key);

            return true;
        } catch (\Exception $e) {
            throw new PublishException(
                "Failed to publish message to Kafka topic '{$topic}': {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Get or create a producer topic
     *
     * @return ProducerTopic|Topic
     */
    private function getOrCreateTopic(string $topicName): ProducerTopic|Topic
    {
        if (!isset($this->topics[$topicName])) {
            $producer = $this->connection->getClient();
            $this->topics[$topicName] = $producer->newTopic($topicName);
        }

        return $this->topics[$topicName];
    }
}
