<?php

declare(strict_types=1);

namespace JardisCore\Messaging\Consumer;

use JardisCore\Messaging\Connection\ExternalKafkaConnection;
use JardisCore\Messaging\Connection\KafkaConnection;
use JardisPsr\Messaging\ConsumerInterface;
use JardisPsr\Messaging\Exception\ConsumerException;
use RdKafka\Conf;
use RdKafka\KafkaConsumer as RdKafkaConsumer;
use RdKafka\Message;

/**
 * Kafka message consumer
 *
 * Supports both internal and external Kafka consumer instances.
 * Uses Kafka consumer groups for scalable message consumption.
 */
class KafkaConsumer implements ConsumerInterface
{
    private bool $running = false;
    private ?RdKafkaConsumer $consumer = null;

    /**
     * @param KafkaConnection|ExternalKafkaConnection $connection Kafka connection
     * @param string $groupId Consumer group ID
     * @param array<string, mixed> $kafkaConfig Additional Kafka configuration
     */
    public function __construct(
        private readonly KafkaConnection|ExternalKafkaConnection $connection,
        private readonly string $groupId,
        private readonly array $kafkaConfig = []
    ) {
    }

    public function __destruct()
    {
        $this->stop();
    }

    /**
     * @inheritDoc
     */
    public function consume(string $topic, callable $callback, array $options = []): void
    {
        if ($this->consumer === null) {
            $this->initializeConsumer();
        }

        if ($this->consumer === null) {
            throw new ConsumerException('Failed to initialize Kafka consumer');
        }

        $this->consumer->subscribe([$topic]);
        $this->running = true;

        $timeoutMs = $options['timeout'] ?? 1000;
        $maxEmptyPolls = $options['max_empty_polls'] ?? 10; // Stop after 10 empty polls
        $emptyPollCount = 0;

        while ($this->running) {
            $message = $this->consumer->consume($timeoutMs);

            $hadMessage = $this->handleMessage($message, $callback);

            if (!$hadMessage) {
                $emptyPollCount++;
                if ($emptyPollCount >= $maxEmptyPolls) {
                    // No messages for too long, stop consuming
                    break;
                }
            } else {
                $emptyPollCount = 0; // Reset on successful message
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function stop(): void
    {
        $this->running = false;

        if ($this->consumer !== null) {
            $this->consumer->unsubscribe();
            $this->consumer = null;
        }
    }

    /**
     * Initialize Kafka consumer
     *
     * @throws ConsumerException
     */
    private function initializeConsumer(): void
    {
        // Check if connection provides external consumer
        if ($this->connection instanceof ExternalKafkaConnection) {
            $client = $this->connection->getClient();

            // External connection must provide a KafkaConsumer instance
            if (!$client instanceof RdKafkaConsumer) {
                throw new ConsumerException(
                    'ExternalKafkaConnection for consumer must wrap a RdKafka\KafkaConsumer instance, ' .
                    'not a Producer. Use ExternalKafkaConnection with KafkaConsumer for consuming.'
                );
            }

            // Use external consumer directly
            $this->consumer = $client;
            return;
        }

        // For KafkaConnection: Create new consumer from config
        try {
            $config = $this->connection->getConfig();

            $conf = new Conf();
            $conf->set('group.id', $this->groupId);
            $conf->set('metadata.broker.list', "{$config->host}:{$config->port}");
            $conf->set('auto.offset.reset', 'earliest');
            $conf->set('enable.auto.commit', 'false'); // Manual commit for better control

            // Set SASL authentication if credentials provided
            if ($config->username !== null && $config->password !== null) {
                $conf->set('security.protocol', 'SASL_SSL');
                $conf->set('sasl.mechanism', 'PLAIN');
                $conf->set('sasl.username', $config->username);
                $conf->set('sasl.password', $config->password);
            }

            // Apply additional Kafka-specific configuration
            foreach (array_merge($config->options, $this->kafkaConfig) as $key => $value) {
                if (is_string($value) || is_int($value)) {
                    $conf->set($key, (string) $value);
                }
            }

            $this->consumer = new RdKafkaConsumer($conf);
        } catch (\Exception $e) {
            throw new ConsumerException(
                "Failed to initialize Kafka consumer: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Handle consumed message
     *
     * @param Message $message Kafka message
     * @param callable $callback Message handler
     * @return bool True if a message was processed, false otherwise
     */
    private function handleMessage(Message $message, callable $callback): bool
    {
        switch ($message->err) {
            case RD_KAFKA_RESP_ERR_NO_ERROR:
                $metadata = [
                    'partition' => $message->partition,
                    'offset' => $message->offset,
                    'timestamp' => $message->timestamp,
                    'key' => $message->key,
                    'topic' => $message->topic_name,
                    'type' => 'kafka'
                ];

                try {
                    $continue = $callback($message->payload, $metadata);
                    if ($continue) {
                        // Acknowledge by committing offset
                        if ($this->consumer !== null) {
                            $this->consumer->commit($message);
                        }
                    } else {
                        // Callback returned false - stop consuming
                        $this->stop();
                    }
                } catch (\Exception $e) {
                    error_log("Error handling Kafka message: {$e->getMessage()}");
                }
                return true; // Had a message

            case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                // End of partition, continue
                return false;

            case RD_KAFKA_RESP_ERR__TIMED_OUT:
                // Timeout, continue
                return false;

            default:
                if ($this->running) {
                    error_log("Kafka consumer error: {$message->errstr()} (code: {$message->err})");
                }
                return false;
        }
    }
}
