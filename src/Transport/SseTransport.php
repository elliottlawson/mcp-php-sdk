<?php

namespace ElliottLawson\McpPhpSdk\Transport;

use ElliottLawson\McpPhpSdk\Contracts\TransportInterface;

/**
 * Server-Sent Events (SSE) transport implementation.
 * 
 * This class provides a framework-agnostic implementation of SSE for the MCP protocol.
 * It uses callbacks for output, headers, and flush operations to make it compatible
 * with various PHP frameworks and environments.
 */
class SseTransport implements TransportInterface
{
    /**
     * @var bool Whether the transport is currently running
     */
    protected bool $running = false;

    /**
     * @var callable|null The message handler
     */
    protected $messageHandler = null;

    /**
     * @var string The path for receiving messages
     */
    protected string $messagePath;

    /**
     * @var int The heartbeat interval in seconds
     */
    protected int $heartbeatInterval;

    /**
     * @var string|null ID used to store/retrieve messages
     */
    protected ?string $messageStoreId = null;

    /**
     * @var callable Callback for output operations
     */
    protected $outputCallback;

    /**
     * @var callable Callback for header operations
     */
    protected $headerCallback;

    /**
     * @var callable Callback for flush operations
     */
    protected $flushCallback;

    /**
     * Create a new SSE transport.
     *
     * @param string $messagePath The path to receive messages from
     * @param int $heartbeatInterval Seconds between heartbeat messages
     */
    public function __construct(string $messagePath = '/mcp/message', int $heartbeatInterval = 30)
    {
        $this->messagePath = $messagePath;
        $this->heartbeatInterval = $heartbeatInterval;

        // Set default callbacks
        $this->outputCallback = function (string $data) {
            echo $data;
        };

        $this->headerCallback = function (string $name, string $value) {
            header("$name: $value");
        };

        $this->flushCallback = function () {
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        };
    }

    /**
     * Set the callback for output operations.
     *
     * @param callable $callback
     * @return self
     */
    public function setOutputCallback(callable $callback): self
    {
        $this->outputCallback = $callback;
        return $this;
    }

    /**
     * Set the callback for header operations.
     *
     * @param callable $callback
     * @return self
     */
    public function setHeaderCallback(callable $callback): self
    {
        $this->headerCallback = $callback;
        return $this;
    }

    /**
     * Set the callback for flush operations.
     *
     * @param callable $callback
     * @return self
     */
    public function setFlushCallback(callable $callback): self
    {
        $this->flushCallback = $callback;
        return $this;
    }

    /**
     * Set the message store ID.
     *
     * @param string $id
     * @return self
     */
    public function setMessageStoreId(string $id): self
    {
        $this->messageStoreId = $id;
        return $this;
    }

    /**
     * Get the message store ID.
     *
     * @return string|null
     */
    public function getMessageStoreId(): ?string
    {
        return $this->messageStoreId;
    }

    /**
     * Start the transport.
     *
     * @return void
     */
    public function start(): void
    {
        if (!$this->running) {
            $this->running = true;
            $this->initializeSse();
        }
    }

    /**
     * Stop the transport.
     *
     * @return void
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Check if the transport is running.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Initialize the SSE connection.
     *
     * @return void
     */
    protected function initializeSse(): void
    {
        // Set appropriate headers for SSE
        ($this->headerCallback)('Content-Type', 'text/event-stream');
        ($this->headerCallback)('Cache-Control', 'no-cache');
        ($this->headerCallback)('Connection', 'keep-alive');
        ($this->headerCallback)('X-Accel-Buffering', 'no');

        // Send initial comment to establish connection
        $this->sendComment('SSE connection established');
    }

    /**
     * Send a message through SSE.
     *
     * @param string $message The message to send
     * @return void
     */
    public function send(string $message): void
    {
        if (!$this->running) {
            return;
        }

        // Format the message for SSE
        $formatted = 'data: ' . str_replace("\n", "\ndata: ", $message) . "\n\n";
        
        // Output the formatted message
        ($this->outputCallback)($formatted);
        
        // Flush the output buffer
        ($this->flushCallback)();
    }

    /**
     * Send an event with optional id and event type.
     *
     * @param string $data The event data
     * @param string|null $id Optional event ID
     * @param string|null $event Optional event type
     * @return void
     */
    public function sendEvent(string $data, ?string $id = null, ?string $event = null): void
    {
        if (!$this->running) {
            return;
        }

        $output = '';
        
        // Add event ID if provided
        if ($id !== null) {
            $output .= "id: $id\n";
        }
        
        // Add event type if provided
        if ($event !== null) {
            $output .= "event: $event\n";
        }
        
        // Add the data, handling multi-line data
        $output .= 'data: ' . str_replace("\n", "\ndata: ", $data) . "\n\n";
        
        // Output the formatted event
        ($this->outputCallback)($output);
        
        // Flush the output buffer
        ($this->flushCallback)();
    }

    /**
     * Send a comment through SSE.
     *
     * @param string $comment The comment to send
     * @return void
     */
    public function sendComment(string $comment): void
    {
        if (!$this->running) {
            return;
        }

        $formatted = ': ' . str_replace("\n", "\n: ", $comment) . "\n\n";
        
        // Output the formatted comment
        ($this->outputCallback)($formatted);
        
        // Flush the output buffer
        ($this->flushCallback)();
    }

    /**
     * Send a heartbeat message.
     *
     * @return void
     */
    public function sendHeartbeat(): void
    {
        $this->sendComment('heartbeat');
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
     * Handle an incoming message.
     *
     * @param string $message The message to handle
     * @return void
     */
    public function handleMessage(string $message): void
    {
        if ($this->messageHandler) {
            ($this->messageHandler)($message);
        }
    }
}
