<?php

namespace ElliottLawson\McpPhpSdk\Transport;

use ElliottLawson\McpPhpSdk\Contracts\TransportInterface;

/**
 * Transport implementation using standard input/output.
 * 
 * This transport is useful for CLI applications and direct process communication.
 */
class StdioTransport implements TransportInterface
{
    /**
     * @var resource The STDIN stream
     */
    private $stdin;
    
    /**
     * @var resource The STDOUT stream
     */
    private $stdout;
    
    /**
     * @var callable|null The message handler
     */
    private $messageHandler = null;
    
    /**
     * @var bool Whether the transport is running
     */
    private bool $running = false;
    
    /**
     * Create a new STDIO transport.
     */
    public function __construct()
    {
        // Open STDIN in non-blocking mode
        $this->stdin = fopen('php://stdin', 'r');
        stream_set_blocking($this->stdin, false);
        
        // Open STDOUT
        $this->stdout = fopen('php://stdout', 'w');
    }
    
    /**
     * Clean up resources when the object is destroyed.
     */
    public function __destruct()
    {
        $this->stop();
        
        if (is_resource($this->stdin)) {
            fclose($this->stdin);
        }
        
        if (is_resource($this->stdout)) {
            fclose($this->stdout);
        }
    }
    
    /**
     * Send a message through STDOUT.
     *
     * @param string $message The message to send
     * @return void
     */
    public function send(string $message): void
    {
        fwrite($this->stdout, $message . PHP_EOL);
        fflush($this->stdout);
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
     * Start listening for messages on STDIN.
     *
     * @return void
     */
    public function start(): void
    {
        if ($this->running) {
            return;
        }
        
        $this->running = true;
        
        // Buffer for incomplete lines
        $buffer = '';
        
        // Main loop
        while ($this->running) {
            // Read from STDIN
            $input = fgets($this->stdin);
            
            if ($input !== false) {
                $buffer .= $input;
                
                // Process complete lines
                while (($pos = strpos($buffer, PHP_EOL)) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + strlen(PHP_EOL));
                    
                    if ($this->messageHandler !== null) {
                        ($this->messageHandler)($line);
                    }
                }
            }
            
            // Small delay to prevent CPU hogging
            usleep(10000); // 10ms
        }
    }
    
    /**
     * Stop listening for messages.
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
}
