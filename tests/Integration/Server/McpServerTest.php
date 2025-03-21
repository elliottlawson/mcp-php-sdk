<?php

namespace ElliottLawson\McpPhpSdk\Tests\Integration\Server;

use ElliottLawson\McpPhpSdk\Contracts\TransportInterface;
use ElliottLawson\McpPhpSdk\Messages\McpMessage;
use ElliottLawson\McpPhpSdk\Resources\ResourceTemplate;
use ElliottLawson\McpPhpSdk\Server\McpServer;
use ElliottLawson\McpPhpSdk\Server\ServerCapabilities;
use ElliottLawson\McpPhpSdk\Tools\Tool;
use PHPUnit\Framework\TestCase;

/**
 * Mock transport for testing
 */
class MockTransport implements TransportInterface
{
    private $messageHandler;
    private $sentMessages = [];
    private $running = false;
    
    public function send(string $message): void
    {
        $this->sentMessages[] = $message;
    }
    
    public function setMessageHandler(callable $handler): TransportInterface
    {
        $this->messageHandler = $handler;
        return $this;
    }
    
    public function start(): void
    {
        $this->running = true;
    }
    
    public function stop(): void
    {
        $this->running = false;
    }
    
    public function isRunning(): bool
    {
        return $this->running;
    }
    
    public function receiveMessage(string $message): void
    {
        // Auto start if not already running
        if (!$this->isRunning()) {
            $this->start();
        }
        
        if ($this->messageHandler) {
            ($this->messageHandler)($message);
        }
    }
    
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }
    
    public function clearSentMessages(): void
    {
        $this->sentMessages = [];
    }
}

/**
 * Integration test for the McpServer class
 */
class McpServerTest extends TestCase
{
    private McpServer $server;
    private MockTransport $transport;
    
    protected function setUp(): void
    {
        $capabilities = ServerCapabilities::create()
            ->withResources(true)
            ->withTools(true)
            ->withPrompts(true);
            
        $this->transport = new MockTransport();
        
        $this->server = new McpServer([
            'name' => 'Test Server',
            'version' => '1.0.0',
            'capabilities' => $capabilities
        ]);
        
        $this->server->connect($this->transport);
    }
    
    public function testInitializeRequest(): void
    {
        // Send an initialize request
        $request = McpMessage::request('mcp/initialize', [
            'client' => [
                'name' => 'Test Client',
                'version' => '1.0.0'
            ]
        ], 'req-init');
        
        $this->transport->receiveMessage($request->toJson());
        
        // Get the response
        $sentMessages = $this->transport->getSentMessages();
        $this->assertCount(1, $sentMessages);
        
        $response = McpMessage::fromJson($sentMessages[0]);
        
        // Check the response
        $this->assertEquals('req-init', $response->id);
        $this->assertTrue(isset($response->result));
        $this->assertEquals('Test Server', $response->result['name']);
        $this->assertEquals('1.0.0', $response->result['version']);
        $this->assertTrue(isset($response->result['capabilities']));
        $this->assertTrue($response->result['capabilities']['resources']);
        $this->assertTrue($response->result['capabilities']['tools']);
        $this->assertTrue($response->result['capabilities']['prompts']);
    }
    
    public function testResourceRegistrationAndAccess(): void
    {
        // Register a resource
        $this->server->resource(
            'test-resource',
            'test://{param}',
            function (string $uri, array $params) {
                return [
                    'contents' => [
                        [
                            'uri' => $uri,
                            'text' => "Param: {$params['param']}"
                        ]
                    ]
                ];
            }
        );
        
        // Send a list resources request
        $this->transport->clearSentMessages();
        $request = McpMessage::request('mcp/resources/list', [], 'req-list');
        $this->transport->receiveMessage($request->toJson());
        
        // Check the response contains our resource
        $sentMessages = $this->transport->getSentMessages();
        $response = McpMessage::fromJson($sentMessages[0]);
        
        $this->assertEquals('req-list', $response->id);
        $this->assertTrue(isset($response->result));
        $this->assertIsArray($response->result);
        $this->assertCount(1, $response->result);
        $this->assertEquals('test-resource', $response->result[0]['name']);
        $this->assertEquals('test://{param}', $response->result[0]['uriPattern']);
        
        // Send a read resource request
        $this->transport->clearSentMessages();
        $request = McpMessage::request('mcp/resources/read', [
            'uri' => 'test://hello'
        ], 'req-read');
        $this->transport->receiveMessage($request->toJson());
        
        // Check the response content
        $sentMessages = $this->transport->getSentMessages();
        $response = McpMessage::fromJson($sentMessages[0]);
        
        $this->assertEquals('req-read', $response->id);
        $this->assertTrue(isset($response->result));
        $this->assertIsArray($response->result);
        $this->assertArrayHasKey('contents', $response->result);
        $this->assertCount(1, $response->result['contents']);
        $this->assertEquals('test://hello', $response->result['contents'][0]['uri']);
        $this->assertEquals('Param: hello', $response->result['contents'][0]['text']);
    }
    
