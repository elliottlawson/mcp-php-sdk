<?php

namespace ElliottLawson\McpPhpSdk\Protocol;

use ElliottLawson\McpPhpSdk\Contracts\TransportInterface;
use ElliottLawson\McpPhpSdk\Errors\McpError;
use ElliottLawson\McpPhpSdk\Messages\McpMessage; 
use InvalidArgumentException;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * Protocol class for handling MCP message framing and communication.
 * 
 * This class implements the JSON-RPC 2.0 protocol for MCP, handling
 * sending and receiving messages, registering handlers, and managing
 * pending requests.
 */
class Protocol
{
    /**
     * @var TransportInterface|null The transport to use for sending/receiving messages
     */
    private ?TransportInterface $transport = null;
    
    /**
     * @var array<string, callable> Map of method names to request handlers
     */
    private array $requestHandlers = [];
    
    /**
     * @var array<string, callable> Map of method names to notification handlers
     */
    private array $notificationHandlers = [];
    
    /**
     * @var array<string, Deferred> Map of request IDs to deferreds for pending requests
     */
    private array $pendingRequests = [];

    /**
     * @var string|null Last request ID that was sent
     */
    private ?string $lastRequestId = null;
    
    /**
     * Create a new Protocol instance.
     *
     * @param TransportInterface|null $transport The transport to use (optional)
     */
    public function __construct(?TransportInterface $transport = null)
    {
        $this->transport = $transport;
        
        if ($this->transport !== null) {
            $this->transport->setMessageHandler(function ($message) {
                $this->handleMessage($message);
            });
        }
    }
    
    /**
     * Register a handler for a request method.
     *
     * @param string $method The method name to handle
     * @param callable $handler The handler function that takes params and returns a result
     * @return $this
     */
    public function registerRequestHandler(string $method, callable $handler): self
    {
        $this->requestHandlers[$method] = $handler;
        return $this;
    }
    
    /**
     * Register a handler for a notification method.
     *
     * @param string $method The method name to handle
     * @param callable $handler The handler function that takes params
     * @return $this
     */
    public function registerNotificationHandler(string $method, callable $handler): self
    {
        $this->notificationHandlers[$method] = $handler;
        return $this;
    }
    
    /**
     * Send a request to the other endpoint.
     *
     * @param string $method The method to call
     * @param array $params The parameters to pass
     * @return PromiseInterface A promise that resolves with the result or rejects with an error
     */
    public function sendRequest(string $method, array $params = []): PromiseInterface
    {
        $id = Uuid::uuid4()->toString();
        $this->lastRequestId = $id;
        $message = McpMessage::request($method, $params, $id);
        $deferred = new Deferred();
        
        $this->pendingRequests[$id] = $deferred;
        
        if ($this->transport !== null) {
            $this->transport->send($message->toJson());
        }
        
        return $deferred->promise();
    }
    
    /**
     * Send a notification.
     *
     * @param string $method The method to call
     * @param array $params The parameters to pass
     * @return McpMessage
     */
    public function sendNotification(string $method, array $params = []): McpMessage
    {
        $message = McpMessage::notification($method, $params);
        if ($this->transport !== null) {
            $this->transport->send($message->toJson());
        }
        return $message;
    }
    
    /**
     * Send a notification (alias for sendNotification).
     *
     * @param string $method The method to call
     * @param array $params The parameters to pass
     * @return McpMessage The notification message
     */
    public function notify(string $method, array $params = []): McpMessage
    {
        return $this->sendNotification($method, $params);
    }
    
