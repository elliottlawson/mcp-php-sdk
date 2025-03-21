<?php

namespace ElliottLawson\McpPhpSdk\Resources;

/**
 * Class representing the content of a resource.
 */
class ResourceContent
{
    /**
     * Create a new resource content instance.
     *
     * @param string $uri The URI of the resource
     * @param string $text The text content of the resource
     * @param string|null $mimeType The MIME type of the content (defaults to text/plain)
     * @param array|null $metadata Additional metadata for the content
     */
    public function __construct(
        public readonly string $uri,
        public readonly string $text,
        public readonly ?string $mimeType = null,
        public readonly ?array $metadata = null
    ) {}
    
    /**
     * Create a resource content instance from an array.
     *
     * @param array $data The resource content data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uri: $data['uri'],
            text: $data['text'],
            mimeType: $data['mimeType'] ?? null,
            metadata: $data['metadata'] ?? null
        );
    }
    
    /**
     * Convert the resource content to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $result = [
            'uri' => $this->uri,
            'text' => $this->text,
        ];
        
        if ($this->mimeType !== null) {
            $result['mimeType'] = $this->mimeType;
        }
        
        if ($this->metadata !== null) {
            $result['metadata'] = $this->metadata;
        }
        
        return $result;
    }
}
