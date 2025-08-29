<?php

use Kirschbaum\Loop\Loop;
use Kirschbaum\Loop\LoopTools;
use Kirschbaum\Loop\Tools\CustomTool;

beforeEach(function () {
    app()->forgetInstance(Loop::class);
});

it('can add tools dynamically', function () {
    $loop = app(Loop::class);
    $tool = CustomTool::make('test-tool', 'Test tool');

    $result = $loop->tool($tool);

    expect($result)
        ->toBe($loop)
        ->and($loop->getPrismTools())
        ->toHaveCount(1);
});

it('can remove tools dynamically', function () {
    $loop = app(Loop::class);
    $tool = CustomTool::make('test-tool', 'Test tool');

    $loop->tool($tool);
    expect($loop->getPrismTools())->toHaveCount(1);

    $result = $loop->removeTool('test-tool');

    expect($result)
        ->toBe($loop)
        ->and($loop->getPrismTools())
        ->toHaveCount(0);
});

it('can clear all tools', function () {
    $loop = app(Loop::class);
    $tool1 = CustomTool::make('tool-1', 'Tool 1');
    $tool2 = CustomTool::make('tool-2', 'Tool 2');

    $loop->tool($tool1)->tool($tool2);
    expect($loop->getPrismTools())->toHaveCount(2);

    $result = $loop->clear();

    expect($result)
        ->toBe($loop)
        ->and($loop->getPrismTools())
        ->toHaveCount(0);
});

it('supports method chaining', function () {
    $loop = app(Loop::class);
    $tool1 = CustomTool::make('tool-1', 'Tool 1');
    $tool2 = CustomTool::make('tool-2', 'Tool 2');

    $result = $loop
        ->tool($tool1)
        ->tool($tool2)
        ->removeTool('tool-1');

    expect($result)
        ->toBe($loop)
        ->and($loop->getPrismTools())
        ->toHaveCount(1);
});

it('can register a callback for tool changes', function () {
    $loopTools = new LoopTools;
    $callbackCalled = false;

    $loopTools->onToolsChanged(function () use (&$callbackCalled) {
        $callbackCalled = true;
    });

    $tool = CustomTool::make('test-tool', 'Test tool');

    $loopTools->registerTool($tool);

    expect($callbackCalled)->toBeTrue();
});

it('calls callback only when tools actually change', function () {
    $loopTools = new LoopTools;
    $callbackCount = 0;

    $loopTools->onToolsChanged(function () use (&$callbackCount) {
        $callbackCount++;
    });

    $tool = CustomTool::make('test-tool', 'Test tool');

    $loopTools->registerTool($tool);
    expect($callbackCount)->toBe(1);

    $loopTools->registerTool($tool);
    expect($callbackCount)->toBe(1);
});

it('calls callback when tool is removed', function () {
    $loopTools = new LoopTools;
    $callbackCalled = false;

    $tool = CustomTool::make('test-tool', 'Test tool');
    $loopTools->registerTool($tool);

    $loopTools->onToolsChanged(function () use (&$callbackCalled) {
        $callbackCalled = true;
    });

    $loopTools->removeTool('test-tool');

    expect($callbackCalled)->toBeTrue();
});

it('does not call callback when removing non-existent tool', function () {
    $loopTools = new LoopTools;
    $callbackCalled = false;

    $loopTools->onToolsChanged(function () use (&$callbackCalled) {
        $callbackCalled = true;
    });

    $loopTools->removeTool('non-existent-tool');

    expect($callbackCalled)->toBeFalse();
});

it('calls callback when clearing tools', function () {
    $loopTools = new LoopTools;
    $callbackCalled = false;

    $tool = CustomTool::make('test-tool', 'Test tool');
    $loopTools->registerTool($tool);

    $loopTools->onToolsChanged(function () use (&$callbackCalled) {
        $callbackCalled = true;
    });

    $loopTools->clear();

    expect($callbackCalled)->toBeTrue();
});

it('calls callback when registering new tool', function () {
    $loopTools = new LoopTools;
    $callbackCalled = false;

    $loopTools->onToolsChanged(function () use (&$callbackCalled) {
        $callbackCalled = true;
    });

    $tool = CustomTool::make('test-tool', 'Test tool');

    $loopTools->registerTool($tool);

    expect($callbackCalled)->toBeTrue();
});

it('uses hash-based change detection', function () {
    $loopTools = new LoopTools;
    $callbackCount = 0;

    $loopTools->onToolsChanged(function () use (&$callbackCount) {
        $callbackCount++;
    });

    $tool1 = CustomTool::make('tool-1', 'Tool 1');
    $tool2 = CustomTool::make('tool-2', 'Tool 2');

    $loopTools->registerTool($tool1);
    expect($callbackCount)->toBe(1);

    $loopTools->registerTool($tool2);
    expect($callbackCount)->toBe(2);

    $loopTools->removeTool('tool-1');
    expect($callbackCount)->toBe(3);

    $loopTools->removeTool('tool-2');
    expect($callbackCount)->toBe(4);
});
