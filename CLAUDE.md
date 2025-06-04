# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Laravel Loop Package

Laravel Loop is a Model Context Protocol (MCP) server package for Laravel applications that enables AI assistants to interact with Laravel apps through multiple transport protocols (STDIO, Streamable HTTP, SSE). Uses Prism PHP for tool building.

## Build/Test Commands
- **Run tests**: `composer test`
- **Code formatting**: `composer lint` (Laravel Pint)
- **Static analysis**: `composer analyse` (Larastan)
- **All quality checks**: `composer check` (lint + analyse + test)
- **Run artisan commands**: `vendor/bin/testbench`
- **MCP server (STDIO)**: `php artisan loop:mcp:start [--user-id=1] [--debug]`
- **Generate MCP config**: `php artisan loop:mcp:config`
- **Development server**: `composer serve` (testbench workbench)

## Architecture

**Core Components:**
- `Loop` - Main service class for managing tools/toolkits
- `McpHandler` - Handles MCP JSON-RPC protocol messages  
- `Tool` interface - Contract for individual tools (implements Prism\Tool)
- `Toolkit` interface - Contract for tool collections
- `CustomTool` - Flexible tool builder for custom functionality

**Transport Layer:**
- STDIO transport via Artisan command using ReactPHP event loop
- Streamable HTTP transport (`/mcp` endpoint)
- SSE transport (`/mcp/sse` endpoint, deprecated)

**Design Patterns:**
- All classes use `Makeable` trait for static factory methods
- Contract-based architecture with clear interfaces
- `ToolCollection` for managing groups of tools
- Facade pattern (`Loop::tool()`, `Loop::toolkit()`)

## Development Guidelines

**Tool Development:**
- Tools implement `Tool` contract with `build()` and `getName()` methods
- Use Prism's fluent API for parameter definition and tool configuration
- Group related tools in Toolkits that return `ToolCollection`
- Register tools in service providers using `Loop::tool()` or `Loop::toolkit()`

**Code Style:**
- Uses Pest testing framework with Orchestra Testbench
- Strict typing with `declare(strict_types=1)`
- PSR-12 compliant via Laravel Pint
- Type hints required for parameters and return types

**Testing:**
- Extends `Tests\TestCase` (Orchestra Testbench)
- Uses workbench setup for package development
- Lazy database refresh for performance
- Mock utilities for dependency injection

**Security:**
- HTTP endpoints protected by Sanctum middleware by default
- User impersonation support in STDIO mode via `--user-id` flag
- Debug mode available for development
- SSE sessions managed with file/Redis drivers

## Configuration

Key config options in `config/loop.php`:
- Transport enablement (`LOOP_STREAMABLE_HTTP_ENABLED`, `LOOP_SSE_ENABLED`)
- Endpoint paths and middleware configuration
- SSE driver selection (file/redis) with TTL settings
- Authentication middleware (defaults to `auth:sanctum`)