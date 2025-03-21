<?php
/**
 * Basic MCP Server Example
 * 
 * This example creates a simple MCP server that registers a resource and a tool.
 */

require __DIR__ . '/../vendor/autoload.php';

use ElliottLawson\McpPhpSdk\Server\McpServer;
use ElliottLawson\McpPhpSdk\Server\ServerCapabilities;
use ElliottLawson\McpPhpSdk\Transport\StdioTransport;

// Create server capabilities
$capabilities = ServerCapabilities::create()
    ->withResources(true)
    ->withTools(true);

// Create an MCP server
$server = new McpServer([
    'name' => 'Example MCP Server',
    'version' => '1.0.0',
    'capabilities' => $capabilities
]);

// Register a simple echo resource
$server->resource(
    'echo',
    'echo://{message}',
    function (string $uri, array $params) {
        return [
            'contents' => [
                [
                    'uri' => $uri,
                    'text' => "You said: {$params['message']}"
                ]
            ]
        ];
    }
);

// Register a calculator tool
$server->tool(
    'calculator',
    [
        'type' => 'object',
        'properties' => [
            'operation' => ['type' => 'string', 'enum' => ['add', 'subtract', 'multiply', 'divide']],
            'a' => ['type' => 'number'],
            'b' => ['type' => 'number']
        ],
        'required' => ['operation', 'a', 'b']
    ],
    function (array $params) {
        $a = $params['a'];
        $b = $params['b'];
        
        switch ($params['operation']) {
            case 'add':
                $result = $a + $b;
                break;
            case 'subtract':
                $result = $a - $b;
                break;
            case 'multiply':
                $result = $a * $b;
                break;
            case 'divide':
                if ($b === 0) {
                    return [
                        'content' => [['type' => 'text', 'text' => 'Cannot divide by zero']],
                        'isError' => true
                    ];
                }
                $result = $a / $b;
                break;
            default:
                return [
                    'content' => [['type' => 'text', 'text' => 'Unknown operation']],
                    'isError' => true
                ];
        }
        
        return [
            'content' => [
                ['type' => 'text', 'text' => "Result: $result"]
            ]
        ];
    }
);

// Log startup information
$server->log('info', 'Server starting up');

// Start the server with stdio transport
try {
    $transport = new StdioTransport();
    $server->connect($transport);
    
    // The server will now run until the process is terminated
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
