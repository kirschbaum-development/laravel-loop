<?php

declare(strict_types=1);

namespace Kirschbaum\Loop\Commands;

use Exception;
use Illuminate\Console\Command;
use Kirschbaum\Loop\Commands\Concerns\AuthenticateUsers;
use Kirschbaum\Loop\Loop;
use Kirschbaum\Loop\McpHandler;
use Prism\Prism\Tool;

class LoopMcpCallCommand extends Command
{
    use AuthenticateUsers;

    protected $signature = 'loop:mcp:call
                            {--tool= : The name of the tool to call (optional, if not provided, you will be prompted to select a tool)}
                            {--parameters=* : Array of key=value parameters for the tool (optional, if not provided, you will be prompted to enter the parameters)}
                            {--user-id= : The user ID to authenticate the requests with}
                            {--user-model= : The model to use to authenticate the requests with}
                            {--auth-guard= : The Auth guard to use to authenticate the requests with}
                            {--debug : Enable debug mode}';

    protected $description = 'Call an MCP tool interactively. Great for testing and debugging.';

    protected McpHandler $mcpHandler;

    protected bool $printedToolsList = false;

    protected string $lastToolName = '';

    /** @var array<string, mixed> */
    protected $lastToolParameters = [];

    public function handle(McpHandler $mcpHandler): void
    {
        $this->mcpHandler = $mcpHandler;

        if ($this->option('user-id')) {
            $this->authenticateUser();
        } else {
            $this->promptForAuthentication();
        }

        do {
            $toolName = $this->getToolName();

            if (! $toolName) {
                $this->error('No tool selected. Exiting...');

                return;
            }

            if ($toolName === '_repeat_last_tool_call') {
                $toolName = $this->lastToolName;
                $parameters = $this->lastToolParameters;
            } else {
                $parameters = $this->getToolParameters($toolName);
            }

            $this->comment(sprintf('----- Calling tool: %s -----', now()->format('Y-m-d H:i:s')));
            $this->newLine();
            $this->info("Tool: {$toolName}");
            if (! empty($parameters)) {
                $this->info('Parameters: '.json_encode($parameters, JSON_PRETTY_PRINT));
            }

            try {
                $response = $this->mcpHandler->callTool($toolName, $parameters);

                $this->lastToolName = $toolName;
                $this->lastToolParameters = $parameters;

                $this->newLine();
                $this->comment('----- Response -----');
                $this->newLine();

                if (isset($response['isError']) && $response['isError']) {
                    $this->error($response['content'][0]['text'] ?? 'Unknown error');
                } else {
                    $content = $response['content'][0]['text'] ?? 'No content returned';

                    if (is_string($content) && $this->isValidJson($content)) {
                        $prettyJson = json_encode(json_decode($content, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                        $this->line($prettyJson ?: $content);
                    } else {
                        $this->line($content);
                    }
                }

                $this->newLine();
                $this->comment('----- End of Response -----');
                $this->newLine();

            } catch (Exception $e) {
                $this->error('Error calling tool: '.$e->getMessage());

                if ($this->option('debug')) {
                    $this->error('Stack trace:');
                    $this->error($e->getTraceAsString());
                }
            }
        } while (true);
    }

    protected function getToolName(): ?string
    {
        $toolName = $this->option('tool');

        if (! $toolName) {
            if ($this->printedToolsList) {
                $this->comment(sprintf('----- Tool call finished at %s -----', now()->format('Y-m-d H:i:s')));
                $this->comment('You can call another tool, repeat the last tool call or exit.');
                $this->newLine();
            }

            $availableTools = $this->getAvailableTools();

            if (empty($availableTools)) {
                $this->error('No tools available');

                return null;
            }

            $this->info('Available tools:');

            foreach ($availableTools as $index => $tool) {
                $this->line(sprintf('%d. %s - %s', $index + 1, $tool['name'], $tool['description']));
            }

            if ($this->printedToolsList) {
                $this->line(sprintf('%d. exit - Exit the command', count($availableTools) + 1));
            }

            $toolNames = array_map(fn ($tool) => $tool['name'], $availableTools);
            if ($this->printedToolsList) {
                $toolNames[] = 'exit';
            }

            $this->printedToolsList = true;

            $choice = $this->anticipate(
                'Enter tool number or name (use the arrow keys for autocompletion)',
                $toolNames,
            );

            if (is_numeric($choice)) {
                $index = (int) $choice - 1;

                if (isset($availableTools[$index])) {
                    $toolName = $availableTools[$index]['name'];
                } elseif ($index === count($availableTools)) {
                    // Exit option selected
                    return null;
                }
            } else {
                if ($choice === 'exit') {
                    return null;
                }
                $toolName = is_string($choice) ? $choice : null;
            }
        }

        return is_string($toolName) ? $toolName : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getToolParameters(string $toolName): array
    {
        /** @var array<int, string>|null */
        $optionParameters = $this->option('parameters');

        /** @var array<string, mixed> */
        $parameters = [];

        // Parse command line parameters first
        if ($optionParameters) {
            foreach ($optionParameters as $param) {
                if ($param && strpos($param, '=') !== false) {
                    [$key, $value] = explode('=', $param, 2);
                    $parameters[trim($key)] = trim($value);
                }
            }
        }

        // Get tool schema to know what parameters are needed
        $tool = $this->getToolByName($toolName);
        if (! $tool) {
            $this->warn("Tool '{$toolName}' not found. Using provided parameters only.");

            return $parameters;
        }

        $toolParameters = $tool->parameters();
        $requiredParameters = $tool->requiredParameters();

        // Ask for missing required parameters
        foreach ($requiredParameters as $requiredParam) {
            if (! isset($parameters[$requiredParam])) {
                $paramInfo = $toolParameters[$requiredParam] ?? [];
                $description = is_string($paramInfo['description'] ?? null) ? $paramInfo['description'] : '';
                $type = is_string($paramInfo['type'] ?? null) ? $paramInfo['type'] : 'string';

                $prompt = "Enter value for '{$requiredParam}'";
                if ($description) {
                    $prompt .= " ({$description})";
                }
                $prompt .= ':';

                $value = $this->ask($prompt) ?? '';

                // Convert types if needed
                if ($type === 'integer' && is_string($value)) {
                    $value = (int) $value;
                } elseif ($type === 'boolean' && is_string($value)) {
                    $value = in_array(strtolower($value), ['true', '1', 'yes', 'y']);
                } elseif ($type === 'array' && is_string($value)) {
                    $decoded = json_decode($value, true);
                    $value = is_array($decoded) ? $decoded : explode(',', $value);
                }

                $parameters[$requiredParam] = $value;
            }
        }

        // Offer to fill optional parameters
        foreach ($toolParameters as $paramName => $paramInfo) {
            if (! in_array($paramName, $requiredParameters) && ! isset($parameters[$paramName])) {
                $description = is_string($paramInfo['description'] ?? null) ? $paramInfo['description'] : '';
                $type = is_string($paramInfo['type'] ?? null) ? $paramInfo['type'] : 'string';

                $prompt = "Enter value for optional parameter '{$paramName}'";
                if ($description) {
                    $prompt .= " ({$description})";
                }
                $prompt .= ' [leave empty to skip]:';

                $value = $this->ask($prompt);

                if ($value !== null && $value !== '') {
                    // Convert types if needed
                    if ($type === 'integer' && is_string($value)) {
                        $value = (int) $value;
                    } elseif ($type === 'boolean' && is_string($value)) {
                        $value = in_array(strtolower($value), ['true', '1', 'yes', 'y']);
                    } elseif ($type === 'array' && is_string($value)) {
                        $decoded = json_decode($value, true);
                        $value = is_array($decoded) ? $decoded : explode(',', $value);
                    }

                    $parameters[$paramName] = $value;
                }
            }
        }

        return $parameters;
    }

    /**
     * @return array<int, array{name: string, description: string}>
     */
    protected function getAvailableTools(): array
    {
        try {
            $toolsList = $this->mcpHandler->listTools();
            /** @var array<int, array{name: string, description: string}> */
            $tools = $toolsList['tools'];

            if ($this->lastToolName) {
                $tools[] = ['name' => '_repeat_last_tool_call', 'description' => 'Repeat the last tool call'];
            }

            return $tools;
        } catch (Exception $e) {
            $this->error('Error fetching tools: '.$e->getMessage());

            return [];
        }
    }

    protected function getToolByName(string $name): ?Tool
    {
        try {
            // Use the listTools method to get tools instead of accessing the protected property
            $toolsList = $this->mcpHandler->listTools();
            $tools = $toolsList['tools'];

            foreach ($tools as $toolData) {
                if (is_array($toolData) && isset($toolData['name']) && $toolData['name'] === $name) {
                    return app(Loop::class)->getPrismTool($name);
                }
            }

            return null;
        } catch (Exception) {
            return null;
        }
    }

    protected function promptForAuthentication(): void
    {
        if ($this->confirm('Would you like to authenticate with a user ID for this session?', false)) {
            $userId = $this->ask('Enter user ID');

            if ($userId) {
                // Set the option programmatically so authenticateUser() can access it
                $this->input->setOption('user-id', $userId);
                $this->authenticateUser();

                $this->info('Authentication successful!');
                $this->newLine();
            }
        }
    }

    protected function isValidJson(string $string): bool
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
