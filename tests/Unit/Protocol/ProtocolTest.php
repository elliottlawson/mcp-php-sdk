<?php

namespace ElliottLawson\Tests\Unit\Protocol;

use ElliottLawson\McpPhpSdk\Protocol\Protocol;
use ElliottLawson\McpPhpSdk\Messages\McpMessage;
use PHPUnit\Framework\TestCase;
use React\Promise\Deferred;

class ProtocolTest extends TestCase
{
    private Protocol $protocol;
    
    protected function setUp(): void
    {
        $this->protocol = new Protocol();
    }
    
    public function testCanRegisterAndHandleRequestHandlers(): void
    {
        $handlerCalled = false;
        
        // Register a handler
        $this->protocol->registerRequestHandler('test.method', function ($params) use (&$handlerCalled) {
            $handlerCalled = true;
            $this->assertEquals(['param' => 'value'], $params);
            return ['response' => 'success'];
        });
        
        // Create a test message
        $message = McpMessage::request('test.method', ['param' => 'value'], 'req-123');
        
        // Process the message
        $result = null;
        $this->protocol->processMessage($message, function ($response) use (&$result) {
            $result = $response;
        });
        
        // Check that handler was called
        $this->assertTrue($handlerCalled);
        
        // Check the response
        $this->assertInstanceOf(McpMessage::class, $result);
        $this->assertEquals('req-123', $result->id);
        $this->assertEquals(['response' => 'success'], $result->result);
    }
    
    public function testCanRegisterAndHandleNotificationHandlers(): void
    {
        $handlerCalled = false;
        
        // Register a handler
        $this->protocol->registerNotificationHandler('test.event', function ($params) use (&$handlerCalled) {
            $handlerCalled = true;
            $this->assertEquals(['data' => 'notification'], $params);
        });
        
        // Create a test message
        $message = McpMessage::notification('test.event', ['data' => 'notification']);
        
        // Process the message
        $this->protocol->processMessage($message, function ($response) {
            $this->fail('Notifications should not generate a response');
        });
        
        // Check that handler was called
        $this->assertTrue($handlerCalled);
    }
    
    public function testUnknownRequestMethodReturnsMethodNotFoundError(): void
    {
        // Create a test message for an unknown method
        $message = McpMessage::request('unknown.method', [], 'req-123');
        
        // Process the message
        $result = null;
        $this->protocol->processMessage($message, function ($response) use (&$result) {
            $result = $response;
        });
        
        // Check the error response
        $this->assertInstanceOf(McpMessage::class, $result);
        $this->assertEquals('req-123', $result->id);
        $this->assertEquals(-32601, $result->error['code']);
        $this->assertEquals('Method not found', $result->error['message']);
    }
    
    public function testCanSendRequestAndReceiveResponse(): void
    {
        $promise = $this->protocol->request('test.method', ['param' => 'value']);
        
        // Create a mocked response message
        $response = McpMessage::response(['result' => 'success'], $this->protocol->getLastRequestId());
        
        // Process the response
        $this->protocol->processMessage($response);
        
        // Check that the promise is resolved with the result
        $result = null;
        $promise->then(function ($value) use (&$result) {
            $result = $value;
        });
        
        $this->assertEquals(['result' => 'success'], $result);
    }
    
    public function testCanSendNotification(): void
    {
        // Create a protocol instance with no transport
        $protocol = new Protocol();
        
        // Send a notification
        $message = $protocol->notify('test.event', ['data' => 'notification']);
        
        // Check the message
        $this->assertInstanceOf(McpMessage::class, $message);
        $this->assertEquals('test.event', $message->method);
        $this->assertEquals(['data' => 'notification'], $message->params);
        $this->assertFalse(isset($message->id));
    }
}
