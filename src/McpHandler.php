<?php

namespace Kirschbaum\Loop;

use Prism\Prism\Tool;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kirschbaum\Loop\Enums\MessageType;
use Kirschbaum\Loop\Exceptions\LoopMcpException;

class McpHandler
{
    /**
     * The registered resources
     *
     * @var array
     */
    protected array $resources = [];

    /**
     * The registered resource templates
     *
     * @var array
     */
    protected array $resourceTemplates = [];

    /**
     * The registered tools
     *
     * @var array
     */
    protected array $tools = [];

    /**
     * The registered prompts
     *
     * @var array
     */
    protected array $prompts = [];

    /**
     * The server information
     *
     * @var array
     */
    protected array $serverInfo;

    /**
     * The server capabilities
     *
     * @var array
     */
    protected array $serverCapabilities;

    /**
     * The latest supported protocol version
     */
    public const LATEST_PROTOCOL_VERSION = '2024-11-05';

    /**
     * Supported protocol versions
     */
    public const SUPPORTED_PROTOCOL_VERSIONS = [
        self::LATEST_PROTOCOL_VERSION,
        '2024-10-07',
    ];

    /**
     * JSON-RPC version
     */
    public const JSONRPC_VERSION = '2.0';

    /**
     * Create a new MCP service instance
     *
     * @param array $config Configuration options
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
            // 'resources' => [],
            'tools' => new \stdClass,
            // 'prompts' => [],
        ];
    }

    /**
     * {@inheritdoc}
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
     * Match a URI against a URI template and extract variables
     *
     * @param string $template URI template (RFC 6570)
     * @param string $uri URI to match against the template
     * @return array|null Variables extracted from the URI or null if no match
     */
    protected function matchUriTemplate(string $template, string $uri): ?array
    {
        // Simple implementation for basic templates like "users://{userId}/profile"
        $pattern = preg_quote($template, '/');
        $pattern = preg_replace('/\\\\{([^}]+)\\\\}/', '(?P<$1>[^/]+)', $pattern);
        $pattern = '/^' . $pattern . '$/';

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

    public function listTools(): array
    {
        $this->loop->setup();

        return [
            'tools' => $this->loop->getTools()->map(function (Tool $tool) {
                $tool = $tool instanceof Tool ? $tool : $tool->getTool();

                return [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'inputSchema' => $tool->parameters()['data'] ?? [
                        "type" => "object",
                        "properties" => new \stdClass,
                        "additionalProperties" => false,
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

    protected function parametersToMcpInputSchema(array $parameters): array
    {
        return array_map(function (array $parameter) {
            return $parameter['name'];
        }, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function callTool(string $name, array $arguments, Request $request): array
    {
        $tool = $this->loop->getTool($name);

        try {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $tool->handle($arguments),
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error("Error calling tool {$name}: {$e->getMessage()}", [
                'exception' => $e,
                'arguments' => $arguments,
            ]);

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

    /**
     * {@inheritdoc}
     */
    public function listPrompts(Request $request): array
    {
        $prompts = [];

        foreach ($this->prompts as $name => $prompt) {
            $promptData = [
                'name' => $name,
            ];

            if ($prompt['description'] !== null) {
                $promptData['description'] = $prompt['description'];
            }

            if (!empty($prompt['argsSchema'])) {
                $promptData['arguments'] = $this->promptArgumentsFromSchema($prompt['argsSchema']);
            }

            $prompts[] = $promptData;
        }

        return [
            'prompts' => $prompts,
        ];
    }

    /**
     * Convert argument schema to MCP prompt arguments
     *
     * @param array $schema The argument schema
     * @return array
     */
    protected function promptArgumentsFromSchema(array $schema): array
    {
        $arguments = [];

        foreach ($schema as $name => $properties) {
            $argument = [
                'name' => $name,
            ];

            if (isset($properties['description'])) {
                $argument['description'] = $properties['description'];
            }

            if (isset($properties['required']) && $properties['required']) {
                $argument['required'] = true;
            }

            $arguments[] = $argument;
        }

        return $arguments;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrompt(string $name, array $arguments, Request $request): array
    {
        if (!isset($this->prompts[$name])) {
            throw new LoopMcpException("Prompt {$name} not found");
        }

        $prompt = $this->prompts[$name];

        // Validate arguments against schema if provided
        if (!empty($prompt['argsSchema'])) {
            // Simple validation - in a real implementation, you would use a validation library
            foreach ($prompt['argsSchema'] as $argName => $properties) {
                if (isset($properties['required']) && $properties['required'] && !isset($arguments[$argName])) {
                    throw new LoopMcpException("Missing required argument: {$argName}");
                }
            }
        }

        try {
            return call_user_func($prompt['callback'], $arguments, $request);
        } catch (\Exception $e) {
            if ($this->isLoggingEnabled()) {
                Log::channel($this->getLogChannel())->error(
                    "Error getting prompt {$name}: {$e->getMessage()}",
                    ['exception' => $e, 'arguments' => $arguments]
                );
            }

            throw new LoopMcpException("Error getting prompt: {$e->getMessage()}");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function ping(Request $request): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function processMessage(MessageType $messageType, string $method, array $params, $id, Request $request): ?array
    {
        try {
            switch ($method) {
                case 'initialize':
                    $clientInfo = $params['clientInfo'] ?? [];
                    $capabilities = $params['capabilities'] ?? [];
                    $protocolVersion = $params['protocolVersion'] ?? self::LATEST_PROTOCOL_VERSION;

                    return $this->initialize($clientInfo, $capabilities, $protocolVersion);

                case 'ping':
                    return $this->ping($request);

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
                        $request
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
            Log::error(
                "Error processing message: {$e->getMessage()}",
                ['exception' => $e, 'method' => $method, 'params' => $params]
            );

            throw new LoopMcpException($e->getMessage());
        }
    }
}