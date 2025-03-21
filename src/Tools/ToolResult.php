<?php

namespace ElliottLawson\McpPhpSdk\Tools;

/**
 * Class representing the result of a tool execution.
 */
class ToolResult
{
    /**
     * Create a new tool result.
     *
     * @param array $content The content of the result
     * @param bool $isError Whether this result represents an error
     * @param string|null $errorType The type of error (if isError is true)
     */
    public function __construct(
        public readonly array $content,
        public readonly bool $isError = false,
        public readonly ?string $errorType = null
    ) {}
    
    /**
     * Create a successful result.
     *
     * @param array|string $content The content of the result
     * @return self
     */
    public static function success(array|string $content): self
    {
        // If content is a string, wrap it in a text content block
        if (is_string($content)) {
            $content = [
                ['type' => 'text', 'text' => $content]
            ];
        }
        
        return new self(content: $content);
    }
    
    /**
     * Create an error result.
     *
     * @param string $message The error message
     * @param string|null $errorType The type of error
     * @return self
     */
    public static function error(string $message, ?string $errorType = null): self
    {
        return new self(
            content: [['type' => 'text', 'text' => $message]],
            isError: true,
            errorType: $errorType
        );
    }
    
    /**
     * Convert the tool result to a response payload.
     *
     * @return array
     */
    public function toArray(): array
    {
        $result = [
            'content' => $this->content
        ];
        
        if ($this->isError) {
            $result['isError'] = true;
            
            if ($this->errorType !== null) {
                $result['errorType'] = $this->errorType;
            }
        }
        
        return $result;
    }
}
