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
     * {@inheritdoc}
     */
    public function registerResource(string $name, string $uri, callable $readCallback, array $metadata = []): self
    {
        if (isset($this->resources[$uri])) {
            throw new \RuntimeException("Resource {$uri} is already registered");
        }

        $this->resources[$uri] = [
            'name' => $name,
            'metadata' => $metadata,
            'readCallback' => $readCallback,
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function registerResourceTemplate(
        string $name,
        string $uriTemplate,
        callable $readCallback,
        array $metadata = [],
        ?callable $listCallback = null,
        array $completeCallbacks = []
    ): self {
        if (isset($this->resourceTemplates[$name])) {
            throw new \RuntimeException("Resource template {$name} is already registered");
        }

        $this->resourceTemplates[$name] = [
            'uriTemplate' => $uriTemplate,
            'metadata' => $metadata,
            'readCallback' => $readCallback,
            'listCallback' => $listCallback,
            'completeCallbacks' => $completeCallbacks,
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function registerTool(string $name, callable $callback, array $inputSchema = [], ?string $description = null): self
    {
        if (isset($this->tools[$name])) {
            throw new \RuntimeException("Tool {$name} is already registered");
        }

        $this->tools[$name] = [
            'description' => $description,
            'inputSchema' => (! $inputSchema || count($inputSchema) === 0)
                ? new \stdClass
                : $inputSchema,
            'callback' => $callback,
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function registerPrompt(string $name, callable $callback, array $argsSchema = [], ?string $description = null): self
    {
        if (isset($this->prompts[$name])) {
            throw new \RuntimeException("Prompt {$name} is already registered");
        }

        $this->prompts[$name] = [
            'description' => $description,
            'argsSchema' => $argsSchema,
            'callback' => $callback,
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function listResources(Request $request): array
    {
        $resources = [];

        // Add fixed resources
        foreach ($this->resources as $uri => $resource) {
            $resources[] = [
                'uri' => $uri,
                'name' => $resource['name'],
            ] + $resource['metadata'];
        }

        // Add resources from templates
        foreach ($this->resourceTemplates as $templateName => $template) {
            if (!$template['listCallback']) {
                continue;
            }

            $result = call_user_func($template['listCallback'], $request);
            if (isset($result['resources']) && is_array($result['resources'])) {
                foreach ($result['resources'] as $resource) {
                    $resources[] = array_merge($resource, $template['metadata']);
                }
            }
        }

        return [
            'resources' => $resources,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function listResourceTemplates(Request $request): array
    {
        $templates = [];

        foreach ($this->resourceTemplates as $name => $template) {
            $templates[] = [
                'name' => $name,
                'uriTemplate' => $template['uriTemplate'],
            ] + $template['metadata'];
        }

        return [
            'resourceTemplates' => $templates,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function readResource(string $uri, Request $request): array
    {
        $parsedUri = parse_url($uri);
        if ($parsedUri === false) {
            throw new LoopMcpException("Invalid URI: {$uri}");
        }

        // Check for exact resource match
        if (isset($this->resources[$uri])) {
            return call_user_func($this->resources[$uri]['readCallback'], $uri, $request);
        }

        // Check for template matches
        foreach ($this->resourceTemplates as $template) {
            $uriTemplate = $template['uriTemplate'];
            $variables = $this->matchUriTemplate($uriTemplate, $uri);

            if ($variables !== null) {
                return call_user_func($template['readCallback'], $uri, $variables, $request);
            }
        }

        throw new LoopMcpException("Resource not found: {$uri}");
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
                return [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'inputSchema' => $tool->parameters()['data'] ?? [
                        "type" => "object",
                        "properties" => new \stdClass,
                        "additionalProperties" => false,
                    ],
                    // 'inputSchema' => $this->parametersToMcpInputSchema($tool->parameters()),
                ];
            })->toArray(),
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
        if ($messageType === MessageType::NOTIFICATION) {
            switch ($method) {
                case 'notifications/initialized':
                    // Handle initialized notification
                    break;
                case 'notifications/cancelled':
                    // Handle cancelled notification
                    break;
                default:
                    if ($this->isLoggingEnabled()) {
                        Log::channel($this->getLogChannel())->warning(
                            "Received unknown notification: {$method}"
                        );
                    }
            }

            return null;
        }
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
                    return $this->listResources($request);

                case 'resources/templates/list':
                    return $this->listResourceTemplates($request);

                case 'resources/read':
                    if (!isset($params['uri'])) {
                        throw new LoopMcpException('Missing required parameter: uri');
                    }
                    return $this->readResource($params['uri'], $request);

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
                    return $this->listPrompts($request);

                case 'prompts/get':
                    if (!isset($params['name'])) {
                        throw new LoopMcpException('Missing required parameter: name');
                    }
                    return $this->getPrompt(
                        $params['name'],
                        $params['arguments'] ?? [],
                        $request
                    );

                default:
                    throw new LoopMcpException($method);
            }
        } catch (LoopMcpException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($this->isLoggingEnabled()) {
                Log::error(
                    "Error processing message: {$e->getMessage()}",
                    ['exception' => $e, 'method' => $method, 'params' => $params]
                );
            }

            throw new LoopMcpException($e->getMessage());
        }
    }

    /**
     * Check if logging is enabled
     *
     * @return bool
     */
    protected function isLoggingEnabled(): bool
    {
        return Arr::get($this->config, 'logging.enabled', false);
    }

    /**
     * Get the log channel
     *
     * @return string
     */
    protected function getLogChannel(): string
    {
        return Arr::get($this->config, 'logging.channel', 'stack');
    }
}