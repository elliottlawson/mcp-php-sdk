<?php

namespace ElliottLawson\McpPhpSdk\Tests\Unit\Protocol;

use ElliottLawson\McpPhpSdk\Messages\McpMessage;
use PHPUnit\Framework\TestCase;

class McpMessageTest extends TestCase
{
    public function testCanCreateRequest(): void
    {
        $message = McpMessage::request('test.method', ['param' => 'value'], 'req-123');
        
        $this->assertEquals('2.0', $message->jsonrpc);
        $this->assertEquals('test.method', $message->method);
        $this->assertEquals(['param' => 'value'], $message->params);
        $this->assertEquals('req-123', $message->id);
    }
    
    public function testCanCreateResponse(): void
    {
        $message = McpMessage::response(['result' => 'value'], 'req-123');
        
        $this->assertEquals('2.0', $message->jsonrpc);
        $this->assertEquals(['result' => 'value'], $message->result);
        $this->assertEquals('req-123', $message->id);
        $this->assertFalse(isset($message->error));
    }
    
    public function testCanCreateErrorResponse(): void
    {
        $error = [
            'code' => -32000,
            'message' => 'Test error'
        ];
        
        $message = McpMessage::error($error, 'req-123');
        
        $this->assertEquals('2.0', $message->jsonrpc);
        $this->assertEquals($error, $message->error);
        $this->assertEquals('req-123', $message->id);
        $this->assertFalse(isset($message->result));
    }
    
    public function testCanCreateNotification(): void
    {
        $message = McpMessage::notification('test.event', ['data' => 'value']);
        
        $this->assertEquals('2.0', $message->jsonrpc);
        $this->assertEquals('test.event', $message->method);
        $this->assertEquals(['data' => 'value'], $message->params);
        $this->assertFalse(isset($message->id));
    }
    
    public function testCanSerializeToJson(): void
    {
        $message = McpMessage::request('test.method', ['param' => 'value'], 'req-123');
        $json = $message->toJson();
        
        $expected = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'test.method',
            'params' => ['param' => 'value'],
            'id' => 'req-123'
        ]);
        
        $this->assertEquals($expected, $json);
    }
    
    public function testCanCreateFromJson(): void
    {
        $json = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'test.method',
            'params' => ['param' => 'value'],
            'id' => 'req-123'
        ]);
        
        $message = McpMessage::fromJson($json);
        
        $this->assertEquals('2.0', $message->jsonrpc);
        $this->assertEquals('test.method', $message->method);
        $this->assertEquals(['param' => 'value'], $message->params);
        $this->assertEquals('req-123', $message->id);
    }
    
    public function testCanDetermineMessageType(): void
    {
        $request = McpMessage::request('test.method', [], 'req-123');
        $response = McpMessage::response([], 'req-123');
        $error = McpMessage::error(['code' => -32000, 'message' => 'Error'], 'req-123');
        $notification = McpMessage::notification('test.event', []);
        
        $this->assertTrue($request->isRequest());
        $this->assertFalse($request->isResponse());
        $this->assertFalse($request->isError());
        $this->assertFalse($request->isNotification());
        
        $this->assertFalse($response->isRequest());
        $this->assertTrue($response->isResponse());
        $this->assertFalse($response->isError());
        $this->assertFalse($response->isNotification());
        
        $this->assertFalse($error->isRequest());
        $this->assertFalse($error->isResponse());
        $this->assertTrue($error->isError());
        $this->assertFalse($error->isNotification());
        
        $this->assertFalse($notification->isRequest());
        $this->assertFalse($notification->isResponse());
        $this->assertFalse($notification->isError());
        $this->assertTrue($notification->isNotification());
    }
}
