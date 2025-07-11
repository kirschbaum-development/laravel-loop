<?php

declare(strict_types=1);

use Kirschbaum\Loop\Contracts\Tool;
use Kirschbaum\Loop\Facades\Loop as LoopFacade;
use Kirschbaum\Loop\Loop;
use Kirschbaum\Loop\LoopTools;
use Kirschbaum\Loop\Tools\CustomTool;

beforeEach(function () {
    LoopFacade::clearResolvedInstances();
    app()->forgetInstance(Loop::class);

    if (app()->bound(LoopTools::class)) {
        app(LoopTools::class)->clear();
    }
});

/*
 * Integration Tests for Dynamic Tool Management
 *
 * These tests demonstrate real-world usage patterns described in the PRD:
 * 1. Feature Toggle via HTTP Controller
 * 2. Administrative Tool Management
 * 3. Permission-Based Tool Access
 */

test('Feature Toggle Scenario: Enable Stripe Integration via HTTP Controller', function () {
    // Simulate the enableStripeIntegration controller method from PRD

    // Initial state - no tools registered
    $loop = app(Loop::class);
    expect($loop->getPrismTools())->toHaveCount(0);

    // Simulate user enabling integration in web UI
    // This would normally happen in a controller after authorization
    $stripeEnabled = true; // Simulate user setting

    if ($stripeEnabled) {
        // Add Stripe tools dynamically (as mentioned in PRD example)
        $stripeTool = CustomTool::make('stripe', 'Stripe payment processing tool')
            ->using(fn () => 'Stripe payment processed');
        $loop->addTool($stripeTool);

        // Simulate updating user settings (would normally be in database)
        $userStripeEnabled = true;
    }

    // Verify Stripe tool is now available for MCP requests
    $tools = $loop->getPrismTools();
    expect($tools)->toHaveCount(1);

    $toolNames = $tools->map(fn ($tool) => $tool->name())->toArray();
    expect($toolNames)->toContain('stripe');

    // Simulate subsequent MCP tools/list request reflecting the change
    $mcpToolsList = $loop->getPrismTools()->map(fn ($tool) => [
        'name' => $tool->name(),
        'description' => $tool->description(),
    ])->toArray();

    expect($mcpToolsList)->toHaveCount(1);
    expect($mcpToolsList[0]['name'])->toBe('stripe');
});

test('Administrative Tool Management: Bulk Disable Maintenance Tools', function () {
    // Simulate the Artisan command scenario from PRD

    $loop = app(Loop::class);

    // Initial setup - add maintenance tools that would typically be available
    $maintenanceTools = [
        CustomTool::make('database-debug', 'Database debugging tool')->using(fn () => 'debug info'),
        CustomTool::make('cache-inspector', 'Cache inspection tool')->using(fn () => 'cache info'),
        CustomTool::make('log-viewer', 'Log viewing tool')->using(fn () => 'log info'),
        CustomTool::make('system-monitor', 'System monitoring tool')->using(fn () => 'system info'),
    ];

    foreach ($maintenanceTools as $tool) {
        $loop->addTool($tool);
    }

    // Verify all maintenance tools are available
    expect($loop->getPrismTools())->toHaveCount(4);

    // Simulate artisan command with --disable-maintenance-tools option
    $disableMaintenanceTools = true; // Command line option

    if ($disableMaintenanceTools) {
        // Use fluent interface for bulk removal (as shown in PRD)
        $loop->removeTool('database-debug')
            ->removeTool('cache-inspector')
            ->removeTool('log-viewer');
    }

    // Verify maintenance tools were removed, but system-monitor remains
    expect($loop->getPrismTools())->toHaveCount(1);

    $remainingTools = $loop->getPrismTools()->map(fn ($tool) => $tool->name())->toArray();
    expect($remainingTools)->toContain('system-monitor');
    expect($remainingTools)->not->toContain('database-debug');
    expect($remainingTools)->not->toContain('cache-inspector');
    expect($remainingTools)->not->toContain('log-viewer');

    // Simulate MCP client checking tools/list after command execution
    $postCommandToolsList = $loop->getPrismTools()->map(fn ($tool) => $tool->name())->toArray();
    expect($postCommandToolsList)->toHaveCount(1);
});

test('Permission-Based Tool Access: Middleware Adjusts Available Tools', function () {
    // Simulate the middleware scenario from PRD

    $loop = app(Loop::class);

    // Create tools that should only be available to admins
    $adminDebugTool = CustomTool::make('admin-debug', 'Administrative debugging tool')
        ->using(fn () => 'admin debug info');
    $systemMonitorTool = CustomTool::make('system-monitor', 'System monitoring tool')
        ->using(fn () => 'system monitor info');

    // Create tools available to all users
    $publicTool = CustomTool::make('public-tool', 'Public tool')
        ->using(fn () => 'public info');

    // Add public tool that's always available
    $loop->addTool($publicTool);

    // Simulate middleware processing for admin user
    $userIsAdmin = true; // Simulate auth()->user()->isAdmin()

    if ($userIsAdmin) {
        $loop->addTool($adminDebugTool)
            ->addTool($systemMonitorTool);
    }

    // Verify admin user sees all tools
    expect($loop->getPrismTools())->toHaveCount(3);

    $adminToolNames = $loop->getPrismTools()->map(fn ($tool) => $tool->name())->toArray();
    expect($adminToolNames)->toContain('admin-debug');
    expect($adminToolNames)->toContain('system-monitor');
    expect($adminToolNames)->toContain('public-tool');

    // Simulate new request for non-admin user (fresh Loop instance)
    app()->forgetInstance(Loop::class);
    if (app()->bound(LoopTools::class)) {
        app(LoopTools::class)->clear();
    }

    $loop = app(Loop::class);

    // Add public tool again
    $loop->addTool($publicTool);

    // Simulate middleware processing for non-admin user
    $userIsAdmin = false; // Simulate auth()->user()->isAdmin()

    if ($userIsAdmin) {
        $loop->addTool($adminDebugTool)
            ->addTool($systemMonitorTool);
    }

    // Verify non-admin user only sees public tools
    expect($loop->getPrismTools())->toHaveCount(1);

    $nonAdminToolNames = $loop->getPrismTools()->map(fn ($tool) => $tool->name())->toArray();
    expect($nonAdminToolNames)->toContain('public-tool');
    expect($nonAdminToolNames)->not->toContain('admin-debug');
    expect($nonAdminToolNames)->not->toContain('system-monitor');
});

