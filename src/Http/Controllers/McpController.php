<?php

namespace Kirschbaum\Loop\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Kirschbaum\Loop\McpHandler;
use Kirschbaum\Loop\Services\SseService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class McpController extends Controller
{
    public function __construct(protected McpHandler $mcpHandler, protected SseService $sseService) {}

    /**
     * Handle MCP requests.
     */
    public function __invoke(Request $request): JsonResponse|StreamedResponse|Response
    {
        $acceptsEventStream = strpos($request->header('Accept', ''), 'text/event-stream') !== false;
        $acceptsJson = strpos($request->header('Accept', ''), 'application/json') !== false;

        if (! $acceptsEventStream && ! $acceptsJson) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => [
                    'message' => 'Not Acceptable: Client must accept text/event-stream or application/json',
                ],
            ], 406);
        }

        $requestData = $request->all();
        $containsRequests = $this->containsJsonRpcRequests($requestData);

        if (! $containsRequests) {
            $this->mcpHandler->handle($requestData);

            return response('', 202);
        }

        if ($this->clientPrefersJson($acceptsJson, $acceptsEventStream)) {
            return $this->handlePostJsonResponse($requestData);
        }

        return $this->handlePostSseResponse($requestData);
    }

    /**
     * Handle SSE response for POST requests.
     *
     * @param  array  $messages  JSON-RPC messages from the client
     */
    protected function handlePostSseResponse(
        array $messages
    ): StreamedResponse {
        $response = $this->sseService->createPostSseResponse(
            $messages,
            fn ($message) => $this->mcpHandler->handle($message),
        );

        return $response;
    }

    /**
     * Handle JSON response for POST requests.
     */
    protected function handlePostJsonResponse(array $messages): JsonResponse
    {
        $response = $this->mcpHandler->handle($messages);

        return response()->json($response);
    }

    /**
     * Handle GET requests (server-initiated messages) according to MCP specification.
     */
    protected function handleGetRequest(Request $request)
    {
        // TODO
    }

    /**
     * Check if the input contains any JSON-RPC requests.
     *
     * @param  mixed  $input
     */
    protected function containsJsonRpcRequests($input): bool
    {
        // If input is an array with numeric keys, it's a batch
        if (is_array($input) && array_keys($input) === range(0, count($input) - 1)) {
            foreach ($input as $item) {
                if ($this->isJsonRpcRequest($item)) {
                    return true;
                }
            }

            return false;
        }

        return $this->isJsonRpcRequest($input);
    }

    /**
     * Check if the client prefers JSON over SSE.
     */
    protected function clientPrefersJson($acceptsJson, $acceptsEventStream): bool
    {
        return $acceptsJson && $acceptsEventStream === false;
    }

    /**
     * Check if an item is a JSON-RPC request.
     */
    protected function isJsonRpcRequest($item): bool
    {
        return is_array($item) &&
               isset($item['jsonrpc']) &&
               $item['jsonrpc'] === '2.0' &&
               isset($item['method']) &&
               isset($item['id']);
    }
}
