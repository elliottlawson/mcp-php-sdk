<?php

namespace ElliottLawson\McpPhpSdk\Contracts;

/**
 * Interface for transport layers in MCP.
 * 
 * Transport layers handle the actual communication between clients and servers.
 */
interface TransportInterface
{
    /**
     * Send a raw message through the transport.
     *
     * @param string $message The serialized message to send
     * @return void
     */
    public function send(string $message): void;
    
    /**
     * Set a message handler callback for incoming messages.
     *
     * @param callable $handler The callback function that will handle incoming messages
     * @return self
     */
    public function setMessageHandler(callable $handler): self;
    
    /**
     * Start the transport to begin receiving messages.
     *
     * @return void
     */
    public function start(): void;
    
    /**
     * Stop the transport to end receiving messages.
     *
     * @return void
     */
    public function stop(): void;
    
    /**
     * Check if the transport is currently running.
     *
     * @return bool True if the transport is running, false otherwise
     */
    public function isRunning(): bool;
}
