<?php

namespace Kirschbaum\Loop\Http\Controllers;

use Kirschbaum\Loop\Loop;
use Illuminate\Http\Request;
use Kirschbaum\Loop\McpHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Kirschbaum\Loop\Enums\ErrorCode;
use Illuminate\Support\Facades\Cache;
use Kirschbaum\Loop\Enums\MessageType;
use Kirschbaum\Loop\Http\Requests\AskRequest;
use Prism\Prism\Text\Response as PrismResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class McpController extends Controller
{
    public function __invoke(Request $request, McpHandler $mcpHandler)
    {
        info('MCP request received', ['request' => $request->all()]);
        $message = $request->all();

        // if (!isset($message['jsonrpc']) || $message['jsonrpc'] !== '2.0') {
        //     return $this->errorResponse(
        //         null,
        //         ErrorCode::INVALID_REQUEST,
        //         'Invalid JSON-RPC version'
        //     );
        // }

        if (!isset($message['method']) || !is_string($message['method'])) {
            return $this->errorResponse(
                $message['id'] ?? null,
                ErrorCode::INVALID_REQUEST,
                'Missing or invalid method'
            );
        }

        // Determine message type
        $method = $message['method'];
        $params = $message['params'] ?? [];
        $id = $message['id'] ?? null;

        $messageType = isset($message['id'])
            ? MessageType::REQUEST
            : MessageType::REQUEST;

        $message = $mcpHandler->processMessage(
            messageType: $messageType,
            method: $method,
            params: $params,
            id: $id,
            request: $request
        );

        dump($message);

        return $this->successResponse($id, $message);
    }

    protected function successResponse($id, $message)
    {
        info('MCP response', ['id' => $id, 'message' => $message]);

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $message,
        ]);

        // Set the appropriate headers for SSE
        $response = new StreamedResponse(function () use ($id, $message) {
            while (true) {
                // Your server-side logic to get data
                // $data = json_encode([
                //     'jsonrpc' => '2.0',
                //     'id' => $id,
                //     'result' => $message,
                // ]);

                echo "data: " . json_encode($message) . "\n\n";

                // Flush the output buffer
                // ob_flush();
                flush();

                // Delay for 1 second
                sleep(1);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }

    protected function errorResponse($id, int $code, string $message, $data = null): JsonResponse
    {
        $response = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($data !== null) {
            $response['error']['data'] = $data;
        }

        return response()->json($response, 200);
    }
}
