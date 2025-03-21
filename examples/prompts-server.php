<?php
/**
 * MCP Server with Prompts Example
 * 
 * This example creates an MCP server that includes prompt handling.
 */

require __DIR__ . '/../vendor/autoload.php';

use ElliottLawson\McpPhpSdk\Server\McpServer;
use ElliottLawson\McpPhpSdk\Server\ServerCapabilities;
use ElliottLawson\McpPhpSdk\Transport\StdioTransport;
use ElliottLawson\McpPhpSdk\Prompts\PromptArgument;
use ElliottLawson\McpPhpSdk\Prompts\PromptResult;

// Create server capabilities
$capabilities = ServerCapabilities::create()
    ->withResources(true)
    ->withTools(true)
    ->withPrompts(true);

// Create an MCP server
$server = new McpServer([
    'name' => 'Prompts Example Server',
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

// Register a greeting prompt
$server->prompt(
    'greeting',
    [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'The name to greet'
            ],
            'formal' => [
                'type' => 'boolean',
                'description' => 'Whether to use formal language'
            ]
        ],
        'required' => ['name']
    ],
    function (array $params) {
        $name = $params['name'];
        $formal = $params['formal'] ?? false;
        
        $greeting = $formal ? 'Hello' : 'Hi';
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a helpful assistant that greets users professionally.'
            ],
            [
                'role' => 'assistant',
                'content' => "{$greeting}, {$name}! How can I assist you today?"
            ]
        ];
        
        return [
            'messages' => $messages,
            'description' => "A greeting for {$name}"
        ];
    }
);

// Register a content generation prompt
$server->prompt(
    'generate-content',
    [
        'type' => 'object',
        'properties' => [
            'topic' => [
                'type' => 'string',
                'description' => 'The topic to generate content about'
            ],
            'style' => [
                'type' => 'string',
                'description' => 'The style of content to generate',
                'enum' => ['professional', 'casual', 'academic']
            ],
            'length' => [
                'type' => 'string',
                'description' => 'The length of content to generate',
                'enum' => ['short', 'medium', 'long']
            ]
        ],
        'required' => ['topic']
    ],
    function (array $params) {
        $topic = $params['topic'];
        $style = $params['style'] ?? 'professional';
        $length = $params['length'] ?? 'medium';
        
        // Determine the tone based on style
        $tone = match($style) {
            'professional' => 'Use a professional tone with industry terminology',
            'casual' => 'Use a conversational, friendly tone',
            'academic' => 'Use an academic tone with citations and formal language',
            default => 'Use a professional tone'
        };
        
        // Determine the length guidance
        $lengthGuidance = match($length) {
            'short' => 'Keep it brief, around 100 words',
            'medium' => 'Write about 300 words',
            'long' => 'Write a comprehensive piece around 500-600 words',
            default => 'Write about 300 words'
        };
        
        $messages = [
            [
                'role' => 'system',
                'content' => "You are a content generation assistant. {$tone}. {$lengthGuidance}."
            ],
            [
                'role' => 'user',
                'content' => "Please write content about: {$topic}"
            ],
            [
                'role' => 'assistant',
                'content' => "I'd be happy to write about {$topic} for you. Here's what I can provide:\n\n[LLM would generate content here based on the topic, style, and length]"
            ]
        ];
        
        return [
            'messages' => $messages,
            'description' => "Generated content about {$topic} in {$style} style ({$length})"
        ];
    }
);

// Log startup information
$server->log('info', 'Prompts server starting up');

// Start the server with stdio transport
try {
    $transport = new StdioTransport();
    $server->connect($transport);
    
    // The server will now run until the process is terminated
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
