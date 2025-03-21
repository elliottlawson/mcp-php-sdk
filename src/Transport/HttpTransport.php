<?php

namespace ElliottLawson\McpPhpSdk\Transport;

use ElliottLawson\McpPhpSdk\Contracts\TransportInterface;
use RuntimeException;

/**
 * Transport implementation using HTTP requests.
 */
class HttpTransport implements TransportInterface
{
    /**
     * @var callable|null The message handler function
     */
    private $messageHandler = null;
    
    /**
     * @var string The URL to send messages to
     */
    private string $url;
    
    /**
     * @var array Additional HTTP headers to include with requests
     */
    private array $headers;
    
    /**
     * @var bool Whether the transport is running
     */
    private bool $running = false;
    
    /**
     * Create a new HTTP transport.
     *
     * @param string $url The URL to send messages to
     * @param array $headers Additional HTTP headers to include with requests
     */
    public function __construct(string $url, array $headers = [])
    {
        $this->url = $url;
        $this->headers = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ], $headers);
    }
    
    /**
     * Send a message via HTTP.
     *
     * @param string $message The message to send
     * @return void
     * @throws RuntimeException If the HTTP request fails
     */
    public function send(string $message): void
    {
        $headers = [];
        foreach ($this->headers as $name => $value) {
            $headers[] = "$name: $value";
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("HTTP request failed with code $httpCode: $error");
        }
        
        // If there is a response and a message handler, process it
        if ($response && $this->messageHandler) {
            ($this->messageHandler)($response);
        }
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
     * Start the HTTP transport.
     *
     * For HTTP transport, this doesn't do much as it operates in a
     * request/response model rather than a persistent connection.
     *
     * @return void
     */
    public function start(): void
    {
        $this->running = true;
    }
    
    /**
     * Stop the HTTP transport.
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
}
