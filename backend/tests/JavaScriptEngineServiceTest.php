<?php

namespace Prism\Backend\Tests;

use PHPUnit\Framework\TestCase;
use Prism\Backend\Services\JavaScriptEngineService;
use Monolog\Logger;

class JavaScriptEngineServiceTest extends TestCase
{
    private JavaScriptEngineService $jsEngine;
    private Logger $logger;

    protected function setUp(): void
    {
        $config = [
            'enabled' => true,
            'debug' => false,
            'v8js' => [
                'enabled' => true,
                'memory_limit' => '256M',
                'timeout' => 30
            ],
            'features' => [
                'console_api' => true,
                'dom_api' => true,
                'window_api' => true,
                'event_api' => true,
                'timer_api' => true,
                'xhr_api' => true,
                'storage_api' => true,
                'location_api' => true,
                'history_api' => true,
                'navigator_api' => true,
                'fetch_api' => true,
                'promise_api' => true
            ],
            'security' => [
                'sandbox' => true,
                'allow_eval' => false,
                'allow_function_constructor' => false,
                'max_execution_time' => 30,
                'memory_limit' => '256M'
            ]
        ];
        
        $this->logger = new Logger('test');
        $this->jsEngine = new JavaScriptEngineService($config, $this->logger);
    }

    public function testInitialize()
    {
        $result = $this->jsEngine->initialize();
        
        if (extension_loaded('v8js')) {
            $this->assertTrue($result);
            $this->assertTrue($this->jsEngine->isInitialized());
        } else {
            $this->assertFalse($result);
            $this->assertFalse($this->jsEngine->isInitialized());
        }
    }

    public function testExecuteBasicJavaScript()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'var x = 5; var y = 10; x + y;';
        $result = $this->jsEngine->execute($code);
        