test('Complex Workflow: Feature Development with Dynamic Tool Management', function () {
    // Simulate a complete development workflow using dynamic tool management

    $loop = app(Loop::class);

    // Phase 1: Development setup
    $developmentTools = [
        CustomTool::make('dev-logger', 'Development logger')->using(fn () => 'dev logs'),
        CustomTool::make('api-tester', 'API testing tool')->using(fn () => 'api test'),
    ];

    foreach ($developmentTools as $tool) {
        $loop->addTool($tool);
    }

    expect($loop->getPrismTools())->toHaveCount(2);

    // Phase 2: Add feature-specific tools
    $featureTools = [
        CustomTool::make('feature-validator', 'Feature validation tool')->using(fn () => 'validation'),
        CustomTool::make('data-migrator', 'Data migration tool')->using(fn () => 'migration'),
    ];

    // Use fluent interface for adding multiple tools
    $loop->addTool($featureTools[0])
        ->addTool($featureTools[1]);

    expect($loop->getPrismTools())->toHaveCount(4);

    // Phase 3: Remove temporary development tools before deployment
    $loop->removeTool('dev-logger')
        ->removeTool('api-tester');

    expect($loop->getPrismTools())->toHaveCount(2);

    $finalTools = $loop->getPrismTools()->map(fn ($tool) => $tool->name())->toArray();
    expect($finalTools)->toContain('feature-validator');
    expect($finalTools)->toContain('data-migrator');
    expect($finalTools)->not->toContain('dev-logger');
    expect($finalTools)->not->toContain('api-tester');

    // Phase 4: Production deployment - clear all and add only production tools
    $loop->clear();

    $productionTool = CustomTool::make('monitoring', 'Production monitoring')->using(fn () => 'monitor');
    $loop->addTool($productionTool);

    expect($loop->getPrismTools())->toHaveCount(1);

    $productionTools = $loop->getPrismTools()->map(fn ($tool) => $tool->name())->toArray();
    expect($productionTools)->toContain('monitoring');
});

test('MCP Client Tool Discovery: Tools List Reflects Runtime Changes', function () {
    // Simulate how MCP client would discover tools after runtime changes

    $loop = app(Loop::class);

    // Initial MCP tools/list request - should return empty
    $initialToolsList = $loop->getPrismTools()->map(fn ($tool) => [
        'name' => $tool->name(),
        'description' => $tool->description(),
    ])->toArray();

    expect($initialToolsList)->toHaveCount(0);

    // Runtime tool addition
    $dynamicTool = CustomTool::make('dynamic-feature', 'Dynamically added feature')
        ->using(fn () => 'dynamic response');

    $loop->addTool($dynamicTool);

    // Subsequent MCP tools/list request - should include new tool
    $updatedToolsList = $loop->getPrismTools()->map(fn ($tool) => [
        'name' => $tool->name(),
        'description' => $tool->description(),
    ])->toArray();

    expect($updatedToolsList)->toHaveCount(1);
    expect($updatedToolsList[0]['name'])->toBe('dynamic-feature');
    expect($updatedToolsList[0]['description'])->toBe('Dynamically added feature');

    // Runtime tool removal
    $loop->removeTool('dynamic-feature');

    // Final MCP tools/list request - should be empty again
    $finalToolsList = $loop->getPrismTools()->map(fn ($tool) => [
        'name' => $tool->name(),
        'description' => $tool->description(),
    ])->toArray();

    expect($finalToolsList)->toHaveCount(0);
});

test('Error Handling: Graceful Handling of Tool Management Operations', function () {
    // Test error handling and edge cases in dynamic tool management

    $loop = app(Loop::class);

    // Test duplicate tool handling
    $tool1 = CustomTool::make('duplicate-test', 'First instance')->using(fn () => 'first');
    $tool2 = CustomTool::make('duplicate-test', 'Second instance')->using(fn () => 'second');

    $loop->addTool($tool1);
    $loop->addTool($tool2); // Should be ignored due to duplicate name

    expect($loop->getPrismTools())->toHaveCount(1);

    // Test removing non-existent tool (should not cause errors)
    $result = $loop->removeTool('non-existent-tool'); // Should complete without error
    expect($loop->getPrismTools())->toHaveCount(1); // Count should remain the same

    // Test clearing empty registry (should not cause errors)
    $loop->clear();
    expect($loop->getPrismTools())->toHaveCount(0);

    $loop->clear(); // Clear again on empty registry
    expect($loop->getPrismTools())->toHaveCount(0);

    // Test chaining with mixed operations
    $validTool = CustomTool::make('valid-tool', 'Valid tool')->using(fn () => 'valid');

    $loop->addTool($validTool)
        ->removeTool('non-existent') // Should not break the chain
        ->addTool($validTool) // Duplicate, should be ignored
        ->removeTool('valid-tool'); // Should work

    expect($loop->getPrismTools())->toHaveCount(0);
});
