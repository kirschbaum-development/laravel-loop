<?php

namespace Kirschbaum\Loop\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kirschbaum\Loop\Enums\ErrorCode;
use Kirschbaum\Loop\McpHandler;
use Symfony\Component\HttpFoundation\StreamedResponse;

class McpSseController extends Controller
{
    /**
     * Handle the MCP request and return a streamed SSE response.
     */
    public function __invoke(Request $request, McpHandler $mcpHandler): StreamedResponse
    {
        if (! $request->isJson()) {
            return $this->createErrorStream('Invalid request format: Content-Type must be application/json.', ErrorCode::PARSE_ERROR);
        }

        $mcpRequest = $request->json()->all();
        $requestId = $mcpRequest['id'] ?? null; // Get ID early for error reporting

        if (! isset($mcpRequest['jsonrpc']) || $mcpRequest['jsonrpc'] !== '2.0' || ! isset($mcpRequest['method'])) {
            return $this->createErrorStream('Invalid MCP request structure.', ErrorCode::INVALID_REQUEST, $requestId);
        }

        // Get the heartbeat interval from config (0 disables heartbeats)
        $heartbeatInterval = config('loop.sse.heartbeat_interval', 30);

        $response = new StreamedResponse(function () use ($mcpHandler, $mcpRequest, $requestId, $heartbeatInterval) {
            $lastHeartbeatTime = time();
            $heartbeatEnabled = $heartbeatInterval > 0;

            try {
                $mcpResponse = $mcpHandler->handle($mcpRequest);

                // Format and send the single MCP response as an SSE event
                $this->sendSseEvent(
                    id: $mcpResponse['id'] ?? $requestId ?? now()->timestamp,
                    event: 'mcp_response',
                    data: json_encode($mcpResponse)
                );

                // If heartbeats are enabled, keep the connection open for a while
                // This is useful for clients that might make subsequent requests
                // or for demonstrating the heartbeat functionality
                if ($heartbeatEnabled) {
                    // Keep connection open for a short time with heartbeats
                    $timeoutTime = time() + 60; // Keep alive for up to 60 seconds after response

                    while (time() < $timeoutTime) {
                        // Check if it's time to send a heartbeat
                        if (time() - $lastHeartbeatTime >= $heartbeatInterval) {
                            $this->sendHeartbeat();
                            $lastHeartbeatTime = time();
                        }

                        usleep(100000); // 100ms
                    }
                }

            } catch (\Throwable $e) {
                $errorResponse = $mcpHandler->formatErrorResponse(
                    $requestId,
                    ErrorCode::INTERNAL_ERROR,
                    'Server error processing request: '.$e->getMessage()
                );

                $this->sendSseEvent(
                    id: $requestId ?? now()->timestamp,
                    event: 'mcp_error',
                    data: json_encode($errorResponse)
                );

                \Illuminate\Support\Facades\Log::error('MCP SSE Error: '.$e->getMessage(), [
                    'exception' => $e,
                    'request' => $mcpRequest,
                ]);
            }

            // Ensure output is sent
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);

        return $response;
    }

    /**
     * Helper to send a formatted SSE event.
     *
     * @param  string|int|null  $id  Event ID
     * @param  string  $event  Event type
     * @param  string  $data  Event data
     */
    private function sendSseEvent(string|int|null $id, string $event, string $data): void
    {
        if ($id !== null) {
            echo 'id: '.$id."\n";
        }
        echo 'event: '.$event."\n";
        // Ensure data is multi-line safe
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            echo 'data: '.$line."\n";
        }
        echo "\n"; // End of event

        // Flush buffers
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * Send an SSE heartbeat comment to keep the connection alive.
     */
    private function sendHeartbeat(): void
    {
        // SSE comments start with ': ' and are ignored by EventSource
        echo ': heartbeat '.time()."\n\n";

        // Flush buffers
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * Helper to create a StreamedResponse for immediate errors.
     *
     * @param  string  $message  Error message
     * @param  int  $code  Error code
     * @param  string|int|null  $requestId  Request ID if available
     */
    private function createErrorStream(string $message, int $code, $requestId = null): StreamedResponse
    {
        $mcpHandler = app(McpHandler::class); // Resolve handler for formatting
        $errorResponse = $mcpHandler->formatErrorResponse($requestId, $code, $message);

        return new StreamedResponse(function () use ($requestId, $errorResponse) {
            $this->sendSseEvent(
                id: $requestId ?? now()->timestamp,
                event: 'mcp_error',
                data: json_encode($errorResponse)
            );
        }, 400, [ // Use appropriate HTTP status for initial errors (e.g., 400 Bad Request)
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
