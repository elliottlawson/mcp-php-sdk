# MCP PHP SDK

A PHP SDK for the Model Context Protocol (MCP), allowing seamless integration between applications and Large Language Models (LLMs).

## Requirements

- PHP 8.1 or higher
- Composer

## Installation

```bash
composer require elliottlawson/mcp-php-sdk
```

## Overview

The Model Context Protocol (MCP) is an open standard for connecting large language models (LLMs) to applications, APIs, and external data sources. This PHP SDK provides a structured implementation for PHP applications to integrate with MCP.

## Main Components

### Protocol Layer

The Protocol layer handles the core MCP message format and communication patterns:

- `McpMessage`: Represents JSON-RPC 2.0 messages used in MCP
- `Protocol`: Handles message framing, request-response linking, and pattern registration

### Transport Layer

The Transport layer defines how messages are sent and received:

- `TransportInterface`: Defines the contract for all transport implementations
- `StdioTransport`: Implementation for command-line applications using standard input/output
- `SseTransport`: Implementation for web applications using Server-Sent Events (SSE)

### Resources

Resources are data sources that LLMs can access:

- `Resource`: Represents a data source
- `ResourceTemplate`: Handles URI templates and pattern matching
- `ResourceContent`: Represents the content of a resource

### Tools

Tools enable LLMs to execute code and produce side effects:

- `Tool`: Represents a tool definition
- `ToolResult`: Encapsulates the result of a tool execution

### Prompts

Prompts define reusable templates for LLM interactions:

- `Prompt`: Represents a prompt definition
- `PromptArgument`: Defines arguments for prompts
- `PromptResult`: Represents the result of a prompt execution

### Server

The server components tie everything together:

- `McpServer`: The main server class that manages connections, resources, tools, and prompts
- `ServerCapabilities`: Defines the capabilities of the server

## Quick Start

### Basic CLI Server

```php
<?php

require __DIR__ . '/vendor/autoload.php';

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

// Start the server with stdio transport
try {
    $transport = new StdioTransport();
    $server->connect($transport);
    
    // The server will now run until the process is terminated
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
```

### Web Server with SSE

See the examples directory for a complete HTTP/SSE server implementation.

## Advanced Usage

### Working with Resources

Resources represent data sources that can be accessed by LLMs. To register a resource:

```php
$server->resource(
    'name',            // The resource name
    'uri://{pattern}', // The URI pattern (with placeholders)
    function (string $uri, array $params) {
        // Handler function that returns resource content
        return [
            'contents' => [
                [
                    'uri' => $uri,
                    'text' => 'Content goes here'
                    // Can also include 'mimeType' for non-text content
                ]
            ]
        ];
    }
);
```

### Working with Tools

Tools enable LLMs to perform actions and have side effects. To register a tool:

```php
$server->tool(
    'name',          // The tool name
    [                // The parameter schema (JSON Schema format)
        'type' => 'object',
        'properties' => [
            'param1' => ['type' => 'string'],
            'param2' => ['type' => 'number']
        ],
        'required' => ['param1']
    ],
    function (array $params) {
        // Handler function that executes the tool
        return [
            'content' => [
                ['type' => 'text', 'text' => 'Result of the tool execution']
                // Can include other content types as needed
            ]
        ];
    }
);
```

### Working with Prompts

Prompts define reusable templates for LLM interactions. To register a prompt:

```php
$server->prompt(
    'name',          // The prompt name
    [                // The parameter schema (JSON Schema format)
        'type' => 'object',
        'properties' => [
            'param1' => ['type' => 'string'],
            'param2' => ['type' => 'number']
        ],
        'required' => ['param1']
    ],
    function (array $params) {
        // Handler function that generates the prompt
        return [
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => "The user asked about: {$params['param1']}"]
            ],
            'description' => 'A helpful prompt about ' . $params['param1']
        ];
    }
);
```

## Running the Examples

The SDK includes several examples in the `examples` directory:

- `basic-server.php`: A simple CLI-based MCP server
- `http-server.php`: An HTTP server with SSE transport
- `prompts-server.php`: A server demonstrating the prompts feature

To run the basic server:

```bash
php examples/basic-server.php
```

To run the HTTP server:

```bash
php -S localhost:8080 examples/http-server.php
```

Then visit http://localhost:8080 in your browser.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License.
