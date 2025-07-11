<?php

declare(strict_types=1);

use Kirschbaum\Loop\Contracts\Tool;
use Kirschbaum\Loop\Contracts\Toolkit;
use Kirschbaum\Loop\Loop;
use Kirschbaum\Loop\LoopTools;

test('addTool returns Loop instance for fluent interface', function () {
    $mockLoopTools = Mockery::mock(LoopTools::class);
    $mockTool = Mockery::mock(Tool::class);

    $mockLoopTools->shouldReceive('addTool')
        ->once()
        ->with($mockTool)
        ->andReturn();

    $loop = new Loop($mockLoopTools);
    $result = $loop->addTool($mockTool);

    expect($result)->toBe($loop);
});

test('removeTool returns Loop instance for fluent interface', function () {
    $mockLoopTools = Mockery::mock(LoopTools::class);

    $mockLoopTools->shouldReceive('removeTool')
        ->once()
        ->with('test-tool')
        ->andReturn(true);

    $loop = new Loop($mockLoopTools);
    $result = $loop->removeTool('test-tool');

    expect($result)->toBe($loop);
});

test('clear returns Loop instance for fluent interface', function () {
    $mockLoopTools = Mockery::mock(LoopTools::class);

    $mockLoopTools->shouldReceive('clear')
        ->once()
        ->andReturn();

    $loop = new Loop($mockLoopTools);
    $result = $loop->clear();

    expect($result)->toBe($loop);
});

test('addTool correctly proxies to LoopTools', function () {
    $mockLoopTools = Mockery::mock(LoopTools::class);
    $mockTool = Mockery::mock(Tool::class);

    $mockLoopTools->shouldReceive('addTool')
        ->once()
        ->with($mockTool)
        ->andReturn();

    $loop = new Loop($mockLoopTools);
    $loop->addTool($mockTool);
});

test('removeTool correctly proxies to LoopTools', function () {
    $mockLoopTools = Mockery::mock(LoopTools::class);

    $mockLoopTools->shouldReceive('removeTool')
        ->once()
        ->with('target-tool')
        ->andReturn(true);

    $loop = new Loop($mockLoopTools);
    $loop->removeTool('target-tool');
});

test('clear correctly proxies to LoopTools', function () {
    $mockLoopTools = Mockery::mock(LoopTools::class);

    $mockLoopTools->shouldReceive('clear')
        ->once()
        ->andReturn();

    $loop = new Loop($mockLoopTools);
    $loop->clear();
});

test('method chaining works with fluent interface', function () {
    $mockLoopTools = Mockery::mock(LoopTools::class);
    $mockTool1 = Mockery::mock(Tool::class);
    $mockTool2 = Mockery::mock(Tool::class);

    $mockLoopTools->shouldReceive('addTool')
        ->twice()
        ->andReturn();

    $mockLoopTools->shouldReceive('removeTool')
        ->once()
        ->with('unwanted-tool')
        ->andReturn(true);

    $loop = new Loop($mockLoopTools);

    $result = $loop->addTool($mockTool1)
        ->addTool($mockTool2)
        ->removeTool('unwanted-tool');

    expect($result)->toBe($loop);
});

test('fluent interface integrates with existing methods', function () {
    $mockLoopTools = Mockery::mock(LoopTools::class);
    $mockTool = Mockery::mock(Tool::class);
    $mockToolkit = Mockery::mock(Toolkit::class);

    $mockLoopTools->shouldReceive('registerTool')
        ->once()
        ->with($mockTool)
        ->andReturn();

    $mockLoopTools->shouldReceive('registerToolkit')
        ->once()
        ->with($mockToolkit)
        ->andReturn();

    $mockLoopTools->shouldReceive('addTool')
        ->once()
        ->andReturn();

    $mockLoopTools->shouldReceive('removeTool')
        ->once()
        ->with('temp-tool')
        ->andReturn(true);

    $loop = new Loop($mockLoopTools);

    $result = $loop->tool($mockTool)
        ->toolkit($mockToolkit)
        ->context('Test context')
        ->addTool($mockTool)
        ->removeTool('temp-tool');

    expect($result)->toBe($loop);
});

test('existing tool method returns Loop instance for backward compatibility', function () {
    $mockLoopTools = Mockery::mock(LoopTools::class);
    $mockTool = Mockery::mock(Tool::class);

    $mockLoopTools->shouldReceive('registerTool')
        ->once()
        ->with($mockTool)
        ->andReturn();

    $loop = new Loop($mockLoopTools);
    $result = $loop->tool($mockTool);

    expect($result)->toBe($loop);
});

test('existing toolkit method returns Loop instance for backward compatibility', function () {
    $mockLoopTools = Mockery::mock(LoopTools::class);
    $mockToolkit = Mockery::mock(Toolkit::class);

    $mockLoopTools->shouldReceive('registerToolkit')
        ->once()
        ->with($mockToolkit)
        ->andReturn();

    $loop = new Loop($mockLoopTools);
    $result = $loop->toolkit($mockToolkit);

    expect($result)->toBe($loop);
});

test('context method returns Loop instance for fluent chaining', function () {
    $mockLoopTools = Mockery::mock(LoopTools::class);

    $loop = new Loop($mockLoopTools);
    $result = $loop->context('Additional context');

    expect($result)->toBe($loop);
});

test('complex fluent chain with all methods works correctly', function () {
    $mockLoopTools = Mockery::mock(LoopTools::class);
    $mockTool1 = Mockery::mock(Tool::class);
    $mockTool2 = Mockery::mock(Tool::class);
    $mockToolkit = Mockery::mock(Toolkit::class);

    $mockLoopTools->shouldReceive('registerTool')
        ->once()
        ->with($mockTool1)
        ->andReturn();

    $mockLoopTools->shouldReceive('registerToolkit')
        ->once()
        ->with($mockToolkit)
        ->andReturn();

    $mockLoopTools->shouldReceive('addTool')
        ->once()
        ->with($mockTool2)
        ->andReturn();

    $mockLoopTools->shouldReceive('removeTool')
        ->once()
        ->with('old-tool')
        ->andReturn(true);

    $mockLoopTools->shouldReceive('clear')
        ->once()
        ->andReturn();

    $loop = new Loop($mockLoopTools);

    $result = $loop->context('Starting workflow')
        ->tool($mockTool1)
        ->toolkit($mockToolkit)
        ->addTool($mockTool2)
        ->context('Removing old tool')
        ->removeTool('old-tool')
        ->context('Clearing everything')
        ->clear();

    expect($result)->toBe($loop);
});
