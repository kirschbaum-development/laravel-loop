<?php

namespace Kirschbaum\Loop\Http\Controllers;

use Illuminate\Http\Request;
use Kirschbaum\Loop\McpHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Kirschbaum\Loop\Enums\ErrorCode;
use Kirschbaum\Loop\Enums\MessageType;

class McpController extends Controller
{
    public function __invoke(Request $request, McpHandler $mcpHandler)
    {
        $message = $request->all();

        return response()->json($mcpHandler->handle($message));
    }
}
