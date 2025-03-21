<?php

namespace ElliottLawson\McpPhpSdk\Tools;

/**
 * Class representing a tool in the MCP.
 * 
 * Tools enable LLMs to execute code and produce side effects.
 */
class Tool
{
    /**
     * Create a new tool.
     *
     * @param string $name The name of the tool
     * @param string|array $schema The JSON schema for the tool's parameters
     * @param callable $handler The handler function for executing the tool
     * @param string|null $description A description of the tool
     */
    public function __construct(
        private string $name,
        private string|array $schema,
        private $handler,
        private ?string $description = null
    ) {
        $this->description = $description ?? "Tool '$name'";
    }
    
    /**
     * Get the name of the tool.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * Get the schema for this tool.
     *
     * @return array
     */
    public function getSchema(): array
    {
        return is_string($this->schema) ? json_decode($this->schema, true) : $this->schema;
    }
    
    /**
     * Get the handler for this tool.
     *
     * @return callable
     */
    public function getHandler(): callable
    {
        return $this->handler;
    }
    
    /**
     * Execute the tool with the given parameters.
     *
     * @param array $params The parameters for the tool
     * @return array The result of executing the tool
     */
    public function execute(array $params): array
    {
        return ($this->handler)($params);
    }
    
    /**
     * Convert the tool to its registration payload.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'schema' => is_string($this->schema) ? json_decode($this->schema, true) : $this->schema
        ];
    }
}
