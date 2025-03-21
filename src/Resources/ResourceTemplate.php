<?php

namespace ElliottLawson\McpPhpSdk\Resources;

/**
 * Class for handling URI templates and pattern matching for resources.
 */
class ResourceTemplate
{
    /**
     * Create a new resource template.
     *
     * @param string $uriTemplate The URI template pattern
     * @param array|null $listOptions Options for listing resources
     */
    public function __construct(
        public readonly string $uriTemplate,
        public readonly ?array $listOptions = ['enabled' => true]
    ) {}
    
    /**
     * Check if a URI matches this template and extract parameters.
     *
     * @param string $uri The URI to check
     * @return array|null An array of parameters if the URI matches, or null if it doesn't match
     */
    public function match(string $uri): ?array
    {
        // Convert the template to a regex pattern
        $pattern = $this->templateToRegex($this->uriTemplate);
        
        // Match against the URI
        if (preg_match($pattern, $uri, $matches)) {
            // Extract parameters
            $parameters = [];
            
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $parameters[$key] = $value;
                }
            }
            
            // Return the parameters
            return $parameters;
        }
        
        return null;
    }
    
    /**
     * Check if a URI matches this template.
     *
     * @param string $uri The URI to check
     * @return bool True if the URI matches, false otherwise
     */
    public function matches(string $uri): bool
    {
        return $this->match($uri) !== null;
    }
    
    /**
     * Extract parameters from a URI.
     *
     * @param string $uri The URI to extract parameters from
     * @return array The extracted parameters
     * @throws \InvalidArgumentException If the URI doesn't match this template
     */
    public function extractParams(string $uri): array
    {
        $params = $this->match($uri);
        
        if ($params === null) {
            throw new \InvalidArgumentException("URI '$uri' does not match template '{$this->uriTemplate}'");
        }
        
        return $params;
    }
    
    /**
     * Get the URI template pattern.
     *
     * @return string The URI template pattern
     */
    public function getPattern(): string
    {
        return $this->uriTemplate;
    }
    
    /**
     * Convert a URI template to a regex pattern.
     *
     * @param string $template The URI template
     * @return string The regex pattern
     */
    private function templateToRegex(string $template): string
    {
        // Handle special characters in the URI scheme properly
        $pattern = str_replace('://', '\\:\\/\\/', $template);
        
        // Replace {param} with a named capture group
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^\/]+)', $pattern);
        
        // Add start and end anchors
        return '/^' . $pattern . '$/';
    }
    
    /**
     * Convert the template to an array representation.
     *
     * @return array
     */
    public function toArray(): array
    {
        $result = [
            'uriTemplate' => $this->uriTemplate,
        ];
        
        if ($this->listOptions !== null) {
            $result['list'] = $this->listOptions;
        }
        
        return $result;
    }
}
