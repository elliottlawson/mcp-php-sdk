<?php

namespace ElliottLawson\Tests\Unit\Resources;

use ElliottLawson\McpPhpSdk\Resources\Resource;
use ElliottLawson\McpPhpSdk\Resources\ResourceTemplate;
use PHPUnit\Framework\TestCase;

class ResourceTest extends TestCase
{
    public function testCanCreateResource(): void
    {
        $name = 'test-resource';
        $template = new ResourceTemplate('test://{param}');
        $handler = function () {
            return ['contents' => []];
        };
        
        $resource = new Resource($name, $template, $handler);
        
        $this->assertEquals($name, $resource->getName());
        $this->assertSame($template, $resource->getTemplate());
        $this->assertSame($handler, $resource->getHandler());
    }
    
    public function testCanCreateResourceWithStringTemplate(): void
    {
        $name = 'test-resource';
        $template = 'test://{param}';
        $handler = function () {
            return ['contents' => []];
        };
        
        $resource = new Resource($name, $template, $handler);
        
        $this->assertEquals($name, $resource->getName());
        $this->assertInstanceOf(ResourceTemplate::class, $resource->getTemplate());
        $this->assertEquals($template, $resource->getTemplate()->getPattern());
        $this->assertSame($handler, $resource->getHandler());
    }
    
    public function testCanCheckIfUriMatches(): void
    {
        $resource = new Resource(
            'test-resource',
            'test://{param}',
            function () {
                return ['contents' => []];
            }
        );
        
        $this->assertTrue($resource->matches('test://hello'));
        $this->assertFalse($resource->matches('other://hello'));
    }
    
    public function testCanHandleUriAndReturnContent(): void
    {
        $handlerCalled = false;
        
        $resource = new Resource(
            'test-resource',
            'test://{param}',
            function (string $uri, array $params) use (&$handlerCalled) {
                $handlerCalled = true;
                $this->assertEquals('test://hello', $uri);
                $this->assertEquals(['param' => 'hello'], $params);
                
                return [
                    'contents' => [
                        [
                            'uri' => $uri,
                            'text' => "Param value: {$params['param']}"
                        ]
                    ]
                ];
            }
        );
        
        $result = $resource->handle('test://hello');
        
        $this->assertTrue($handlerCalled);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('contents', $result);
        $this->assertCount(1, $result['contents']);
        $this->assertEquals('test://hello', $result['contents'][0]['uri']);
        $this->assertEquals('Param value: hello', $result['contents'][0]['text']);
    }
    
    public function testToArrayReturnsCorrectSchema(): void
    {
        $resource = new Resource(
            'test-resource',
            'test://{param}',
            function () {
                return ['contents' => []];
            }
        );
        
        $array = $resource->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals('test-resource', $array['name']);
        $this->assertEquals('test://{param}', $array['uriPattern']);
        $this->assertTrue(isset($array['description']) && is_string($array['description']));
    }
}
