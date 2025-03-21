<?php
/**
 * HTTP/SSE MCP Server Example
 * 
 * This example creates an MCP server that uses Server-Sent Events (SSE)
 * for communication with web clients.
 * 
 * To run this server:
 * php -S localhost:8080 examples/http-server.php
 */

require __DIR__ . '/../vendor/autoload.php';

use ElliottLawson\McpPhpSdk\Server\McpServer;
use ElliottLawson\McpPhpSdk\Server\ServerCapabilities;
use ElliottLawson\McpPhpSdk\Transport\SseTransport;

// Global variables to store the server and transport
$mcpServer = null;
$sseTransport = null;

/**
 * Initialize the MCP server.
 */
function initializeMcpServer() {
    global $mcpServer, $sseTransport;
    
    // Create server capabilities
    $capabilities = ServerCapabilities::create()
        ->withResources(true)
        ->withTools(true);
    
    // Create the MCP server
    $mcpServer = new McpServer([
        'name' => 'HTTP MCP Server',
        'version' => '1.0.0',
        'capabilities' => $capabilities
    ]);
    
    // Register a simple echo resource
    $mcpServer->resource(
        'echo',
        'echo://{message}',
        function (string $uri, array $params) {
            return [
                'contents' => [
                    [
                        'uri' => $uri,
                        'text' => "You said: {$params['message']}"
                    ]
                ]
            ];
        }
    );
    
    // Register a calculator tool
    $mcpServer->tool(
        'calculator',
        [
            'type' => 'object',
            'properties' => [
                'operation' => ['type' => 'string', 'enum' => ['add', 'subtract', 'multiply', 'divide']],
                'a' => ['type' => 'number'],
                'b' => ['type' => 'number']
            ],
            'required' => ['operation', 'a', 'b']
        ],
        function (array $params) {
            $a = $params['a'];
            $b = $params['b'];
            
            switch ($params['operation']) {
                case 'add':
                    $result = $a + $b;
                    break;
                case 'subtract':
                    $result = $a - $b;
                    break;
                case 'multiply':
                    $result = $a * $b;
                    break;
                case 'divide':
                    if ($b === 0) {
                        return [
                            'content' => [['type' => 'text', 'text' => 'Cannot divide by zero']],
                            'isError' => true
                        ];
                    }
                    $result = $a / $b;
                    break;
                default:
                    return [
                        'content' => [['type' => 'text', 'text' => 'Unknown operation']],
                        'isError' => true
                    ];
            }
            
            return [
                'content' => [
                    ['type' => 'text', 'text' => "Result: $result"]
                ]
            ];
        }
    );
    
    // Create SSE transport
    $sseTransport = new SseTransport('/mcp/message');
}

// Initialize the server on first request
initializeMcpServer();

// Handle different endpoints
$uri = $_SERVER['REQUEST_URI'];

// Handle SSE connection
if ($uri === '/mcp/sse') {
    // Connect the server to the SSE transport
    $mcpServer->connect($sseTransport);
    
    // This will start the SSE connection and send headers
    $sseTransport->start();
    exit;
}