    public function testToolRegistrationAndExecution(): void
    {
        // Register a tool
        $this->server->tool(
            'test-tool',
            [
                'type' => 'object',
                'properties' => [
                    'param' => ['type' => 'string']
                ]
            ],
            function (array $params) {
                return [
                    'content' => [
                        ['type' => 'text', 'text' => "Param: {$params['param']}"]
                    ]
                ];
            }
        );
        
        // Send a list tools request
        $this->transport->clearSentMessages();
        $request = McpMessage::request('mcp/tools/list', [], 'req-list');
        $this->transport->receiveMessage($request->toJson());
        
        // Check the response contains our tool
        $sentMessages = $this->transport->getSentMessages();
        $response = McpMessage::fromJson($sentMessages[0]);
        
        $this->assertEquals('req-list', $response->id);
        $this->assertTrue(isset($response->result));
        $this->assertIsArray($response->result);
        $this->assertCount(1, $response->result);
        $this->assertEquals('test-tool', $response->result[0]['name']);
        $this->assertEquals('object', $response->result[0]['schema']['type']);
        
        // Send an execute tool request
        $this->transport->clearSentMessages();
        $request = McpMessage::request('mcp/tools/execute', [
            'name' => 'test-tool',
            'parameters' => ['param' => 'hello']
        ], 'req-exec');
        $this->transport->receiveMessage($request->toJson());
        
        // Check the response content
        $sentMessages = $this->transport->getSentMessages();
        $response = McpMessage::fromJson($sentMessages[0]);
        
        $this->assertEquals('req-exec', $response->id);
        $this->assertTrue(isset($response->result));
        $this->assertIsArray($response->result);
        $this->assertArrayHasKey('content', $response->result);
        $this->assertCount(1, $response->result['content']);
        $this->assertEquals('text', $response->result['content'][0]['type']);
        $this->assertEquals('Param: hello', $response->result['content'][0]['text']);
    }
    
    public function testInvalidResourceRequestHandling(): void
    {
        // Try to access a non-existent resource
        $request = McpMessage::request('mcp/resources/read', [
            'uri' => 'nonexistent://resource'
        ], 'req-invalid');
        $this->transport->receiveMessage($request->toJson());
        
        // Check the error response
        $sentMessages = $this->transport->getSentMessages();
        $response = McpMessage::fromJson($sentMessages[0]);
        
        $this->assertEquals('req-invalid', $response->id);
        $this->assertTrue(isset($response->error));
        $this->assertFalse(isset($response->result));
        $this->assertEquals(-32000, $response->error['code']);
        $this->assertStringContainsString('No matching resource', $response->error['message']);
    }
    
    public function testInvalidToolExecutionHandling(): void
    {
        // Try to execute a non-existent tool
        $request = McpMessage::request('mcp/tools/execute', [
            'name' => 'nonexistent-tool',
            'parameters' => []
        ], 'req-invalid');
        $this->transport->receiveMessage($request->toJson());
        
        // Check the error response
        $sentMessages = $this->transport->getSentMessages();
        $response = McpMessage::fromJson($sentMessages[0]);
        
        $this->assertEquals('req-invalid', $response->id);
        $this->assertTrue(isset($response->error));
        $this->assertFalse(isset($response->result));
        $this->assertEquals(-32000, $response->error['code']);
        $this->assertStringContainsString('Tool not found', $response->error['message']);
    }
}
