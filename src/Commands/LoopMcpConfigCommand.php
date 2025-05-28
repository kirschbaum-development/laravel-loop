<?php

namespace Kirschbaum\Loop\Commands;

use Illuminate\Console\Command;

class LoopMcpConfigCommand extends Command
{
    protected $signature = 'loop:mcp:generate-config
                            {--url= : The base URL of your application (optional, defaults to config("app.url"))}';

    protected $description = 'Generate MCP server configuration to configure Laravel Loop in your MCP client';

    public function handle(): int
    {
        $this->info('🔧 Laravel Loop MCP Server Configuration');
        $this->newLine();

        $provider = $this->choice(
            'Which MCP provider will you be configuring?',
            ['Claude Code', 'MCP Servers JSON (Claude Desktop, Cursor, etc)'],
            0
        );

        if (! is_string($provider)) {
            $this->error('Invalid provider');

            return Command::FAILURE;
        }

        $transport = $this->choice(
            'Which transport method do you want to use?',
            ['STDIO', 'HTTP + SSE', 'Streamable HTTP'],
            0
        );

        $this->newLine();

        if ($transport === 'STDIO') {
            $this->generateStdioConfig($provider);
        } else {
            $this->generateHttpSseConfig($provider);
        }

        return Command::SUCCESS;
    }

    private function generateStdioConfig(string $provider): void
    {
        $this->info('📋 STDIO Transport Configuration');
        $this->newLine();

        $useAuth = $this->confirm('Do you want to authenticate with a specific user?', false);
        $userId = null;
        $userModel = null;

        if ($useAuth) {
            $userId = $this->ask('Enter the user ID to authenticate with', null);
            $userModel = $this->ask('Enter the user model class', 'App\\Models\\User');

            if (! is_string($userId) || ! is_string($userModel)) {
                $this->error('Invalid user ID or user model');

                return;
            }
        }

        $projectPath = base_path();

        if ($provider === 'Claude Code') {
            $this->generateClaudeCodeCommand($projectPath, $userId, $userModel);
        } else {
            $this->generateJsonConfig($projectPath, $userId, $userModel);
        }
    }

    private function generateHttpSseConfig(string $provider): void
    {
        $this->info('🌐 HTTP + SSE Transport Configuration');
        $this->newLine();

        $baseUrl = $this->ask('Enter your application base URL', config()->string('app.url', 'http://localhost:8000'));
        $ssePath = config()->string('loop.sse.path', '/mcp/sse');

        if (! is_string($baseUrl) || empty($ssePath)) {
            $this->error('Invalid base URL or SSE path');

            return;
        }

        if ($provider === 'Claude Code') {
            $this->generateClaudeCodeHttpConfig($baseUrl, $ssePath);
        } else {
            $this->generateJsonHttpConfig($baseUrl, $ssePath);
        }
    }

    private function generateClaudeCodeCommand(string $projectPath, ?string $userId, ?string $userModel): void
    {
        $command = "claude mcp add laravel-loop-mcp php {$projectPath}/artisan loop:mcp:start";

        if ($userId) {
            $command .= " -- --user-id={$userId}";
            if ($userModel && $userModel !== 'App\\Models\\User') {
                $command .= " --user-model={$userModel}";
            }
        }

        $this->info('🎯 Claude Code Configuration Command:');
        $this->newLine();
        $this->line("<fg=green>{$command}</>");
        $this->newLine();
        $this->comment('💡 Copy and paste this command in your terminal to add the MCP server to Claude Code.');
    }

    private function generateJsonConfig(string $projectPath, ?string $userId, ?string $userModel): void
    {
        $args = [
            "{$projectPath}/artisan",
            'loop:mcp:start',
        ];

        if ($userId) {
            $args[] = "--user-id={$userId}";
            if ($userModel && $userModel !== 'App\\Models\\User') {
                $args[] = "--user-model={$userModel}";
            }
        }

        $config = [
            'mcpServers' => [
                'laravel-loop-mcp' => [
                    'command' => 'php',
                    'args' => $args,
                ],
            ],
        ];

        $this->info('📄 JSON Configuration:');
        $this->newLine();
        $this->line('<fg=green>'.json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).'</>');
        $this->newLine();
        $this->comment('💡 Add this configuration to your MCP client configuration file.');
    }

    private function generateClaudeCodeHttpConfig(string $baseUrl, string $ssePath): void
    {
        $headers = $this->collectHeaders();

        $fullUrl = rtrim($baseUrl, '/').$ssePath;
        $command = "claude mcp add laravel-loop-mcp {$fullUrl} -t sse";

        if (! empty($headers)) {
            $command .= sprintf(' --header "%s"', implode(', ', $headers));
        }

        $this->info('🎯 Claude Code HTTP + SSE Configuration Command:');
        $this->newLine();
        $this->line("<fg=green>{$command}</>");
        $this->newLine();
        $this->comment('💡 Copy and paste this command in your terminal to add the MCP server to Claude Code.');
        $this->newLine();
        $this->info('🔧 Additional Setup Required:');
        $this->line('1. Enable SSE in your .env file: LOOP_SSE_ENABLED=true');
        $this->line('2. Configure authentication middleware in config/loop.php');
        $this->line('3. Ensure your Laravel application is running and accessible');
    }

    /**
     * @return array<string>
     */
    private function collectHeaders(): array
    {
        $headers = [];

        $this->info('📋 Header Configuration');
        $this->comment('Enter headers one by one. Press Enter without typing anything when you\'re done.');
        $this->newLine();

        while (true) {
            $header = $this->ask('Enter a header (Format: "Header-Name: value") or press Enter to finish');

            if (! is_string($header) || empty($header)) {
                break;
            }

            if (! str_contains($header, ':')) {
                $this->error('Invalid header format. Please use "Header-Name: value" format.');

                continue;
            }

            $headers[] = $header;
            $this->line("✅ Added: {$header}");
        }

        if (! empty($headers)) {
            $this->newLine();
            $this->info('📋 Headers to be added:');
            foreach ($headers as $header) {
                $this->line("  • {$header}");
            }
            $this->newLine();
        }

        return $headers;
    }

    private function generateJsonHttpConfig(string $baseUrl, string $ssePath): void
    {
        $config = [
            'mcpServers' => [
                'laravel-loop-mcp' => [
                    'transport' => 'sse',
                    'url' => rtrim($baseUrl, '/').$ssePath,
                ],
            ],
        ];

        $this->info('📄 JSON Configuration for HTTP + SSE:');
        $this->newLine();
        $this->line('<fg=green>'.json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).'</>', 'verbatim');
        $this->newLine();
        $this->comment('💡 Add this configuration to your MCP client configuration file.');
        $this->newLine();
        $this->info('🔧 Additional Setup Required:');
        $this->line('1. Enable SSE in your .env file: LOOP_SSE_ENABLED=true');
        $this->line('2. Configure authentication middleware in config/loop.php');
        $this->line('3. Ensure your Laravel application is running and accessible');
    }
}
