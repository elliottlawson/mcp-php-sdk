<?php

namespace ElliottLawson\McpPhpSdk\Server;

use ElliottLawson\McpPhpSdk\Contracts\TransportInterface;
use ElliottLawson\McpPhpSdk\Errors\McpError;
use ElliottLawson\McpPhpSdk\Messages\McpMessage;
use ElliottLawson\McpPhpSdk\Protocol\Protocol;
use ElliottLawson\McpPhpSdk\Resources\ResourceTemplate;
use ElliottLawson\McpPhpSdk\Tools\Tool;
use ElliottLawson\McpPhpSdk\Transport\WebSocketTransport;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

/**
 * Main MCP server implementation.
 * 
 * This class is responsible for handling MCP connections, resource registration,
 * tool execution, and prompt handling.
 */
class McpServer
{
    /**
     * @var string Server name
     */
    protected string $name;
    
    /**
     * @var string Server version
     */
    protected string $version;
    
    /**
     * @var Protocol The protocol instance
     */
    protected Protocol $protocol;
    
    /**
     * @var TransportInterface The transport instance
     */
    protected ?TransportInterface $transport;
    
    /**
     * @var ServerCapabilities The server capabilities
     */
    protected ServerCapabilities $capabilities;
    
    /**
     * @var array Registered resources
     */
    protected array $resources = [];
    
    /**
     * @var array Registered tools
     */
    protected array $tools = [];
    
    /**
     * @var array Registered prompts
     */
    protected array $prompts = [];
    
    /**
     * @var bool Whether the server is initialized
     */
    protected bool $initialized = false;
    
    /**
     * Create a new MCP server.
     *
     * @param array $config Server configuration
     */
    public function __construct(array $config = [])
    {
        $this->name = $config['name'] ?? 'MCP PHP Server';
        $this->version = $config['version'] ?? '1.0.0';
        $this->transport = $config['transport'] ?? null;
        
        if ($this->transport) {
            $this->protocol = new Protocol($this->transport);
        }
        
        $this->capabilities = $config['capabilities'] ?? ServerCapabilities::create();
    }
    
    /**
     * Get the server name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * Get the server version.
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }
    
    /**
     * Connect to a transport.
     *
     * @param TransportInterface $transport The transport to connect to
     * @return self
     */
    public function connect(TransportInterface $transport): self
    {
        $this->transport = $transport;
        
        // Create protocol with transport
        $this->protocol = new Protocol($transport);
        
        // Register standard method handlers
        $this->registerMethodHandlers();
        
        // Start the transport
        $transport->start();
        
        return $this;
    }
    
    /**
     * Register a resource handler.
     *
     * @param string $name The resource name
     * @param string|ResourceTemplate $uriPattern The URI pattern or template
     * @param callable $handler The resource handler function
     * @return self
     */
    public function resource(string $name, string|ResourceTemplate $uriPattern, callable $handler): self
    {
        if (!$this->capabilities->resources) {
            throw new \RuntimeException('Resources are not enabled in this server');
        }
        
        // If uriPattern is a string, convert it to a ResourceTemplate
        if (is_string($uriPattern)) {
            $uriPattern = new ResourceTemplate($uriPattern);
        }
        
        $this->resources[$name] = [
            'template' => $uriPattern,
            'handler' => $handler
        ];
        
        return $this;
    }
    
    /**
     * Register a tool.
     *
     * @param string $name The tool name
     * @param array|string $schema The tool parameter schema
     * @param callable $handler The tool handler function
     * @return self
     */
    public function tool(string $name, array|string $schema, callable $handler): self
    {
        if (!$this->capabilities->tools) {
            throw new \RuntimeException('Tools are not enabled in this server');
        }
        
        $this->tools[$name] = [
            'schema' => $schema,
            'handler' => $handler
        ];
        
        return $this;
    }
    
