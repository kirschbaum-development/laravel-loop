<?php

namespace Kirschbaum\Loop\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kirschbaum\Loop\McpHandler;

class McpController extends Controller
{
    public function __invoke(Request $request, McpHandler $mcpHandler): JsonResponse
    {
        $message = $request->all();

        return response()->json($mcpHandler->handle($message));
    }
}
