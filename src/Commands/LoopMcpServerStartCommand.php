<?php

namespace Kirschbaum\Loop\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Kirschbaum\Loop\Enums\ErrorCode;
use Kirschbaum\Loop\McpHandler;
use React\EventLoop\Loop;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;

if (! function_exists('pcntl_signal')) {
    fwrite(STDERR, "The pcntl extension is required for signal handling but is not available.\n");
}

class LoopMcpServerStartCommand extends Command
{
    protected $signature = 'loop:mcp:start
                            {--user-id= : The user ID to authenticate the requests with}
                            {--user-model= : The model to use to authenticate the requests with}
                            {--debug : Enable debug mode}';

    protected $description = 'Run the Laravel Loop MCP server';

    public function handle(McpHandler $mcpHandler): int
    {
        if ($this->option('debug')) {
            $this->debug('Starting Laravel Loop MCP server (STDIO transport)');
        }

        if ($this->option('user-id')) {
            $userModel = $this->option('user-model') ?? 'App\\Models\\User';
            $user = $userModel::find($this->option('user-id'));

            if (! $user) {
                $this->error(sprintf('User with ID %s not found. Model used: %s', $this->option('user-id'), $userModel));

                return Command::FAILURE;
            }

            Auth::login($user);

            if ($this->option('debug')) {
                $this->debug(sprintf('Authenticated with user ID %s', $this->option('user-id')));
            }
        }

        $loop = Loop::get();
        $stdin = new ReadableResourceStream(STDIN, $loop);
        $stdout = new WritableResourceStream(STDOUT, $loop);

        $stdin->on('data', function ($data) use ($stdout, $mcpHandler) {
            if ($this->option('debug')) {
                $this->debug('Received data: '.$data);
            }

            foreach (explode("\n", trim($data)) as $line) {
                if (! json_validate($line)) {
                    if ($this->option('debug')) {
                        $this->debug('Invalid line: '.$line);
                    }

                    continue;
                }

                try {
                    $message = (array) json_decode($line, true);
                    $response = $mcpHandler->handle($message);

                    if ($this->option('debug')) {
                        $this->debug('Response: '.json_encode($response));
                    }

                    if (isset($message['id'])) {
                        $stdout->write(json_encode($response).PHP_EOL);
                    }
                } catch (\Throwable $e) {
                    $this->debug('Error processing message: '.$e->getMessage());

                    $response = $mcpHandler->formatErrorResponse(
                        $message['id'] ?? '',
                        ErrorCode::INTERNAL_ERROR,
                        $e->getMessage()
                    );

                    $stdout->write(json_encode($response).PHP_EOL);

                    report($e);
                }
            }

        });

        if ($this->option('debug')) {
            $this->debug('Laravel Loop MCP server running. Press Ctrl+C or send SIGTERM to stop.');
        }

        // Add signal handlers if pcntl is available
        if (function_exists('pcntl_signal')) {
            $loop->addSignal(SIGINT, function ($signal) use ($loop) {
                info('Received signal: '.$signal.'. Shutting down...');

                if ($this->option('debug')) {
                    $this->debug('Received signal: '.$signal.'. Shutting down...');
                }

                $loop->stop();

                exit(0);
            });

            $loop->addSignal(SIGTERM, function ($signal) use ($loop) {
                info('Received signal: '.$signal.'. Shutting down...');

                if ($this->option('debug')) {
                    $this->info('Received signal: '.$signal.'. Shutting down...');
                }

                $loop->stop();

                exit(0);
            });
        } else {
            $this->warn('PCNTL extension not available. Signal handling disabled. Use Ctrl+C if supported by your environment.');
        }

        $loop->run();

        $this->info('Laravel Loop MCP server stopped.');

        return Command::SUCCESS;
    }

    protected function debug($message)
    {
        $this->getOutput()->getOutput()->getErrorOutput()->writeln($message);
    }
}