    /**
     * Register a prompt.
     *
     * @param string $name The prompt name
     * @param array|string $schema The prompt parameter schema
     * @param callable $handler The prompt handler function
     * @return self
     */
    public function prompt(string $name, array|string $schema, callable $handler): self
    {
        if (!$this->capabilities->prompts) {
            throw new \RuntimeException('Prompts are not enabled in this server');
        }
        
        $this->prompts[$name] = [
            'schema' => $schema,
            'handler' => $handler
        ];
        
        return $this;
    }
    
    /**
     * Send a log message.
     *
     * @param string $level The log level
     * @param string $message The log message
     * @param string|null $logger The logger name
     * @param array|null $data Additional log data
     * @return void
     */
    public function log(string $level, string $message, ?string $logger = null, ?array $data = null): void
    {
        if (!$this->capabilities->logging) {
            return;
        }
        
        $params = [
            'level' => $level,
            'message' => $message
        ];
        
        if ($logger !== null) {
            $params['logger'] = $logger;
        }
        
        if ($data !== null) {
            $params['data'] = $data;
        }
        
        $message = McpMessage::notification('mcp/log', $params);
        $this->protocol->sendNotification('mcp/log', $params);
    }
    
    /**
     * Close the server connection.
     *
     * @return void
     */
    public function close(): void
    {
        // Send a shutdown notification
        $message = McpMessage::notification('mcp/shutdown', []);
        $this->protocol->sendNotification('mcp/shutdown', []);
    }
    
    /**
     * Register the standard MCP method handlers.
     *
     * @return void
     */
    protected function registerMethodHandlers(): void
    {
        // Initialize
        $this->protocol->registerRequestHandler('mcp/initialize', function (array $params) {
            return $this->handleInitialize($params);
        });
        
        // Resources - read
        if ($this->capabilities->resources) {
            $this->protocol->registerRequestHandler('mcp/resources/read', function (array $params) {
                return $this->handleResourceRead($params);
            });
            
            $this->protocol->registerRequestHandler('mcp/resources/list', function (array $params) {
                return $this->handleResourceList($params);
            });
        }
        
        // Tools - execute
        if ($this->capabilities->tools) {
            $this->protocol->registerRequestHandler('mcp/tools/execute', function (array $params) {
                return $this->handleToolExecute($params);
            });
            
            // Add handler for tool listing
            $this->protocol->registerRequestHandler('mcp/tools/list', function (array $params) {
                return $this->handleToolList($params);
            });
        }
        
        // Prompts - execute
        if ($this->capabilities->prompts) {
            $this->protocol->registerRequestHandler('mcp/prompts/execute', function (array $params) {
                return $this->handlePromptExecute($params);
            });
        }
    }
    
    /**
     * Handle the initialize request.
     *
     * @param array $params The initialization parameters
     * @return array The initialization result
     */
    protected function handleInitialize(array $params): array
    {
        $this->initialized = true;
        
        // Prepare capabilities
        $capabilities = $this->capabilities->toArray();
        
        // Return server information
        return [
            'name' => $this->name,
            'version' => $this->version,
            'capabilities' => $capabilities
        ];
    }
    
    /**
     * Handle a resource read request.
     *
     * @param array $params The request parameters
     * @return array|PromiseInterface The response data or a promise
     * @throws McpError If the resource cannot be found
     */
    protected function handleResourceRead(array $params): array|PromiseInterface
    {
        $uri = $params['uri'] ?? null;
        
        if (!$uri) {
            throw new McpError('URI is required', McpError::INVALID_PARAMS);
        }
        
        // Find a matching resource handler
        foreach ($this->resources as $name => $resource) {
            $template = $resource['template'];
            $handler = $resource['handler'];
            
            $matches = $template->match($uri);
            if ($matches !== null) {
                // Call the handler
                $result = $handler($uri, $matches);
                
                // If the result is a promise, return it directly
                if ($result instanceof PromiseInterface) {
                    return $result;
                }
                
                // Return the result as is - let the handler format it correctly
                return $result;
            }
        }
        
        // No matching resource found
        throw new McpError("No matching resource for URI: {$uri}", McpError::RESOURCE_NOT_FOUND);
    }
    
