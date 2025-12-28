<?php

declare(strict_types=1);

namespace JardisCore\Messaging\Tests\Unit\Connection;

use JardisCore\Messaging\Connection\ExternalKafkaConnection;
use JardisPsr\Messaging\Exception\ConnectionException;
use PHPUnit\Framework\TestCase;
use RdKafka\Producer;

class ExternalKafkaConnectionTest extends TestCase
{
    private Producer $producer;

    protected function setUp(): void
    {
        $this->producer = $this->createMock(Producer::class);
    }

    public function testConstructorAcceptsExternalProducer(): void
    {
        $connection = new ExternalKafkaConnection($this->producer);

        $this->assertInstanceOf(ExternalKafkaConnection::class, $connection);
    }

    public function testIsConnectedReturnsTrueByDefault(): void
    {
        $connection = new ExternalKafkaConnection($this->producer);

        $this->assertTrue($connection->isConnected());
    }

    public function testGetClientReturnsExternalProducer(): void
    {
        $connection = new ExternalKafkaConnection($this->producer);

        $this->assertSame($this->producer, $connection->getClient());
    }

    public function testConnectIsNoOp(): void
    {
        $connection = new ExternalKafkaConnection($this->producer);

        // Should not throw exception
        $connection->connect();

        $this->assertTrue($connection->isConnected());
    }

    public function testDisconnectRespectsFlushOnDisconnectFlag(): void
    {
        $this->producer->expects($this->never())->method('flush');

        $connection = new ExternalKafkaConnection($this->producer, flushOnDisconnect: false);
        $connection->disconnect();

        $this->assertFalse($connection->isConnected());
    }

    public function testDisconnectFlushesWhenFlagIsTrue(): void
    {
        $this->producer->expects($this->once())
            ->method('flush')
            ->with(10000);

        $connection = new ExternalKafkaConnection($this->producer, flushOnDisconnect: true);
        $connection->disconnect();

        $this->assertFalse($connection->isConnected());
    }

    public function testGetClientThrowsExceptionWhenDisconnected(): void
    {
        $connection = new ExternalKafkaConnection($this->producer, flushOnDisconnect: false);
        $connection->disconnect();

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('External Kafka client is not available');

        $connection->getClient();
    }

    public function testIsConnectedReturnsFalseAfterDisconnect(): void
    {
        $connection = new ExternalKafkaConnection($this->producer);
        $connection->disconnect();

        $this->assertFalse($connection->isConnected());
    }

    public function testConnectAfterDisconnect(): void
    {
        $connection = new ExternalKafkaConnection($this->producer, flushOnDisconnect: false);
        $connection->disconnect();

        // Reconnecting should work
        $connection->connect();

        $this->assertTrue($connection->isConnected());
    }

    public function testMultipleConnectCallsAreIdempotent(): void
    {
        $connection = new ExternalKafkaConnection($this->producer);

        $connection->connect();
        $connection->connect();
        $connection->connect();

        $this->assertTrue($connection->isConnected());
    }

    public function testMultipleDisconnectCallsWithFlush(): void
    {
        $this->producer->expects($this->once())
            ->method('flush')
            ->with(10000);

        $connection = new ExternalKafkaConnection($this->producer, flushOnDisconnect: true);

        $connection->disconnect();
        $connection->disconnect(); // Should not flush again

        $this->assertFalse($connection->isConnected());
    }
}
