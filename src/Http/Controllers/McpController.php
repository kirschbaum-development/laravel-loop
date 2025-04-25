<?php

namespace Kirschbaum\Loop\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kirschbaum\Loop\McpHandler;

class McpController extends Controller
{
    public function __invoke(Request $request, McpHandler $mcpHandler)
    {
        $message = $request->all();

        return response()->json($mcpHandler->handle($message));
    }
}
