<?php

namespace Kirschbaum\Loop\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Kirschbaum\Loop\McpHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Kirschbaum\Loop\Services\SseService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class McpController extends Controller
{
    public function __construct(protected McpHandler $mcpHandler, protected SseService $sseService)
    {
    }

    /**
     * Handle MCP requests.
     */
    public function __invoke(Request $request): JsonResponse|StreamedResponse|Response
    {
        if ($request->isMethod('GET')) {
            return $this->handleGetRequest($request);
        }

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
     * @param  array<array-key, mixed>  $messages  JSON-RPC messages from the client
     */
    protected function handlePostSseResponse(array $messages): StreamedResponse {
        $response = $this->sseService->createPostSseResponse(
            $messages,
            fn ($message) => $this->mcpHandler->handle($message),
        );

        return $response;
    }

    /**
     * Handle JSON response for POST requests.
     *
     * @param  array<array-key, mixed>  $messages  JSON-RPC messages from the client
     */
    protected function handlePostJsonResponse(array $messages): JsonResponse
    {
        $response = $this->mcpHandler->handle($messages);

        return response()->json($response);
    }

    /**
     * Handle GET requests for backwards compatibility with older clients
     * expecting an SSE stream for server-initiated messages.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    protected function handleGetRequest(Request $request): StreamedResponse|JsonResponse|Response
    {
        if (strpos($request->header('Accept', ''), 'text/event-stream') === false) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => [
                    // Using a generic error code; consult JSON-RPC spec for specific codes if necessary
                    'code' => -32000,
                    'message' => 'Not Acceptable: Client must include "Accept: text/event-stream" for this endpoint.',
                ],
            ], 406);
        }

        // Placeholder for the "endpoint event" data structure.
        // Adjust this to match what older clients expect.
        $endpointEventData = [
            'status' => 'connected',
            'transport_protocol' => 'http_sse_deprecated_2024_11_05',
            'message' => 'SSE connection established for server-initiated messages.',
        ];

        return new StreamedResponse(function () use ($endpointEventData) {
            // Send the initial "endpoint event"
            // The event name 'endpoint' is a placeholder; adjust if old clients expect a different name.
            echo 'event: endpoint
';
            echo 'data: ' . json_encode($endpointEventData) . '

';
            flush();

            // This loop maintains the connection and sends heartbeats.
            // In a production environment, this section would need to integrate
            // with an event bus or message queue to push actual application events
            // to the client.
            while (true) {
                if (connection_aborted()) {
                    break;
                }

                // Send an SSE comment as a heartbeat to keep the connection alive
                // and help with proxy buffering.
                echo ': heartbeat

';
                flush();

                // Pause before sending the next heartbeat.
                // The frequency of heartbeats can be adjusted.
                sleep(15);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no', // Important for Nginx and other reverse proxies
            'Connection' => 'keep-alive',
        ]);
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
     *
     * @param mixed $acceptsJson
     * @param mixed $acceptsEventStream
     */
    protected function clientPrefersJson($acceptsJson, $acceptsEventStream): bool
    {
        return $acceptsJson && $acceptsEventStream === false;
    }

    /**
     * Check if an item is a JSON-RPC request.
     *
     * @param mixed $item
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