    /**
     * Handle a message received from the transport.
     *
     * @param string $json The JSON-encoded message
     */
    private function handleMessage(string $json): void
    {
        try {
            $message = McpMessage::fromJson($json);
            
            if ($message->isRequest()) {
                $this->handleRequest($message);
            } elseif ($message->isResponse()) {
                $this->handleResponse($message);
            } elseif ($message->isError()) {
                $this->handleError($message);
            } elseif ($message->isNotification()) {
                $this->handleNotification($message);
            } else {
                throw new InvalidArgumentException('Invalid message format');
            }
        } catch (\Exception $e) {
            // Log the error but don't crash
            error_log('Error handling message: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle a request message.
     *
     * @param McpMessage $message The request message
     * @param callable|null $onResponse Optional callback for responses
     */
    private function handleRequest(McpMessage $message, ?callable $onResponse = null): void
    {
        $method = $message->method;
        $params = $message->params ?? [];
        $id = $message->id;
        
        if (!isset($this->requestHandlers[$method])) {
            $error = [
                'code' => -32601,
                'message' => 'Method not found',
                'data' => ['method' => $method]
            ];
            $errorResponse = McpMessage::error($error, $id);
            
            if ($onResponse !== null) {
                $onResponse($errorResponse);
            } elseif ($this->transport !== null) {
                $this->transport->send($errorResponse->toJson());
            }
            return;
        }
        
        try {
            $result = ($this->requestHandlers[$method])($params);
            $response = McpMessage::response($result, $id);
            
            if ($onResponse !== null) {
                $onResponse($response);
            } elseif ($this->transport !== null) {
                $this->transport->send($response->toJson());
            }
        } catch (\Exception $e) {
            // Check if it's already an McpError
            if ($e instanceof McpError) {
                $error = [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                    'data' => $e->getData()
                ];
            } else {
                $error = [
                    'code' => -32000,
                    'message' => $e->getMessage(),
                    'data' => ['trace' => $e->getTraceAsString()]
                ];
            }
            $errorResponse = McpMessage::error($error, $id);
            
            if ($onResponse !== null) {
                $onResponse($errorResponse);
            } elseif ($this->transport !== null) {
                $this->transport->send($errorResponse->toJson());
            }
        }
    }
    
    /**
     * Handle a response message.
     *
     * @param McpMessage $message The response message
     */
    private function handleResponse(McpMessage $message): void
    {
        $id = $message->id;
        
        if (!isset($this->pendingRequests[$id])) {
            // No pending request with this ID
            return;
        }
        
        $deferred = $this->pendingRequests[$id];
        unset($this->pendingRequests[$id]);
        
        $deferred->resolve($message->result);
    }
    
    /**
     * Handle an error message.
     *
     * @param McpMessage $message The error message
     */
    private function handleError(McpMessage $message): void
    {
        $id = $message->id;
        
        if (!isset($this->pendingRequests[$id])) {
            // No pending request with this ID
            return;
        }
        
        $deferred = $this->pendingRequests[$id];
        unset($this->pendingRequests[$id]);
        
        $error = $message->error;
        $deferred->reject(new McpError($error['message'] ?? 'Unknown error', $error['code'] ?? 0, $error['data'] ?? null));
    }
    
    /**
     * Handle a notification message.
     *
     * @param McpMessage $message The notification message
     */
    private function handleNotification(McpMessage $message): void
    {
        $method = $message->method;
        $params = $message->params ?? [];
        
        if (!isset($this->notificationHandlers[$method])) {
            // No handler for this notification
            return;
        }
        
        try {
            ($this->notificationHandlers[$method])($params);
        } catch (\Exception $e) {
            // Log the error but don't crash
            error_log('Error handling notification: ' . $e->getMessage());
        }
    }
    
    /**
     * Start the protocol transport.
     */
    public function start(): void
    {
        if ($this->transport !== null) {
            $this->transport->start();
        }
    }
    
    /**
     * Stop the protocol transport.
     */
    public function stop(): void
    {
        if ($this->transport !== null) {
            $this->transport->stop();
        }
    }
    
    /**
     * Check if the protocol transport is running.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->transport !== null && $this->transport->isRunning();
    }
    
    /**
     * Alias for sendRequest to maintain compatibility with tests.
     *
     * @param string $method The method to call
     * @param array $params The parameters to pass
     * @return PromiseInterface A promise that resolves with the result or rejects with an error
     */
    public function request(string $method, array $params = []): PromiseInterface
    {
        return $this->sendRequest($method, $params);
    }

    /**
     * Process a message without sending it through transport.
     * This is primarily used for testing.
     *
     * @param McpMessage $message The message to process
     * @param callable|null $onResponse Optional callback for responses
     */
    public function processMessage(McpMessage $message, ?callable $onResponse = null): void
    {
        if ($message->isRequest()) {
            $this->handleRequest($message, $onResponse);
        } elseif ($message->isResponse()) {
            $this->handleResponse($message);
        } elseif ($message->isError()) {
            $this->handleError($message);
        } elseif ($message->isNotification()) {
            $this->handleNotification($message);
        }
    }

    /**
     * Get the last request ID that was sent.
     *
     * @return string|null The last request ID or null if no requests have been sent
     */
    public function getLastRequestId(): ?string
    {
        return $this->lastRequestId;
    }

    /**
     * Send a message directly (used for testing).
     *
     * @param McpMessage $message The message to send
     */
    public function send(McpMessage $message): void
    {
        if ($this->transport !== null) {
            $this->transport->send($message->toJson());
        }
    }
}
