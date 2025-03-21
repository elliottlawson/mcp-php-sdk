<?php

namespace ElliottLawson\McpPhpSdk\Prompts;

/**
 * Class representing a prompt in the MCP.
 * 
 * Prompts define reusable templates for LLM interactions.
 */
class Prompt
{
    /**
     * Create a new prompt.
     *
     * @param string $name The name of the prompt
     * @param string $description A description of the prompt
     * @param array $arguments The prompt arguments
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $arguments = []
    ) {}
    
    /**
     * Convert the prompt to its registration payload.
     *
     * @return array
     */
    public function toArray(): array
    {
        $args = [];
        
        foreach ($this->arguments as $arg) {
            if ($arg instanceof PromptArgument) {
                $args[$arg->name] = $arg->toArray();
            } else {
                $args[$arg['name']] = $arg;
            }
        }
        
        return [
            'name' => $this->name,
            'description' => $this->description,
            'arguments' => $args
        ];
    }
}
