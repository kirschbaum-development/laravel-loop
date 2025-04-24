<?php

namespace Kirschbaum\Loop\Enums;

/**
 * Error codes defined by the JSON-RPC specification and MCP protocol.
 */
class ErrorCode
{
    // SDK error codes
    public const CONNECTION_CLOSED = -32000;

    public const REQUEST_TIMEOUT = -32001;

    // Standard JSON-RPC error codes
    public const PARSE_ERROR = -32700;

    public const INVALID_REQUEST = -32600;

    public const METHOD_NOT_FOUND = -32601;

    public const INVALID_PARAMS = -32602;

    public const INTERNAL_ERROR = -32603;
}
