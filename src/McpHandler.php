<?php

namespace Kirschbaum\Loop;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kirschbaum\Loop\Concerns\LogsMessages;
use Kirschbaum\Loop\Enums\ErrorCode;
use Kirschbaum\Loop\Enums\MessageType;
use Kirschbaum\Loop\Exceptions\LoopMcpException;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Tool;

class McpHandler
{
    use LogsMessages;

    public const LATEST_PROTOCOL_VERSION = '2024-11-05';

    public const SUPPORTED_PROTOCOL_VERSIONS = [
        self::LATEST_PROTOCOL_VERSION,
        '2024-10-07',
    ];

    public const JSONRPC_VERSION = '2.0';

    /** @var array<array-key, mixed> */
    protected array $resources = [];

    /** @var array<array-key, mixed> */
    protected array $resourceTemplates = [];

    /** @var array<array-key, mixed> */
    protected array $tools = [];

    /** @var array<array-key, mixed> */
    protected array $prompts = [];

    /** @var array<array-key, mixed> */
    protected array $serverInfo;

    /** @var array<array-key, mixed> */
    protected array $serverCapabilities;

    /**
     * @param  array<array-key, array<array-key, mixed>>  $config
     */
    public function __construct(
        protected Loop $loop,
        protected array $config = []
    ) {
        $this->serverInfo = $this->config['serverInfo'] ?? [
            'name' => 'Laravel MCP Server',
            'version' => '1.0.0',
        ];

        $this->serverCapabilities = $this->config['capabilities'] ?? [
            'tools' => $this->listTools(),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $clientInfo
     * @param  array<array-key, mixed>  $capabilities
     * @return array<array-key, mixed>
     */
    public function initialize(array $clientInfo, array $capabilities, string $protocolVersion): array
    {
        if (! in_array($protocolVersion, self::SUPPORTED_PROTOCOL_VERSIONS)) {
            $protocolVersion = self::LATEST_PROTOCOL_VERSION;
        }

        $instructions = $this->config['instructions'] ?? null;

        $result = [
            'protocolVersion' => $protocolVersion,
            'serverInfo' => $this->serverInfo,
            'capabilities' => $this->serverCapabilities,
        ];

        if ($instructions !== null) {
            $result['instructions'] = $instructions;
        }

        return $result;
    }

    /**
     * @return array{tools: array<array-key, mixed>}
     */
    public function listTools(): array
    {
        $this->loop->setup();

        return [
            'tools' => $this->loop->getPrismTools()->map(function (Tool $tool) {
                $tool = $tool instanceof Tool ? $tool : $tool->getTool();
                $parameters = $tool->parameters();
                $hasParameters = count($parameters) > 0;

                return [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => $hasParameters ? $tool->parameters() : new \stdClass,
                        'required' => $hasParameters ? $tool->requiredParameters() : [],
                        'additionalProperties' => false,
                    ],
                ];
            })->toArray(),
        ];
    }

    public function emptyList(string $key = 'tools'): array
    {
        return [
            $key => [],
        ];
    }

    public function callTool(string $name, array $arguments): array
    {
        $tool = $this->loop->getPrismTool($name);

        try {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $tool->handle(...$arguments),
                    ],
                ],
            ];
        } catch (PrismException $e) {
            $this->log("Error calling tool {$name}: {$e->getMessage()}", [
                'arguments' => $arguments,
                'trace' => $e->getTrace(),
                'exception' => $e->getPrevious()?->getMessage(),
            ], level: 'error');

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $e->getMessage(),
                    ],
                ],
                'isError' => true,
            ];
        } catch (\Exception $e) {
            $this->log("Error calling tool {$name}: {$e->getMessage()}", [
                'exception' => $e,
                'arguments' => $arguments,
            ], level: 'error');

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $e->getMessage(),
                    ],
                ],
                'isError' => true,
            ];
        }
    }

    public function ping(): array
    {
        return [];
    }

    public function handle(array $message): array
    {
        if (! isset($message['jsonrpc']) || $message['jsonrpc'] !== '2.0') {
            return $this->formatErrorResponse(
                null,
                ErrorCode::INVALID_REQUEST,
                'Invalid JSON-RPC version'
            );
        }

        if (! isset($message['method']) || ! is_string($message['method'])) {
            return $this->formatErrorResponse(
                $message['id'] ?? null,
                ErrorCode::INVALID_REQUEST,
                'Missing or invalid method'
            );
        }

        // TODO: Handle/log errors like {\"jsonrpc\":\"2.0\",\"id\":0,\"error\":{\"code\":-32601,\"message\":\"Method not found\"}

        $method = $message['method'];
        $params = $message['params'] ?? [];
        $id = $message['id'] ?? null;

        $message = $this->processMessage(
            method: $method,
            params: $params,
            id: $id,
        );

        return $this->successResponse($id, $message);
    }

    public function formatErrorResponse($id, int $code, string $message, $data = null): array
    {
        $response = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($data !== null) {
            $response['error']['data'] = $data;
        }

        return $response;
    }

    public function processMessage(string $method, array $params, $id): ?array
    {
        $messageType = Str::startsWith($method, 'notification')
            ? MessageType::NOTIFICATION
            : MessageType::REQUEST;

        try {
            if ($messageType === MessageType::NOTIFICATION) {
                return ['success' => true];
            }

            switch ($method) {
                case 'initialize':
                    $clientInfo = $params['clientInfo'] ?? [];
                    $capabilities = $params['capabilities'] ?? [];
                    $protocolVersion = $params['protocolVersion'] ?? self::LATEST_PROTOCOL_VERSION;

                    return $this->initialize($clientInfo, $capabilities, $protocolVersion);

                case 'ping':
                    return $this->ping();

                case 'resources/list':
                    return $this->emptyList('resources');

                case 'resources/templates/list':
                    return $this->emptyList('resourceTemplates');

                case 'resources/read':
                    throw new LoopMcpException('Resource not found');
                case 'tools/list':
                    return $this->listTools();

                case 'tools/call':
                    if (! isset($params['name'])) {
                        throw new LoopMcpException('Missing required parameter: name');
                    }

                    return $this->callTool(
                        $params['name'],
                        $params['arguments'] ?? [],
                    );

                case 'prompts/list':
                    return $this->emptyList('prompts');

                case 'prompts/get':
                    throw new LoopMcpException('Prompt not found');
                default:
                    throw new LoopMcpException($method);
            }
        } catch (LoopMcpException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->log("Error processing message: {$e->getMessage()}", [
                'exception' => $e,
                'method' => $method,
                'params' => $params,
            ], level: 'error');

            throw new LoopMcpException($e->getMessage());
        }
    }

    /**
     * Match a URI against a URI template and extract variables
     *
     * @param  string  $template  URI template (RFC 6570)
     * @param  string  $uri  URI to match against the template
     * @return array<array-key, mixed>|null Variables extracted from the URI or null if no match
     */
    protected function matchUriTemplate(string $template, string $uri): ?array
    {
        // Simple implementation for basic templates like "users://{userId}/profile"
        $pattern = preg_quote($template, '/');
        $pattern = preg_replace('/\\\\{([^}]+)\\\\}/', '(?P<$1>[^/]+)', $pattern);
        $pattern = '/^'.$pattern.'$/';

        if (preg_match($pattern, $uri, $matches)) {
            $variables = [];

            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $variables[$key] = $value;
                }
            }

            return $variables;
        }

        return null;
    }

    protected function parametersToMcpInputSchema(array $parameters): array
    {
        return array_map(function (array $parameter) {
            return $parameter['name'];
        }, $parameters);
    }

    protected function successResponse($id, $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $message,
        ];
    }
}