// Handle incoming messages
if ($uri === '/mcp/message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    
    // Process the incoming message
    $sseTransport->handleIncomingMessage($rawInput);
    
    // Return a simple response
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// Serve a simple HTML page with a client example
echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCP Client Example</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; line-height: 1.6; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow: auto; }
        button { padding: 8px 16px; background: #4CAF50; border: none; color: white; cursor: pointer; margin-right: 10px; border-radius: 4px; }
        input, select { padding: 8px; margin-bottom: 10px; width: 100%; box-sizing: border-box; }
        #output { height: 300px; overflow: auto; background: #f4f4f4; padding: 10px; border-radius: 5px; }
        .section { margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>MCP Client Example</h1>
    
    <div class="section">
        <h2>Server Status</h2>
        <div id="status">Disconnected</div>
        <button id="connect">Connect</button>
    </div>
    
    <div class="section">
        <h2>Echo Resource</h2>
        <input type="text" id="echoMessage" placeholder="Enter a message...">
        <button id="sendEcho">Send Echo Request</button>
    </div>
    
    <div class="section">
        <h2>Calculator Tool</h2>
        <input type="number" id="operandA" placeholder="First number" value="10">
        <select id="operation">
            <option value="add">Add</option>
            <option value="subtract">Subtract</option>
            <option value="multiply">Multiply</option>
            <option value="divide">Divide</option>
        </select>
        <input type="number" id="operandB" placeholder="Second number" value="5">
        <button id="calculate">Calculate</button>
    </div>
    
    <div class="section">
        <h2>Output</h2>
        <div id="output"></div>
    </div>
    
    <script>
        // Client state
        let eventSource = null;
        let connected = false;
        let requestId = 0;
        let pendingRequests = {};
        
        // DOM elements
        const connectBtn = document.getElementById('connect');
        const statusEl = document.getElementById('status');
        const outputEl = document.getElementById('output');
        const echoMessageEl = document.getElementById('echoMessage');
        const sendEchoBtn = document.getElementById('sendEcho');
        const operandAEl = document.getElementById('operandA');
        const operationEl = document.getElementById('operation');
        const operandBEl = document.getElementById('operandB');
        const calculateBtn = document.getElementById('calculate');
        
        // Connect button
        connectBtn.addEventListener('click', () => {
            if (connected) {
                disconnect();
            } else {
                connect();
            }
        });
        
        // Echo button
        sendEchoBtn.addEventListener('click', () => {
            const message = echoMessageEl.value;
            if (!message || !connected) return;
            
            sendRequest('mcp/resources/read', { uri: `echo://${message}` })
                .then(response => {
                    log(`Echo response: ${JSON.stringify(response, null, 2)}`);
                })
                .catch(error => {
                    log(`Error: ${error.message}`);
                });
        });
        
        // Calculate button
        calculateBtn.addEventListener('click', () => {
            if (!connected) return;
            
            const a = parseFloat(operandAEl.value);
            const b = parseFloat(operandBEl.value);
            const operation = operationEl.value;
            
            sendRequest('mcp/tools/execute', {
                name: 'calculator',
                parameters: { operation, a, b }
            })
                .then(response => {
                    log(`Calculator response: ${JSON.stringify(response, null, 2)}`);
                })
                .catch(error => {
                    log(`Error: ${error.message}`);
                });
        });
        
        // Connect to the server
        function connect() {
            if (eventSource) {
                eventSource.close();
            }
            
            eventSource = new EventSource('/mcp/sse');
            
            eventSource.onopen = () => {
                log('SSE connection established');
            };
            
            eventSource.onmessage = (event) => {
                const data = JSON.parse(event.data);
                handleServerMessage(data);
            };
            
            eventSource.onerror = (error) => {
                log('SSE error: ' + JSON.stringify(error));
                disconnect();
            };
            
            // Send initialize request
            sendRequest('mcp/initialize', {
                // Client information
                client: {
                    name: 'Web Client',
                    version: '1.0.0'
                }
            })
                .then(response => {
                    connected = true;
                    statusEl.textContent = `Connected to ${response.name} v${response.version}`;
                    connectBtn.textContent = 'Disconnect';
                    log(`Connected to server: ${JSON.stringify(response, null, 2)}`);
                })
                .catch(error => {
                    log(`Initialization failed: ${error.message}`);
                    disconnect();
                });
        }
        
        // Disconnect from the server
        function disconnect() {
            if (eventSource) {
                eventSource.close();
                eventSource = null;
            }
            
            connected = false;
            statusEl.textContent = 'Disconnected';
            connectBtn.textContent = 'Connect';
            log('Disconnected from server');
        }
        
        // Send a request to the server
        function sendRequest(method, params = {}) {
            return new Promise((resolve, reject) => {
                const id = `req-${++requestId}`;
                
                const request = {
                    jsonrpc: '2.0',
                    method,
                    params,
                    id
                };
                
                pendingRequests[id] = { resolve, reject };
                
                fetch('/mcp/message', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(request)
                })
                .catch(error => {
                    delete pendingRequests[id];
                    reject(new Error(`Network error: ${error.message}`));
                });
            });
        }
        
        // Handle messages from the server
        function handleServerMessage(message) {
            log(`Received: ${JSON.stringify(message, null, 2)}`);
            
            // Check if it's a response to a request
            if (message.id && pendingRequests[message.id]) {
                const { resolve, reject } = pendingRequests[message.id];
                
                if (message.error) {
                    reject(new Error(message.error.message));
                } else if (message.result) {
                    resolve(message.result);
                }
                
                delete pendingRequests[message.id];
            }
        }
        
        // Log a message to the output
        function log(message) {
            const now = new Date().toLocaleTimeString();
            outputEl.innerHTML += `<div>[${now}] ${message}</div>`;
            outputEl.scrollTop = outputEl.scrollHeight;
        }
    </script>
</body>
</html>
HTML;
