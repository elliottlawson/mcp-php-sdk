<?php

namespace ElliottLawson\McpPhpSdk\Prompts;

/**
 * Class representing the result of a prompt execution.
 */
class PromptResult
{
    /**
     * Create a new prompt result.
     *
     * @param array $messages The messages in the result
     * @param string|null $description An optional description of the result
     */
    public function __construct(
        public readonly array $messages,
        public readonly ?string $description = null
    ) {}
    
    /**
     * Create a prompt result from a single message.
     *
     * @param string $role The message role (e.g., 'system', 'user', 'assistant')
     * @param string $content The message content
     * @param string|null $description An optional description
     * @return self
     */
    public static function fromMessage(string $role, string $content, ?string $description = null): self
    {
        return new self(
            messages: [
                [
                    'role' => $role,
                    'content' => $content
                ]
            ],
            description: $description
        );
    }
    
    /**
     * Create a prompt result from multiple messages.
     *
     * @param array $messages Array of message arrays with 'role' and 'content' keys
     * @param string|null $description An optional description
     * @return self
     */
    public static function fromMessages(array $messages, ?string $description = null): self
    {
        return new self(
            messages: $messages,
            description: $description
        );
    }
    
    /**
     * Convert the prompt result to a response payload.
     *
     * @return array
     */
    public function toArray(): array
    {
        $result = [
            'messages' => $this->messages
        ];
        
        if ($this->description !== null) {
            $result['description'] = $this->description;
        }
        
        return $result;
    }
}
