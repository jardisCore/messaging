# 🚀 Jardis Async-Messaging

> **A powerful, unified PHP messaging library that makes working with Redis, Kafka, and RabbitMQ effortless.**

![Build Status](https://github.com/jardisCore/logger/actions/workflows/ci.yml/badge.svg)
[![License](https://img.shields.io/badge/license-PolyForm%20Noncommercial-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-success.svg)](phpstan.neon)
[![PSR-4](https://img.shields.io/badge/autoload-PSR--4-blue.svg)](https://www.php-fig.org/psr/psr-4/)
[![PSR-12](https://img.shields.io/badge/code%20style-PSR--12-orange.svg)](phpcs.xml)
[![Coverage](https://img.shields.io/badge/coverage-84.35%25-brightgreen.svg)](phpunit.xml)
---

## ✨ Why Choose Jardis Async-Messaging?

### 🎯 **One API, Three Brokers**
Switch between Redis Streams, Apache Kafka, and RabbitMQ with **zero code changes**. Your business logic stays clean while we handle the complexity.

### 💪 **Built for Modern PHP**
- **PHP 8.2+** with full type safety and strict types
- **Named arguments** for crystal-clear configuration
- **Dependency injection ready** - perfect for frameworks

### 🛡️ **Production-Ready Features**
- ✅ **Layered architecture** - automatic fallback & broadcast
- ✅ **Fluent API** - 2-line setup, zero boilerplate
- ✅ **Automatic JSON serialization/deserialization**
- ✅ **Connection pooling and graceful reconnection**
- ✅ **Consumer groups** (Redis & Kafka)
- ✅ **Message acknowledgement** (RabbitMQ & Kafka)
- ✅ **Metadata support** for tracing and debugging
- ✅ **Lazy connections** - connect only when needed

### 🎨 **Developer Experience First**
- **Intuitive, fluent API** - publish in 2 lines
- **Comprehensive validation** - catch errors before they hit production
- **Detailed exceptions** with context
- **Full PHPStan Level 8 compatible**

### 🔬 **Battle-Tested**
- **320 tests** with **84.35% coverage**
- Extensive unit and integration test suite
- CI/CD ready with Docker Compose
- Used in production DDD/Event-Driven systems

---

## 📦 Installation

```bash
composer require jardiscore/messaging
```

**Requirements:**
- PHP 8.2 or higher
- Choose your broker extension(s):
  - `ext-redis` for Redis Streams/Pub-Sub
  - `ext-rdkafka` for Apache Kafka
  - `ext-amqp` for RabbitMQ

---

## 🚀 Quick Start

### ⚡ **NEW: Fluent API** (Easiest Way!)

```php
use JardisCore\Messaging\MessagePublisher;
use JardisCore\Messaging\MessageConsumer;
use JardisCore\Messaging\MessagingService;

// Publishing - Just 2 lines!
$publisher = (new MessagePublisher())->setRedis('localhost');
$publisher->publish('orders', ['order_id' => 123, 'total' => 99.99]);

// Consuming - Just as easy!
$consumer = (new MessageConsumer())
    ->setRedis('localhost')
    ->enableGracefulShutdown();  // Auto-stop on SIGTERM/SIGINT

$consumer->consume('orders', $handler);

// Or use the unified MessagingService wrapper
$messaging = new MessagingService($publisher, $consumer);
$messaging->publish('orders', ['order_id' => 123]);
$messaging->consume('orders', $handler);
```

**That's it!** No configuration objects, no connection boilerplate. Just fluent, readable code.

---

### 🔥 **Advanced: Layered Messaging** (High Availability)

Stack multiple brokers for **automatic fallback**:

```php
// Try Redis first, fallback to Kafka if Redis fails
$publisher = (new MessagePublisher())
    ->setRedis('localhost', priority: 0)      // Fastest - try first
    ->setKafka('kafka:9092', priority: 1);    // Fallback

$publisher->publish('orders', ['order_id' => 123]);
// → Publishes to Redis, OR Kafka if Redis is down!
```

**Or broadcast to ALL brokers:**

```php
$publisher = (new MessagePublisher())
    ->setRedis('redis-cache')     // Hot cache
    ->setKafka('kafka:9092')      // Event log
    ->setRabbitMq('rabbitmq');    // Task queue

$publisher->publishToAll('critical-event', ['status' => 'down']);
// → Published to Redis, Kafka, AND RabbitMQ simultaneously!
```

---

### 📖 All Brokers with Fluent API

#### Kafka
```php
// Publish
$publisher = (new MessagePublisher())->setKafka('kafka:9092');
$publisher->publish('user-events', [
    'event' => 'user.registered',
    'user_id' => 'abc123'
]);

// Consume
$consumer = (new MessageConsumer())
    ->setKafka('kafka:9092', groupId: 'order-processors')
    ->autoDeserialize();

$consumer->consume('orders', $handler, ['timeout' => 1000]);
```

#### RabbitMQ
```php
// Publish
$publisher = (new MessagePublisher())
    ->setRabbitMq('rabbitmq', username: 'admin', password: 'secret');

$publisher->publish('notifications', [
    'type' => 'email',
    'to' => 'user@example.com'
]);

// Consume
$consumer = (new MessageConsumer())
    ->setRabbitMq('rabbitmq', queueName: 'notifications', username: 'admin', password: 'secret');

$handler = new CallbackHandler(function ($msg, $meta): bool {
    sendEmail($msg);
    return true; // ACK
});

$consumer->consume('email.*', $handler);
```

#### With Options
```php
// Redis with Pub/Sub (not Streams)
$publisher = (new MessagePublisher())
    ->setRedis('localhost', options: ['useStreams' => false]);

// Kafka with SASL auth
$publisher = (new MessagePublisher())
    ->setKafka('kafka:9092', username: 'user', password: 'pass');

// RabbitMQ with custom port
$publisher = (new MessagePublisher())
    ->setRabbitMq('localhost', port: 5673);
```

---

## 🎨 Advanced Features

### 🛡️ **Graceful Shutdown** (NEW!)

Enable automatic cleanup when your consumer receives termination signals:

```php
$consumer = (new MessageConsumer())
    ->setRedis('localhost')
    ->enableGracefulShutdown();  // Registers SIGTERM & SIGINT handlers

$consumer->consume('orders', $handler);
// When you press Ctrl+C or send SIGTERM, consumer stops gracefully
```

**Features:**
- Automatically stops on `SIGTERM` (Docker/Kubernetes stop)
- Automatically stops on `SIGINT` (Ctrl+C)
- Works on Unix/Linux systems with `pcntl` extension
- No manual signal handling needed

### 🔌 **MessagingService Wrapper with Lazy Loading** (NEW!)

Unified interface for DDD/Application Services with **lazy instantiation**:

```php
use JardisCore\Messaging\MessagingService;

// Setup with lazy loading - Publisher/Consumer created only when needed!
$messaging = new MessagingService(
    publisherFactory: fn() => (new MessagePublisher())->setRedis('localhost'),
    consumerFactory: fn() => (new MessageConsumer())->setRedis('localhost')->enableGracefulShutdown()
);

// Publisher is NOT created yet - no Redis connection established!

// Use in your Domain/Application layer
class OrderService
{
    public function __construct(private MessagingService $messaging) {}

    public function placeOrder(Order $order): void
    {
        // ... business logic

        // Publisher is created HERE on first publish() call
        $this->messaging->publish('orders.placed', [
            'order_id' => $order->id,
            'total' => $order->total
        ]);
    }

    public function startOrderProcessor(): void
    {
        // Consumer is created HERE on first consume() call
        $this->messaging->consume('orders.placed', new OrderHandler());
    }
}

// Perfect for DDD contexts like $domain->getMessage()
```

**Benefits:**
- ✅ **Zero overhead** if service is injected but never used
- ✅ **No connections** established until actually needed
- ✅ **Memory efficient** - only instantiate what you use
- ✅ **Perfect for DI containers** - inject everywhere, pay only when used

### 🏗️ **Layered Architecture**

Build resilient, high-availability messaging systems by stacking multiple brokers:

#### Use Case 1: Automatic Fallback
```php
// Production setup: Primary + Backup
$publisher = (new MessagePublisher())
    ->setRedis('redis-primary', priority: 0)     // Try first
    ->setRedis('redis-replica', priority: 1)     // Fallback #1
    ->setKafka('kafka:9092', priority: 2);       // Fallback #2

$publisher->publish('orders', $order);
// Automatically tries redis-primary → redis-replica → kafka
```

#### Use Case 2: Broadcast Critical Events
```php
// Send to ALL systems simultaneously
$publisher = (new MessagePublisher())
    ->setRedis('cache')       // For real-time processing
    ->setKafka('kafka:9092')  // For audit log
    ->setRabbitMq('rabbitmq'); // For async workers

$results = $publisher->publishToAll('system.alert', [
    'severity' => 'critical',
    'message' => 'Database connection lost'
]);
// Returns: ['redis' => true, 'kafka' => true, 'rabbitmq' => true]
```

#### Use Case 3: Performance Tiering
```php
// Hot path + Cold storage
$publisher = (new MessagePublisher())
    ->setRedis('localhost', priority: 0)      // Fast cache (ms)
    ->setKafka('kafka:9092', priority: 1);    // Persistent (s)

$publisher->publish('analytics', $event);
// Writes to Redis for instant queries,
// Falls back to Kafka if Redis is saturated
```

#### Use Case 4: Consumer Fallback
```php
// Try Redis first, fallback to Kafka
$consumer = (new MessageConsumer())
    ->setRedis('localhost', priority: 0)
    ->setKafka('kafka:9092', 'backup-group', priority: 1);

$consumer->consume('orders', $handler);
// Reads from Redis, switches to Kafka if Redis fails
```

**Priority System:**
- Lower priority number = tried first
- Default: Redis (0) → Kafka (1) → RabbitMQ (2)
- Customize with `priority:` parameter

---

### Custom Message Handlers

```php
use JardisCore\Messaging\contract\MessageHandlerInterface;

class OrderHandler implements MessageHandlerInterface
{
    public function __construct(
        private OrderService $orderService,
        private Logger $logger
    ) {}

    public function handle(string|array $message, array $metadata): bool
    {
        $this->logger->info('Processing order', [
            'order_id' => $message['order_id'],
            'stream_id' => $metadata['id']
        ]);

        $this->orderService->process($message);

        return true;
    }
}

$consumer->consume('orders', new OrderHandler($orderService, $logger));
```

### Redis Consumer Groups (Horizontal Scaling)

```php
$consumer = (new MessageConsumer())->setRedis('localhost');

// Multiple consumers with same group = work distribution
$consumer->consume('orders', $handler, [
    'group' => 'order-workers',
    'consumer' => gethostname(),  // Unique consumer name
    'count' => 10,                 // Batch size
    'block' => 2000
]);
```

### Kafka with Custom Configuration

```php
$consumer = (new MessageConsumer())->setKafka(
    brokers: 'kafka:9092',
    groupId: 'my-group',
    options: [
        'session.timeout.ms' => 6000,
        'max.poll.interval.ms' => 300000,
        'enable.auto.offset.store' => 'false'
    ]
);
```

### RabbitMQ Queue Options

```php
$consumer = (new MessageConsumer())->setRabbitMq(
    host: 'localhost',
    queueName: 'high-priority-orders',
    options: [
        'flags' => AMQP_DURABLE,
        'arguments' => [
            'x-message-ttl' => 60000,        // 60 seconds TTL
            'x-max-priority' => 10,          // Priority queue
            'x-max-length' => 10000          // Max queue size
        ]
    ]
);

$consumer->consume('orders.*', $handler, [
    'prefetch_count' => 5  // QoS - process 5 at a time
]);
```

### Publisher Options

```php
// Redis with custom fields
$publisher->publish('orders', ['order_id' => 123], [
    'fields' => [
        'priority' => 'high',
        'region' => 'EU'
    ]
]);

// Kafka with partition key
$publisher->publish('orders', $order, [
    'key' => $order['customer_id'],  // Same customer = same partition
    'partition' => 2                  // Or explicit partition
]);

// RabbitMQ with message attributes
$publisher->publish('notifications', $notification, [
    'attributes' => [
        'priority' => 9,
        'delivery_mode' => 2,         // Persistent
        'expiration' => '60000'       // 60s expiration
    ]
]);
```

---

## 🔌 External Connection Support

If your application already has established connections (e.g., in a legacy system), you can wrap them for reuse instead of creating new connections. This is perfect for integrating the messaging library into existing applications without duplicating connection resources.

### Why Use External Connections?

- **Resource Efficiency**: Reuse existing connections instead of creating new ones
- **Legacy Integration**: Seamlessly integrate with existing infrastructure
- **Connection Management**: Let your existing system manage connection lifecycle
- **Zero Migration Cost**: Add messaging capabilities without refactoring existing code

### Redis

Wrap an existing Redis connection:

```php
use JardisCore\Messaging\Connection\ExternalRedisConnection;
use JardisCore\Messaging\Publisher\RedisPublisher;

// Your existing Redis connection
$legacyRedis = new Redis();
$legacyRedis->connect('localhost', 6379);

// Wrap it for reuse
$connection = new ExternalRedisConnection($legacyRedis);
$publisher = new RedisPublisher($connection);

// Use it normally
$publisher->publish('orders', json_encode(['order_id' => 123]));

// Connection lifecycle is managed by your legacy system
```

**Lifecycle Management:**

```php
// Default: Don't close external connection on disconnect
$connection = new ExternalRedisConnection($legacyRedis, manageLifecycle: false);

// Or: Allow messaging library to close it
$connection = new ExternalRedisConnection($legacyRedis, manageLifecycle: true);
```

### Kafka

Wrap an existing Kafka producer or consumer:

```php
use JardisCore\Messaging\Connection\ExternalKafkaConnection;
use JardisCore\Messaging\Publisher\KafkaPublisher;
use JardisCore\Messaging\Consumer\KafkaConsumer;

// For Publishing - wrap existing producer
$legacyProducer = new \RdKafka\Producer();
$legacyProducer->addBrokers('kafka1:9092');

$connection = new ExternalKafkaConnection($legacyProducer);
$publisher = new KafkaPublisher($connection);
$publisher->publish('user-events', json_encode(['event' => 'user.registered']));

// For Consuming - wrap existing consumer
$conf = new \RdKafka\Conf();
$conf->set('group.id', 'my-group');
$conf->set('metadata.broker.list', 'kafka1:9092');

$legacyConsumer = new \RdKafka\KafkaConsumer($conf);

$connection = new ExternalKafkaConnection($legacyConsumer);
$consumer = new KafkaConsumer($connection, 'my-group');
$consumer->consume('user-events', $handler);
```

**Flush on Disconnect (for Producers):**

```php
// Default: Don't flush on disconnect
$connection = new ExternalKafkaConnection($legacyProducer, flushOnDisconnect: false);

// Or: Flush producer when disconnecting
$connection = new ExternalKafkaConnection($legacyProducer, flushOnDisconnect: true);
```

**Important:** `ExternalKafkaConnection` supports both `\RdKafka\Producer` and `\RdKafka\KafkaConsumer` instances. Make sure to pass the correct type for your use case.

### RabbitMQ

Wrap an existing AMQP connection:

```php
use JardisCore\Messaging\Connection\ExternalRabbitMqConnection;
use JardisCore\Messaging\Publisher\RabbitMqPublisher;

// Your existing RabbitMQ connection
$legacyConnection = new \AMQPConnection([
    'host' => 'localhost',
    'port' => 5672,
    'login' => 'guest',
    'password' => 'guest',
]);
$legacyConnection->connect();

// Wrap it for reuse
$connection = new ExternalRabbitMqConnection($legacyConnection);
$publisher = new RabbitMqPublisher($connection);

// Use it normally
$publisher->publish('notifications', json_encode(['type' => 'email']));
```

**Custom Exchange Configuration:**

```php
$connection = new ExternalRabbitMqConnection(
    connection: $legacyConnection,
    exchangeName: 'custom-exchange',
    exchangeType: AMQP_EX_TYPE_TOPIC,
    manageLifecycle: false
);
```

### Adding Pre-Configured Publishers/Consumers

Use `addPublisher()` and `addConsumer()` methods to add already instantiated broker instances with external connections:

```php
use JardisCore\Messaging\MessagePublisher;
use JardisCore\Messaging\MessageConsumer;
use JardisCore\Messaging\Connection\ExternalRedisConnection;
use JardisCore\Messaging\Connection\ExternalKafkaConnection;
use JardisCore\Messaging\Publisher\RedisPublisher;
use JardisCore\Messaging\Consumer\KafkaConsumer;

// Legacy app setup
$legacyRedis = new Redis();
$legacyRedis->connect('localhost', 6379);

// Create external connection and publisher
$redisConnection = new ExternalRedisConnection($legacyRedis);
$redisPublisher = new RedisPublisher($redisConnection, useStreams: true);

// Add to MessagePublisher with custom type and priority
$messagePublisher = (new MessagePublisher())
    ->addPublisher($redisPublisher, 'redis-external', priority: 0)
    ->setKafka('kafka:9092', priority: 1);  // Mix with new connections

$messagePublisher->publish('events', ['type' => 'order.created']);

// Same pattern for consumers
$legacyKafkaConsumer = new \RdKafka\KafkaConsumer($conf);
$kafkaConnection = new ExternalKafkaConnection($legacyKafkaConsumer);
$kafkaConsumer = new KafkaConsumer($kafkaConnection, 'my-group');

$messageConsumer = (new MessageConsumer())
    ->addConsumer($kafkaConsumer, 'kafka-external', priority: 0);

$messageConsumer->consume('orders', $handler);
```

**Use Cases:**
- **Legacy Integration**: Reuse existing connections from legacy systems
- **Custom Configuration**: Add publishers/consumers with advanced setup
- **Mixed Environments**: Combine external and new connections with priority control
- **Testing**: Inject mock publishers/consumers for unit tests

### Use with Fluent API (Legacy Pattern)

External connections work seamlessly with publishers created via factories:

```php
// Legacy app setup
$redis = new Redis();
$redis->connect('localhost', 6379);

// Create publisher with external connection
$externalConnection = new ExternalRedisConnection($redis);
$publisher = new RedisPublisher($externalConnection, useStreams: true);

// Now wrap it in MessagePublisher if needed
$messagePublisher = new MessagePublisher($publisher);
$messagePublisher->publish('events', ['type' => 'order.created']);
```

### Best Practices

1. **Lifecycle Management**: Use `manageLifecycle: false` (default) when external system owns the connection
2. **Health Checks**: External connections perform health checks (e.g., Redis ping)
3. **Error Handling**: If external connection dies, appropriate exceptions are thrown
4. **Thread Safety**: Ensure your external connection is thread-safe if used concurrently

---

## 🧪 Testing

### Run All Tests
```bash
make phpunit
```

### Run Only Unit Tests
```bash
make phpunit-unit
```

### Run Integration Tests (requires Docker)
```bash
make start              # Start Redis, Kafka, RabbitMQ (Wiremock removed)
make phpunit-integration
```

### Code Quality
```bash
make phpstan            # Static analysis
make phpcs              # Coding standards
make phpunit-coverage   # Coverage report
```

---

## 🏗️ Architecture

### Clean, Interface-Driven Design

```
MessagingService (DDD/Application Layer)
    ├── MessagePublisher (Facade)
    │   ├── Factory/
    │   │   └── PublisherFactory
    │   └── PublisherInterface (contract)
    │       ├── RedisPublisher
    │       ├── KafkaPublisher
    │       └── RabbitMqPublisher
    │
    └── MessageConsumer (Facade)
        ├── Factory/
        │   └── ConsumerFactory
        └── ConsumerInterface (contract)
            ├── RedisConsumer
            ├── KafkaConsumer
            └── RabbitMqConsumer
```

**Architecture Highlights:**
- **Factory Pattern**: Centralized factory classes for creating publisher/consumer instances (SRP compliance)
- **Facade Pattern**: MessagePublisher/MessageConsumer provide simple APIs
- **Strategy Pattern**: Pluggable publisher/consumer implementations
- **Layered Fallback**: Priority-based automatic failover

### Dependency Injection Ready

```php
// In your DI container - MessagingService with Lazy Loading (Recommended)
$container->singleton(MessagingService::class, function() {
    return new MessagingService(
        publisherFactory: fn() => (new MessagePublisher())
            ->setRedis(env('REDIS_HOST'), (int) env('REDIS_PORT'))
            ->setKafka(env('KAFKA_BROKERS'), priority: 1),
        consumerFactory: fn() => (new MessageConsumer())
            ->setRedis(env('REDIS_HOST'), (int) env('REDIS_PORT'))
            ->enableGracefulShutdown()
    );
});

// Or register separately
$container->singleton(MessagePublisher::class, function() {
    return (new MessagePublisher())
        ->setRedis(env('REDIS_HOST'), (int) env('REDIS_PORT'));
});

$container->singleton(MessageConsumer::class, function() {
    return (new MessageConsumer())
        ->setRedis(env('REDIS_HOST'), (int) env('REDIS_PORT'))
        ->enableGracefulShutdown();
});
```

### Classic API (For Advanced Users)

If you need explicit control over connections and publishers:

```php
use JardisCore\Messaging\Config\ConnectionConfig;
use JardisCore\Messaging\Connection\RedisConnection;
use JardisCore\Messaging\Publisher\RedisPublisher;

// Create connection configuration
$config = new ConnectionConfig(
    host: 'localhost',
    port: 6379,
    password: 'secret'
);

// Create connection
$connection = new RedisConnection($config);

// Create specific publisher
$redisPublisher = new RedisPublisher($connection, useStreams: true);

// Use with MessagePublisher
$publisher = new MessagePublisher($redisPublisher);
$publisher->publish('orders', ['order_id' => 123]);
```

This approach gives you full control but requires more setup. The fluent API is recommended for most use cases.

---

## 🎯 Use Cases

### ✅ Perfect For:
- **Event-Driven Architecture** - Decouple your services
- **Domain-Driven Design** - Domain events and command handling
- **Microservices Communication** - Async messaging between services
- **Job Queues** - Background processing
- **Real-time Updates** - WebSocket backends, live dashboards
- **CQRS** - Command/Query separation with event sourcing

### 🏢 Production Scenarios:
- E-commerce order processing
- User notification systems
- Payment processing pipelines
- Log aggregation and monitoring
- IoT data ingestion
- Analytics event tracking

---

## 📚 Documentation

### Key Concepts

#### Auto-Serialization
- **Arrays** → Automatically encoded to JSON
- **Objects** → Must implement `JsonSerializable`
- **Strings** → Passed through as-is

#### Auto-Deserialization
- **Valid JSON** → Decoded to array
- **Invalid JSON** → Returned as string
- **Can be disabled** with `autoDeserialize: false`

#### Connection Management
- **Lazy connection** - Connects on first use
- **Auto-reconnect** - Handles connection drops
- **Graceful shutdown** - Kafka flush on disconnect

#### Message Metadata
Each message handler receives metadata:
- **Redis**: `id`, `stream`, `timestamp`, `type`
- **Kafka**: `partition`, `offset`, `timestamp`, `key`, `topic`
- **RabbitMQ**: `routing_key`, `delivery_tag`, `exchange`, `headers`, etc.

---

### Development Setup
```bash
git clone https://github.com/jardiscore/messaging.git
cd messaging
make install
make start
make phpunit
```

---

## 📄 License

**PolyForm Noncommercial License 1.0.0** - see [LICENSE](LICENSE) for details

- ✅ **Free for noncommercial use** (personal projects, education, research, open source)
- ✅ **Free for nonprofits** (charities, educational institutions, government)
- ⚠️ **Commercial use requires a separate license** - contact jardiscore@headgent.dev

---

## 🙏 Acknowledgments

Built with ❤️ by [Jardis Core Development](mailto:jardisCore@headgent.dev)

Part of the JardisCore ecosystem for building robust, scalable PHP applications.

---

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/jardiscore/Messaging/issues)
- **Email**: jardisCore@headgent.dev
- **Documentation**: [Coming Soon]

---

**Ready to simplify your messaging infrastructure?** `composer require jardiscore/messaging` 🚀
