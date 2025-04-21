<?php

namespace Kirschbaum\Loop\Commands;

use Exception;
use React\EventLoop\Loop;
use Illuminate\Console\Command;
use Kirschbaum\Loop\McpHandler;
use Kirschbaum\Loop\Enums\ErrorCode;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;

class LoopMcpServerStartCommand extends Command
{
    protected $signature = 'loop:mcp:start';

    protected $description = 'Run the Laravel Loop MCP server';

    public function handle(McpHandler $mcpHandler)
    {
        $this->info("Starting OpenControl MCP proxy server");

        $loop = Loop::get();
        $stdin = new ReadableResourceStream(STDIN, $loop);
        $stdout = new WritableResourceStream(STDOUT, $loop);

        $stdin->on('data', function ($data) use ($stdout, $mcpHandler) {
            try {
                $message = (array) json_decode($data, true);
                $response = $mcpHandler->handle($message);

                if (isset($message['id'])) {
                    $stdout->write(json_encode($response) . PHP_EOL);
                }

                $stdout->write($data);
            } catch (Exception $e) {
                $this->error("Error processing message: " . $e->getMessage());

                $response = $mcpHandler->formatErrorResponse(
                    $message['id'] ?? '',
                    ErrorCode::INTERNAL_ERROR,
                    $e->getMessage()
                );

                $stdout->write(json_encode($response) . PHP_EOL);
            }
        });

        $this->info("Laravel Loop MCP server running. Press Ctrl+C to stop.");

        while (true) {
            $loop->run();
        }
    }
}
