<?php

namespace ElliottLawson\Tests\Unit\Tools;

use ElliottLawson\McpPhpSdk\Tools\Tool;
use PHPUnit\Framework\TestCase;

class ToolTest extends TestCase
{
    public function testCanCreateToolWithArraySchema(): void
    {
        $name = 'test-tool';
        $schema = [
            'type' => 'object',
            'properties' => [
                'param' => ['type' => 'string']
            ]
        ];
        $handler = function () {
            return ['content' => []];
        };
        
        $tool = new Tool($name, $schema, $handler);
        
        $this->assertEquals($name, $tool->getName());
        $this->assertEquals($schema, $tool->getSchema());
        $this->assertSame($handler, $tool->getHandler());
    }
    
    public function testCanCreateToolWithStringSchema(): void
    {
        $name = 'test-tool';
        $schema = json_encode([
            'type' => 'object',
            'properties' => [
                'param' => ['type' => 'string']
            ]
        ]);
        $handler = function () {
            return ['content' => []];
        };
        
        $tool = new Tool($name, $schema, $handler);
        
        $this->assertEquals($name, $tool->getName());
        $this->assertIsArray($tool->getSchema());
        $this->assertSame($handler, $tool->getHandler());
    }
    
    public function testCanExecuteTool(): void
    {
        $handlerCalled = false;
        
        $tool = new Tool(
            'test-tool',
            [
                'type' => 'object',
                'properties' => [
                    'param' => ['type' => 'string']
                ]
            ],
            function (array $params) use (&$handlerCalled) {
                $handlerCalled = true;
                $this->assertEquals(['param' => 'test-value'], $params);
                
                return [
                    'content' => [
                        ['type' => 'text', 'text' => "Param value: {$params['param']}"]
                    ]
                ];
            }
        );
        
        $result = $tool->execute(['param' => 'test-value']);
        
        $this->assertTrue($handlerCalled);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertCount(1, $result['content']);
        $this->assertEquals('text', $result['content'][0]['type']);
        $this->assertEquals('Param value: test-value', $result['content'][0]['text']);
    }
    
    public function testToArrayReturnsCorrectSchema(): void
    {
        $tool = new Tool(
            'test-tool',
            [
                'type' => 'object',
                'properties' => [
                    'param' => ['type' => 'string']
                ]
            ],
            function () {
                return ['content' => []];
            }
        );
        
        $array = $tool->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals('test-tool', $array['name']);
        $this->assertIsArray($array['schema']);
        $this->assertEquals('object', $array['schema']['type']);
        $this->assertTrue(isset($array['description']) && is_string($array['description']));
    }
}
