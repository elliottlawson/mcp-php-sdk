<?php

namespace ElliottLawson\McpPhpSdk\Messages;

use stdClass;

/**
 * Class representing a JSON-RPC 2.0 message used in MCP.
 * 
 * This class provides methods for creating different types of MCP messages
 * (requests, responses, errors, and notifications) as well as serializing and
 * deserializing them to/from JSON.
 */
class McpMessage
{
    /**
     * @var string The JSON-RPC version (always 2.0)
     */
    public readonly string $jsonrpc;
    
    // Dynamic properties for request/response/error/notification messages
    public readonly ?string $method;
    public readonly ?array $params;
    public readonly ?string $id;
    public readonly mixed $result;
    public readonly ?array $error;
    
    /**
     * Create a new MCP message.
     *
     * @param array $data The message data
     */
    private function __construct(array $data)
    {
        // Initialize required properties
        $data['jsonrpc'] = $data['jsonrpc'] ?? '2.0';
        
        // Dynamically set the properties through reflection
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        
        foreach ($properties as $property) {
            $name = $property->getName();
            if (isset($data[$name])) {
                $property->setValue($this, $data[$name]);
            }
        }
    }
    
    /**
     * Create a request message.
     *
     * @param string $method The method to call
     * @param array $params The parameters to pass to the method
     * @param string $id The request ID
     * @return self
     */
    public static function request(string $method, array $params, string $id): self
    {
        return new self([
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => $id
        ]);
    }
    
    /**
     * Create a response message.
     *
     * @param mixed $result The result of the request
     * @param string $id The request ID
     * @return self
     */
    public static function response($result, string $id): self
    {
        return new self([
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id
        ]);
    }
    
    /**
     * Create an error response message.
     *
     * @param array $error The error details
     * @param string $id The request ID
     * @return self
     */
    public static function error(array $error, string $id): self
    {
        return new self([
            'jsonrpc' => '2.0',
            'error' => $error,
            'id' => $id
        ]);
    }
    
    /**
     * Create a notification message.
     *
     * @param string $method The method to call
     * @param array $params The parameters to pass to the method
     * @return self
     */
    public static function notification(string $method, array $params): self
    {
        return new self([
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params
        ]);
    }
    
    /**
     * Create a message from a JSON string.
     *
     * @param string $json The JSON string
     * @return self
     * @throws \InvalidArgumentException If the JSON is invalid
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        
        return new self($data);
    }
    
    /**
     * Convert the message to a JSON string.
     *
     * @return string
     */
    public function toJson(): string
    {
        $data = get_object_vars($this);
        
        // Remove null properties to keep the JSON clean
        foreach ($data as $key => $value) {
            if ($value === null) {
                unset($data[$key]);
            }
        }
        
        return json_encode($data);
    }
    
    /**
     * Check if the message is a request.
     *
     * @return bool
     */
    public function isRequest(): bool
    {
        return isset($this->method) && isset($this->id);
    }
    
    /**
     * Check if the message is a response.
     *
     * @return bool
     */
    public function isResponse(): bool
    {
        return isset($this->result) && isset($this->id) && !isset($this->method);
    }
    
    /**
     * Check if the message is an error.
     *
     * @return bool
     */
    public function isError(): bool
    {
        return isset($this->error) && isset($this->id);
    }
    
    /**
     * Check if the message is a notification.
     *
     * @return bool
     */
    public function isNotification(): bool
    {
        return isset($this->method) && !isset($this->id);
    }
}
