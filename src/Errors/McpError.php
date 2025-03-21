<?php

namespace ElliottLawson\McpPhpSdk\Errors;

/**
 * Custom exception class for MCP errors.
 */
class McpError extends \Exception
{
    // Standard JSON-RPC error codes
    public const PARSE_ERROR = -32700;
    public const INVALID_REQUEST = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS = -32602;
    public const INTERNAL_ERROR = -32603;
    
    // MCP-specific error codes
    public const RESOURCE_NOT_FOUND = -32000;
    public const TOOL_EXECUTION_ERROR = -32000;
    public const TOOL_NOT_FOUND = -32000;
    public const PROMPT_EXECUTION_ERROR = -32000;
    
    /**
     * Additional error data.
     *
     * @var array|null
     */
    protected ?array $data;
    
    /**
     * Create a new MCP error.
     *
     * @param string $message The error message
     * @param int $code The error code
     * @param array|null $data Additional error data
     */
    public function __construct(string $message, int $code, ?array $data = null)
    {
        parent::__construct($message, $code);
        $this->data = $data;
    }
    
    /**
     * Get the additional error data.
     *
     * @return array|null The error data
     */
    public function getData(): ?array
    {
        return $this->data;
    }
    
    /**
     * Convert to a JSON-RPC error response.
     *
     * @param string $id The request ID
     * @return array The error response array
     */
    public function toResponseArray(string $id): array
    {
        $error = [
            'code' => $this->getCode(),
            'message' => $this->getMessage()
        ];
        
        if ($this->data !== null) {
            $error['data'] = $this->data;
        }
        
        return [
            'jsonrpc' => '2.0',
            'error' => $error,
            'id' => $id
        ];
    }
}
