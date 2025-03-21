<?php

namespace ElliottLawson\McpPhpSdk\Transport;

use ElliottLawson\McpPhpSdk\Contracts\TransportInterface;

/**
 * Transport implementation using Server-Sent Events (SSE).
 * 
 * This transport is useful for web applications and browser-based clients.
 */
class SseTransport implements TransportInterface
{
    /**
     * @var callable|null The message handler
     */
    private $messageHandler = null;
    
    /**
     * @var bool Whether the transport is running
     */
    private bool $running = false;
    
    /**
     * @var string The path to receive messages from
     */
    private string $messagePath;
    
    /**
     * Create a new SSE transport.
     *
     * @param string $messagePath The endpoint path to receive messages from
     */
    public function __construct(string $messagePath = '/mcp/message')
    {
        $this->messagePath = $messagePath;
    }
    
    /**
     * Send a message through SSE.
     *
     * @param string $message The message to send
     * @return void
     */
    public function send(string $message): void
    {
        echo "data: " . $message . "\n\n";
        flush();
        ob_flush();
    }
    
    /**
     * Set the handler for incoming messages.
     *
     * @param callable $handler The message handler function
     * @return self
     */
    public function setMessageHandler(callable $handler): TransportInterface
    {
        $this->messageHandler = $handler;
        return $this;
    }
    
    /**
     * Start listening for SSE events.
     *
     * @return void
     */
    public function start(): void
    {
        if ($this->running) {
            return;
        }
        
        $this->running = true;
        
        // Send SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        
        // Send initial connection message
        echo "event: connected\n";
        echo "data: " . json_encode(['connected' => true]) . "\n\n";
        flush();
        
        // This method doesn't actually wait for messages, since the HTTP request
        // for the SSE connection is separate from the HTTP requests that send messages.
        // Those are handled in a separate method that should be called from the
        // appropriate HTTP endpoint.
        
        // Keep the connection alive with a periodic heartbeat
        while ($this->running) {
            echo ": heartbeat\n\n";
            flush();
            
            // Sleep for 30 seconds - adjust as needed
            if (sleep(30) !== 0) {
                // Sleep was interrupted
                break;
            }
        }
    }
    
    /**
     * Stop the SSE connection.
     *
     * @return void
     */
    public function stop(): void
    {
        $this->running = false;
    }
    
    /**
     * Check if the transport is currently running.
     *
     * @return bool True if the transport is running, false otherwise
     */
    public function isRunning(): bool
    {
        return $this->running;
    }
    
    /**
     * Handle an incoming message from an HTTP request.
     * 
     * This method should be called from the HTTP endpoint that receives
     * messages from the client.
     *
     * @param string $message The raw message content
     * @return void
     */
    public function handleIncomingMessage(string $message): void
    {
        if ($this->messageHandler !== null) {
            ($this->messageHandler)($message);
        }
    }
}
