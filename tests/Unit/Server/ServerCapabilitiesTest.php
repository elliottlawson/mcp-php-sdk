<?php

namespace ElliottLawson\Tests\Unit\Server;

use ElliottLawson\McpPhpSdk\Server\ServerCapabilities;
use PHPUnit\Framework\TestCase;

class ServerCapabilitiesTest extends TestCase
{
    public function testCanCreateDefaultCapabilities(): void
    {
        $capabilities = ServerCapabilities::create();
        
        $array = $capabilities->toArray();
        
        $this->assertIsArray($array);
        $this->assertArrayHasKey('resources', $array);
        $this->assertArrayHasKey('tools', $array);
        $this->assertArrayHasKey('prompts', $array);
        
        // Default values should all be false
        $this->assertFalse($array['resources']);
        $this->assertFalse($array['tools']);
        $this->assertFalse($array['prompts']);
    }
    
    public function testCanEnableCapabilities(): void
    {
        $capabilities = ServerCapabilities::create()
            ->withResources(true)
            ->withTools(true)
            ->withPrompts(true);
        
        $array = $capabilities->toArray();
        
        $this->assertTrue($array['resources']);
        $this->assertTrue($array['tools']);
        $this->assertTrue($array['prompts']);
    }
    
    public function testCanCheckCapabilities(): void
    {
        $capabilities = ServerCapabilities::create()
            ->withResources(true)
            ->withTools(false)
            ->withPrompts(true);
        
        $this->assertTrue($capabilities->hasResources());
        $this->assertFalse($capabilities->hasTools());
        $this->assertTrue($capabilities->hasPrompts());
    }
    
    public function testCanChangeCapabilitiesWithFluentInterface(): void
    {
        $capabilities = ServerCapabilities::create();
        
        // Initially all false
        $this->assertFalse($capabilities->hasResources());
        $this->assertFalse($capabilities->hasTools());
        $this->assertFalse($capabilities->hasPrompts());
        
        // Enable resources
        $capabilities = $capabilities->withResources(true);
        $this->assertTrue($capabilities->hasResources());
        
        // Enable tools
        $capabilities = $capabilities->withTools(true);
        $this->assertTrue($capabilities->hasTools());
        
        // Disable resources
        $capabilities = $capabilities->withResources(false);
        $this->assertFalse($capabilities->hasResources());
    }
    
    public function testCanCreateFromArray(): void
    {
        $array = [
            'resources' => true,
            'tools' => false,
            'prompts' => true
        ];
        
        $capabilities = ServerCapabilities::fromArray($array);
        
        $this->assertTrue($capabilities->hasResources());
        $this->assertFalse($capabilities->hasTools());
        $this->assertTrue($capabilities->hasPrompts());
    }
}