    /**
     * Handle a resource list request.
     *
     * @param array $params The request parameters
     * @return array The response data
     */
    protected function handleResourceList(array $params): array
    {
        $resources = [];
        
        foreach ($this->resources as $name => $resource) {
            $template = $resource['template'];
            
            // Only include listable resources
            if ($template->listOptions !== null) {
                $resources[] = [
                    'name' => $name,
                    'uriPattern' => $template->uriTemplate,
                    'list' => $template->listOptions
                ];
            }
        }
        
        return $resources;
    }
    
    /**
     * Handle a tool execution request.
     *
     * @param array $params The request parameters
     * @return array|PromiseInterface The response data or a promise
     * @throws McpError If the tool cannot be found or execution fails
     */
    protected function handleToolExecute(array $params): array|PromiseInterface
    {
        $name = $params['name'] ?? null;
        $parameters = $params['parameters'] ?? [];
        
        if (!$name) {
            throw new McpError('Tool name is required', McpError::INVALID_PARAMS);
        }
        
        if (!isset($this->tools[$name])) {
            throw new McpError("Tool not found: {$name}", McpError::TOOL_NOT_FOUND);
        }
        
        $tool = $this->tools[$name];
        $handler = $tool['handler'];
        
        try {
            // Call the handler
            $result = $handler($parameters);
            
            // If the result is a promise, return it directly
            if ($result instanceof PromiseInterface) {
                return $result->then(
                    function ($value) {
                        // If the result is already a ToolResult instance, convert to array
                        if (is_array($value) && isset($value['content'])) {
                            return $value;
                        }
                        
                        // Otherwise, create a successful result
                        return ['content' => is_array($value) ? $value : [['type' => 'text', 'text' => (string)$value]]];
                    },
                    function ($error) {
                        // Convert error to proper format
                        $message = $error instanceof \Throwable ? $error->getMessage() : 'Tool execution failed';
                        return [
                            'content' => [['type' => 'text', 'text' => $message]],
                            'isError' => true
                        ];
                    }
                );
            }
            
            // If the result is already a properly formatted tool result, return it
            if (is_array($result) && isset($result['content'])) {
                return $result;
            }
            
            // Otherwise, create a successful result
            return [
                'content' => is_array($result) ? $result : [['type' => 'text', 'text' => (string)$result]]
            ];
        } catch (\Throwable $e) {
            // Tool execution failed
            throw new McpError(
                "Tool execution failed: {$e->getMessage()}",
                McpError::TOOL_EXECUTION_ERROR
            );
        }
    }
    
    /**
     * Handle a tool list request.
     *
     * @param array $params The request parameters
     * @return array The response data
     */
    protected function handleToolList(array $params): array
    {
        $tools = [];
        
        foreach ($this->tools as $name => $tool) {
            $tools[] = [
                'name' => $name,
                'schema' => $tool['schema']
            ];
        }
        
        return $tools;
    }
    
    /**
     * Handle a prompt execution request.
     *
     * @param array $params The request parameters
     * @return array|PromiseInterface The response data or a promise
     * @throws McpError If the prompt cannot be found or execution fails
     */
    protected function handlePromptExecute(array $params): array|PromiseInterface
    {
        $name = $params['name'] ?? null;
        $parameters = $params['parameters'] ?? [];
        
        if (!$name) {
            throw new McpError('Prompt name is required', McpError::INVALID_PARAMS);
        }
        
        if (!isset($this->prompts[$name])) {
            throw new McpError("Prompt not found: {$name}", McpError::METHOD_NOT_FOUND);
        }
        
        $prompt = $this->prompts[$name];
        $handler = $prompt['handler'];
        
        try {
            // Call the handler
            $result = $handler($parameters);
            
            // If the result is a promise, return it directly
            if ($result instanceof PromiseInterface) {
                return $result;
            }
            
            // Otherwise, return the result
            return $result;
        } catch (\Throwable $e) {
            // Prompt execution failed
            throw new McpError(
                "Prompt execution failed: {$e->getMessage()}",
                McpError::PROMPT_EXECUTION_ERROR
            );
        }
    }
}
