<?php

namespace ElliottLawson\McpPhpSdk\Tests\Transport;

use ElliottLawson\McpPhpSdk\Transport\SseTransport;
use ElliottLawson\McpPhpSdk\Contracts\TransportInterface;
use PHPUnit\Framework\TestCase;

class SseTransportTest extends TestCase
{
    /**
     * @var SseTransport
     */
    protected $transport;
    
    /**
     * @var array Captured headers
     */
    protected $capturedHeaders = [];
    
    /**
     * @var string Captured output
     */
    protected $capturedOutput = '';
    
    /**
     * @var bool Flush was called
     */
    protected $flushCalled = false;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset captured data
        $this->capturedHeaders = [];
        $this->capturedOutput = '';
        $this->flushCalled = false;
        
        // Create transport with test callbacks
        $this->transport = new SseTransport('/test/message', 15);
        
        // Set test callbacks
        $this->transport->setHeaderCallback(function ($name, $value) {
            $this->capturedHeaders[$name] = $value;
        });
        
        $this->transport->setOutputCallback(function ($data) {
            $this->capturedOutput .= $data;
        });
        
        $this->transport->setFlushCallback(function () {
            $this->flushCalled = true;
        });
    }

    /** @test */
    public function it_implements_transport_interface()
    {
        $this->assertInstanceOf(TransportInterface::class, $this->transport);
    }

    /** @test */
    public function it_sets_and_gets_message_store_id()
    {
        $testId = 'test-connection-123';
        $this->transport->setMessageStoreId($testId);
        
        $this->assertEquals($testId, $this->transport->getMessageStoreId());
    }

    /** @test */
    public function it_can_be_started_and_stopped()
    {
        $this->assertFalse($this->transport->isRunning());
        
        $this->transport->start();
        $this->assertTrue($this->transport->isRunning());
        
        // Verify headers were set
        $this->assertArrayHasKey('Content-Type', $this->capturedHeaders);
        $this->assertEquals('text/event-stream', $this->capturedHeaders['Content-Type']);
        
        $this->transport->stop();
        $this->assertFalse($this->transport->isRunning());
    }

    /** @test */
    public function it_sets_and_uses_callbacks()
    {
        // Clear previous captured data
        $this->capturedHeaders = [];
        $this->capturedOutput = '';
        $this->flushCalled = false;
        
        // Start transport to initialize SSE
        $this->transport->start();
        
        // Verify header callback was used
        $this->assertArrayHasKey('Content-Type', $this->capturedHeaders);
        $this->assertEquals('text/event-stream', $this->capturedHeaders['Content-Type']);
        
        // Reset to test message sending
        $this->capturedOutput = '';
        $this->flushCalled = false;
        
        // Send a message
        $testMessage = 'test message';
        $this->transport->send($testMessage);
        
        // Verify output callback was used
        $this->assertStringContainsString($testMessage, $this->capturedOutput);
        
        // Verify flush callback was used
        $this->assertTrue($this->flushCalled);
    }

    /** @test */
    public function it_sets_message_handler()
    {
        $handlerCalled = false;
        $testMessage = 'test message';
        
        $handler = function ($message) use (&$handlerCalled, $testMessage) {
            $handlerCalled = true;
            // Use static assertion to avoid $this context issues in closures
            TestCase::assertEquals($testMessage, $message);
        };
        
        $this->transport->setMessageHandler($handler);
        
        // We need to call the handler directly since we don't have access to the protected property
        $reflection = new \ReflectionClass($this->transport);
        $property = $reflection->getProperty('messageHandler');
        $property->setAccessible(true);
        $messageHandler = $property->getValue($this->transport);
        
        // Call the handler
        $messageHandler($testMessage);
        
        $this->assertTrue($handlerCalled, 'Message handler should be called');
    }
}
