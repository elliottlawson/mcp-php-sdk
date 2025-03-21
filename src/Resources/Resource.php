<?php

namespace ElliottLawson\McpPhpSdk\Resources;

/**
 * Class representing a resource in the MCP.
 * 
 * Resources provide standardized access to data for LLMs.
 */
class Resource
{
    /**
     * @var string The name of the resource
     */
    private string $name;
    
    /**
     * @var ResourceTemplate The URI template for this resource
     */
    private ResourceTemplate $template;
    
    /**
     * @var callable The handler function for this resource
     */
    private $handler;
    
    /**
     * @var string|null Optional description
     */
    private ?string $description;

    /**
     * Create a new resource.
     *
     * @param string $name The name of the resource
     * @param ResourceTemplate|string $template The URI template
     * @param callable $handler The handler function
     * @param string|null $description Optional description
     */
    public function __construct(
        string $name,
        $template,
        callable $handler,
        ?string $description = null
    ) {
        $this->name = $name;
        $this->template = is_string($template) ? new ResourceTemplate($template) : $template;
        $this->handler = $handler;
        $this->description = $description ?? "Resource '$name'";
    }
    
    /**
     * Get the name of the resource.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * Get the template for this resource.
     *
     * @return ResourceTemplate
     */
    public function getTemplate(): ResourceTemplate
    {
        return $this->template;
    }
    
    /**
     * Get the handler for this resource.
     *
     * @return callable
     */
    public function getHandler(): callable
    {
        return $this->handler;
    }
    
    /**
     * Check if a URI matches this resource.
     *
     * @param string $uri The URI to check
     * @return bool
     */
    public function matches(string $uri): bool
    {
        return $this->template->matches($uri);
    }
    
    /**
     * Handle a URI with this resource.
     *
     * @param string $uri The URI to handle
     * @return array The result of handling the URI
     */
    public function handle(string $uri): array
    {
        $params = $this->template->extractParams($uri);
        return ($this->handler)($uri, $params);
    }
    
    /**
     * Convert the resource to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'uriPattern' => $this->template->getPattern(),
            'description' => $this->description
        ];
    }
    
    /**
     * Create a resource from an array representation.
     *
     * @param array $data The resource data
     * @param callable|null $handler Optional handler to use
     * @return self
     */
    public static function fromArray(array $data, ?callable $handler = null): self
    {
        if ($handler === null) {
            $handler = function () {
                return ['contents' => []];
            };
        }
        
        return new self(
            $data['name'],
            $data['uriPattern'],
            $handler,
            $data['description'] ?? null
        );
    }
}
