<?php

require __DIR__ . '/vendor/autoload.php';

use ElliottLawson\McpPhpSdk\Server\McpServer;
use ElliottLawson\McpPhpSdk\Server\ServerCapabilities;
use ElliottLawson\McpPhpSdk\Contracts\TransportInterface;
use ElliottLawson\McpPhpSdk\Messages\McpMessage;

class MockTransport implements TransportInterface
{
    private $messageHandler;
    private $sentMessages = [];
    private $running = false;
    
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
    
    public function onMessage(callable $handler): void
    {
        $this->messageHandler = $handler;
    }
    
    public function send(string $message): void
    {
        // Always store sent messages, regardless of whether transport is running
        $this->sentMessages[] = $message;
    }
    
    public function receiveMessage(string $message): void
    {
        // Auto-start the transport if it's not running
        if (!$this->running) {
            $this->start();
        }
        
        if ($this->messageHandler) {
            call_user_func($this->messageHandler, $message);
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

// Set up server and mock transport
$capabilities = ServerCapabilities::create()
    ->withResources(true)
    ->withTools(true)
    ->withPrompts(true);
    
$transport = new MockTransport();

$server = new McpServer([
    'name' => 'Test Server',
    'version' => '1.0.0',
    'capabilities' => $capabilities
]);

$server->connect($transport);

// Register a resource
$server->resource(
    'test-resource',
    'test://{param}',
    function (string $uri, array $params) {
        var_dump("Resource handler called with:", $uri, $params);
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
$transport->clearSentMessages();
$request = McpMessage::request('mcp/resources/list', [], 'req-list');
$transport->receiveMessage($request->toJson());

// Check the response
$sentMessages = $transport->getSentMessages();
$response = McpMessage::fromJson($sentMessages[0]);
var_dump("List response:", $response);

// Send a read resource request
$transport->clearSentMessages();
$request = McpMessage::request('mcp/resources/read', [
    'uri' => 'test://hello'
], 'req-read');
$transport->receiveMessage($request->toJson());

// Check the response
$sentMessages = $transport->getSentMessages();
$response = McpMessage::fromJson($sentMessages[0]);
var_dump("Read response:", $response);
