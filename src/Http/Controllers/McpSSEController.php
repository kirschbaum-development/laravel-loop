<?php

namespace Kirschbaum\Loop\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Kirschbaum\Loop\McpHandler;
use Kirschbaum\Loop\Services\SseService;
use Kirschbaum\Loop\Services\SseSessionManager;
use Symfony\Component\HttpFoundation\StreamedResponse;

class McpSSEController extends Controller
{
    public function __construct(
        protected McpHandler $mcpHandler,
        protected SseService $sse,
        protected SseSessionManager $sessionManager
    ) {}

    /**
     * Handle SSE connection setup (GET request), creates server-client event stream.
     */
    public function connect(Request $request): StreamedResponse
    {
        $sessionId = uniqid();
        $ssePathConfig = config('loop.sse.path', '/mcp/sse');
        $ssePath = is_string($ssePathConfig) ? $ssePathConfig : '/mcp/sse';
        $postEndpoint = $ssePath.'/message/?sessionId='.$sessionId;

        return $this->sse->createSseResponse(function () use ($sessionId, $postEndpoint) {
            $this->sessionManager->registerConnection($sessionId);

            $this->sse->sendEvent($postEndpoint, 'endpoint');
            $this->sse->sendDebug('Connection established');

            $heartbeatInterval = 30;
            $lastHeartbeat = time();
            $lastMessageId = -1;

            register_shutdown_function(function () use ($sessionId) {
                $this->sessionManager->removeConnection($sessionId);
            });

            $connectionStatus = connection_status();

            while ($connectionStatus === CONNECTION_NORMAL) {
                $messages = $this->sessionManager->getMessages($sessionId, $lastMessageId);

                foreach ($messages as $message) {
                    if (is_array($message) && isset($message['id'], $message['data']) && is_int($message['id'])) {
                        $lastMessageId = $message['id'];

                        $this->sse->sendEvent($message['data']);
                    }
                }

                if ((time() - $lastHeartbeat) >= $heartbeatInterval) {
                    $this->sse->sendPing();
                    $lastHeartbeat = time();
                }

                $oldStatus = $connectionStatus;
                $connectionStatus = connection_status();

                usleep(100000);
            }

            $this->sessionManager->removeConnection($sessionId);
        });
    }

    /**
     * Handle POST messages from client
     */
    public function message(Request $request): JsonResponse
    {
        $requestData = $request->all();
        $sessionId = $request->query('sessionId');

        if ($sessionId === null || ! $this->sessionManager->sessionExists($sessionId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Session not found or expired',
            ], 404);
        }

        try {
            $response = $this->mcpHandler->handle($requestData);

            $success = $this->sessionManager->sendToClient((string) $sessionId, $response);

            if (! $success) {
                Log::warning("Failed to store message for client: {$sessionId}");
            }

            return response()->json([
                'status' => 'received',
                'message' => 'Request received and being processed',
            ]);
        } catch (\Exception $e) {
            Log::error("Error processing MCP message: {$e->getMessage()}", [
                'sessionId' => $sessionId,
                'request' => $requestData,
                'exception' => $e,
            ]);

            $errorResponse = [
                'jsonrpc' => '2.0',
                'id' => $requestData['id'] ?? null,
                'error' => [
                    'code' => -32000,
                    'message' => $e->getMessage(),
                ],
            ];
            $this->sessionManager->sendToClient((string) $sessionId, $errorResponse);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