        $this->assertEquals(15, $result);
    }

    public function testExecuteWithVariables()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'x + y;';
        $variables = ['x' => 5, 'y' => 10];
        $result = $this->jsEngine->execute($code, $variables);
        
        $this->assertEquals(15, $result);
    }

    public function testExecuteWithGlobalObjects()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'Math.PI;';
        $result = $this->jsEngine->execute($code);
        
        $this->assertEquals(M_PI, $result);
    }

    public function testConsoleAPI()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'console.log("Hello, World!"); "logged";';
        $result = $this->jsEngine->execute($code);
        
        $this->assertEquals('logged', $result);
    }

    public function testMathObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'Math.sqrt(16);';
        $result = $this->jsEngine->execute($code);
        
        $this->assertEquals(4, $result);
    }

    public function testJSONObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'JSON.stringify({name: "test", value: 123});';
        $result = $this->jsEngine->execute($code);
        
        $this->assertStringContains('"name":"test"', $result);
        $this->assertStringContains('"value":123', $result);
    }

    public function testDateObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'new Date().getFullYear();';
        $result = $this->jsEngine->execute($code);
        
        $this->assertIsInt($result);
        $this->assertGreaterThan(2020, $result);
    }

    public function testArrayObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'Array.isArray([1, 2, 3]);';
        $result = $this->jsEngine->execute($code);
        
        $this->assertTrue($result);
    }

    public function testStringObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'String.fromCharCode(65, 66, 67);';
        $result = $this->jsEngine->execute($code);
        
        $this->assertEquals('ABC', $result);
    }

    public function testNumberObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'Number.isInteger(42);';
        $result = $this->jsEngine->execute($code);
        
        $this->assertTrue($result);
    }

    public function testBooleanObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'Boolean(1);';
        $result = $this->jsEngine->execute($code);
        
        $this->assertTrue($result);
    }

    public function testFunctionObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'function add(a, b) { return a + b; } add(5, 3);';
        $result = $this->jsEngine->execute($code);
        
        $this->assertEquals(8, $result);
    }

    public function testRegExpObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = '/test/.test("testing");';
        $result = $this->jsEngine->execute($code);
        
        $this->assertTrue($result);
    }

    public function testErrorObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'try { throw new Error("Test error"); } catch(e) { e.message; }';
        $result = $this->jsEngine->execute($code);
        
        $this->assertEquals('Test error', $result);
    }

    public function testPromiseObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'Promise.resolve(42);';
        $result = $this->jsEngine->execute($code);
        
        $this->assertIsObject($result);
    }

    public function testSymbolObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'Symbol("test");';
        $result = $this->jsEngine->execute($code);
        
        $this->assertIsString($result);
    }

    public function testMapObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'new Map().set("key", "value").get("key");';
        $result = $this->jsEngine->execute($code);
        
        $this->assertEquals('value', $result);
    }

    public function testSetObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'new Set([1, 2, 3]).has(2);';
        $result = $this->jsEngine->execute($code);
        
        $this->assertTrue($result);
    }

    public function testWeakMapObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'new WeakMap();';
        $result = $this->jsEngine->execute($code);
        
        $this->assertIsObject($result);
    }

    public function testWeakSetObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'new WeakSet();';
        $result = $this->jsEngine->execute($code);
        
        $this->assertIsObject($result);
    }

    public function testProxyObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'new Proxy({}, {});';
        $result = $this->jsEngine->execute($code);
        
        $this->assertIsObject($result);
    }

    public function testReflectObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'Reflect.has({}, "key");';
        $result = $this->jsEngine->execute($code);
        
        $this->assertFalse($result);
    }

    public function testIntlObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'Intl.Collator;';
        $result = $this->jsEngine->execute($code);
        
        $this->assertIsObject($result);
    }

    public function testWebAssemblyObject()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'WebAssembly.validate;';
        $result = $this->jsEngine->execute($code);
        
        $this->assertIsCallable($result);
    }

    public function testCreateContext()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $contextId = $this->jsEngine->createContext('test-context');
        
        $this->assertIsString($contextId);
        $this->assertEquals('test-context', $contextId);
    }

    public function testSetContextVariable()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $contextId = $this->jsEngine->createContext();
        $this->jsEngine->setContextVariable($contextId, 'testVar', 'testValue');
        
        $value = $this->jsEngine->getContextVariable($contextId, 'testVar');
        $this->assertEquals('testValue', $value);
    }

    public function testExecuteInContext()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $contextId = $this->jsEngine->createContext();
        $this->jsEngine->setContextVariable($contextId, 'x', 5);
        
        $code = 'x * 2;';
        $result = $this->jsEngine->executeInContext($contextId, $code);
        
        $this->assertEquals(10, $result);
    }

    public function testAddEventListener()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $listener = function($data) {
            // Test listener
        };
        
        $this->jsEngine->addEventListener('test-event', $listener);
        
        $listeners = $this->jsEngine->getEventListeners();
        $this->assertArrayHasKey('test-event', $listeners);
        $this->assertCount(1, $listeners['test-event']);
    }

    public function testRemoveEventListener()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $listener = function($data) {
            // Test listener
        };
        
        $this->jsEngine->addEventListener('test-event', $listener);
        $this->jsEngine->removeEventListener('test-event', $listener);
        
        $listeners = $this->jsEngine->getEventListeners();
        $this->assertArrayHasKey('test-event', $listeners);
        $this->assertCount(0, $listeners['test-event']);
    }

    public function testDispatchEvent()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $called = false;
        $listener = function($data) use (&$called) {
            $called = true;
        };
        
        $this->jsEngine->addEventListener('test-event', $listener);
        $this->jsEngine->dispatchEvent('test-event', ['test' => 'data']);
        
        $this->assertTrue($called);
    }

    public function testGetTimers()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $timers = $this->jsEngine->getTimers();
        $this->assertIsArray($timers);
    }

    public function testClearTimer()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $timerId = 'test-timer';
        $this->jsEngine->clearTimer($timerId);
        
        // Should not throw exception
        $this->assertTrue(true);
    }

    public function testClearAllTimers()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $this->jsEngine->clearAllTimers();
        
        $timers = $this->jsEngine->getTimers();
        $this->assertEmpty($timers);
    }

    public function testGetContexts()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $contexts = $this->jsEngine->getContexts();
        $this->assertIsArray($contexts);
    }

    public function testGetMemoryUsage()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $memory = $this->jsEngine->getMemoryUsage();
        $this->assertIsArray($memory);
        $this->assertArrayHasKey('used', $memory);
        $this->assertArrayHasKey('peak', $memory);
        $this->assertArrayHasKey('limit', $memory);
    }

    public function testClose()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        $this->jsEngine->close();
        
        $this->assertFalse($this->jsEngine->isInitialized());
    }

    public function testErrorHandling()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $code = 'throw new Error("Test error");';
        
        $this->expectException(\RuntimeException::class);
        $this->jsEngine->execute($code);
    }

    public function testExecuteFile()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_js_');
        file_put_contents($tempFile, 'var x = 42; x;');
        
        try {
            $result = $this->jsEngine->executeFile($tempFile);
            $this->assertEquals(42, $result);
        } finally {
            unlink($tempFile);
        }
    }

    public function testExecuteFileNotFound()
    {
        if (!extension_loaded('v8js')) {
            $this->markTestSkipped('V8Js extension not loaded');
        }

        $this->jsEngine->initialize();
        
        $this->expectException(\RuntimeException::class);
        $this->jsEngine->executeFile('/nonexistent/file.js');
    }
}
