<?php

namespace ElliottLawson\McpPhpSdk\Server;

/**
 * Class representing the capabilities of an MCP server.
 */
class ServerCapabilities
{
    /**
     * Whether resources are supported.
     *
     * @var bool
     */
    public bool $resources = false;
    
    /**
     * Whether list changes notifications for resources are supported.
     *
     * @var bool
     */
    public bool $resourceListChanges = false;
    
    /**
     * Whether tools are supported.
     *
     * @var bool
     */
    public bool $tools = false;
    
    /**
     * Whether prompts are supported.
     *
     * @var bool
     */
    public bool $prompts = false;
    
    /**
     * Whether logging is supported.
     *
     * @var bool
     */
    public bool $logging = true;
    
    /**
     * The logging level.
     *
     * @var string|null
     */
    public ?string $loggingLevel = 'info';
    
    /**
     * Create a new instance with default capabilities.
     *
     * @return self
     */
    public static function create(): self
    {
        return new self();
    }
    
    /**
     * Set resource capabilities.
     *
     * @param bool $enabled Whether resources are enabled
     * @param bool $listChanges Whether list changes are enabled
     * @return self
     */
    public function withResources(bool $enabled = true, bool $listChanges = false): self
    {
        $this->resources = $enabled;
        $this->resourceListChanges = $listChanges;
        return $this;
    }
    
    /**
     * Set tool capabilities.
     *
     * @param bool $enabled Whether tools are enabled
     * @return self
     */
    public function withTools(bool $enabled = true): self
    {
        $this->tools = $enabled;
        return $this;
    }
    
    /**
     * Set prompt capabilities.
     *
     * @param bool $enabled Whether prompts are enabled
     * @return self
     */
    public function withPrompts(bool $enabled = true): self
    {
        $this->prompts = $enabled;
        return $this;
    }
    
    /**
     * Check if resources are enabled.
     *
     * @return bool
     */
    public function hasResources(): bool
    {
        return $this->resources;
    }
    
    /**
     * Check if tools are enabled.
     *
     * @return bool
     */
    public function hasTools(): bool
    {
        return $this->tools;
    }
    
    /**
     * Check if prompts are enabled.
     *
     * @return bool
     */
    public function hasPrompts(): bool
    {
        return $this->prompts;
    }
    
    /**
     * Set logging capabilities.
     *
     * @param string $level The logging level
     * @return self
     */
    public function withLogging(string $level = 'info'): self
    {
        $this->logging = true;
        $this->loggingLevel = $level;
        return $this;
    }
    
    /**
     * Convert capabilities to an array for initialization message.
     *
     * @return array
     */
    public function toArray(): array
    {
        $capabilities = [
            'resources' => $this->resources,
            'tools' => $this->tools,
            'prompts' => $this->prompts
        ];
        
        if ($this->resources) {
            $capabilities['resource_list_changes'] = $this->resourceListChanges;
        }
        
        if ($this->logging) {
            $capabilities['logging'] = [
                'level' => $this->loggingLevel
            ];
        }
        
        return $capabilities;
    }
    
    /**
     * Create capabilities from an array.
     *
     * @param array $data The capabilities data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $capabilities = new self();
        
        if (isset($data['resources'])) {
            $capabilities->withResources($data['resources'], $data['resource_list_changes'] ?? false);
        }
        
        if (isset($data['tools'])) {
            $capabilities->withTools($data['tools']);
        }
        
        if (isset($data['prompts'])) {
            $capabilities->withPrompts($data['prompts']);
        }
        
        if (isset($data['logging'])) {
            $level = is_array($data['logging']) && isset($data['logging']['level']) 
                ? $data['logging']['level'] 
                : 'info';
            $capabilities->withLogging($level);
        }
        
        return $capabilities;
    }
}
