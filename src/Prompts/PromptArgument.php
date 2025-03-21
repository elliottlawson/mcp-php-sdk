<?php

namespace ElliottLawson\McpPhpSdk\Prompts;

/**
 * Class representing a prompt argument.
 */
class PromptArgument
{
    /**
     * Create a new prompt argument.
     *
     * @param string $name The argument name
     * @param string $description A description of the argument
     * @param bool $required Whether the argument is required
     * @param string|null $type The argument type
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly bool $required = false,
        public readonly ?string $type = null
    ) {}
    
    /**
     * Convert the argument to an array for the payload.
     *
     * @return array
     */
    public function toArray(): array
    {
        $result = [
            'description' => $this->description,
            'required' => $this->required
        ];
        
        if ($this->type !== null) {
            $result['type'] = $this->type;
        }
        
        return $result;
    }
}
