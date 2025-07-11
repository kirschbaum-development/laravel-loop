<?php

declare(strict_types=1);

use Kirschbaum\Loop\Facades\Loop as LoopFacade;
use Kirschbaum\Loop\Loop;
use Kirschbaum\Loop\LoopTools;
use Kirschbaum\Loop\Toolkits\LaravelModelToolkit;
use Kirschbaum\Loop\Tools\CustomTool;
use Workbench\App\Models\User;

beforeEach(function () {
    LoopFacade::clearResolvedInstances();

    app()->forgetInstance(Loop::class);

    if (app()->bound(LoopTools::class)) {
        app(LoopTools::class)->clear();
    }
});

test('tools persist across loop instances', function () {
    $tool = CustomTool::make('test_tool', 'A test tool')
        ->using(fn () => 'Test tool response');

    LoopFacade::tool($tool);

    $loop1 = app(Loop::class);
    $tools1 = $loop1->getPrismTools();

    expect($tools1)
        ->toHaveCount(1)
        ->and($tools1->first()->name())
        ->toEqual('test_tool');

    app()->forgetInstance(Loop::class);

    $loop2 = app(Loop::class);
    $tools2 = $loop2->getPrismTools();

    expect($tools2)
        ->toHaveCount(1)
        ->and($tools2->first()->name())
        ->toEqual('test_tool');
});

test('toolkit registrations persist across loop instances', function () {
    LoopFacade::toolkit(LaravelModelToolkit::make([User::class]));

    $loop1 = app(Loop::class);
    $tools1 = $loop1->getPrismTools();

    expect($tools1->count())
        ->toBeGreaterThan(0);

    app()->forgetInstance(Loop::class);

    $loop2 = app(Loop::class);
    $tools2 = $loop2->getPrismTools();

    expect($tools2->count())
        ->toEqual($tools1->count());
});

test('multiple tool registrations persist', function () {
    LoopFacade::tool(CustomTool::make('tool1', 'Tool 1')->using(fn () => 'Response 1'));
    LoopFacade::tool(CustomTool::make('tool2', 'Tool 2')->using(fn () => 'Response 2'));

    $loop1 = app(Loop::class);

    expect($loop1->getPrismTools())
        ->toHaveCount(2);

    app()->forgetInstance(Loop::class);

    $loop2 = app(Loop::class);

    expect($loop2->getPrismTools())
        ->toHaveCount(2);
});

test('loop tools registry is a singleton', function () {
    $registry1 = app(LoopTools::class);
    $registry2 = app(LoopTools::class);

    expect($registry1)
        ->toBe($registry2);
});

test('can add tool dynamically', function () {
    $loopTools = app(LoopTools::class);
    $tool = CustomTool::make('dynamic_tool', 'A dynamic tool')->using(fn () => 'Dynamic response');

    $loopTools->addTool($tool);

    expect($loopTools->getTools())
        ->toHaveCount(1)
        ->and($loopTools->getTools()->getTool('dynamic_tool'))
        ->not->toBeNull()
        ->and($loopTools->getTools()->getTool('dynamic_tool')->getName())
        ->toBe('dynamic_tool');
});

test('adding duplicate tool is ignored', function () {
    $loopTools = app(LoopTools::class);
    $tool1 = CustomTool::make('duplicate_tool', 'First tool')->using(fn () => 'First response');
    $tool2 = CustomTool::make('duplicate_tool', 'Second tool')->using(fn () => 'Second response');

    $loopTools->addTool($tool1);
    $loopTools->addTool($tool2); // Should be ignored

    expect($loopTools->getTools())
        ->toHaveCount(1);

    $retrievedTool = $loopTools->getTools()->getTool('duplicate_tool');
    expect($retrievedTool)
        ->not->toBeNull();
});

test('can remove tool by name successfully', function () {
    $loopTools = app(LoopTools::class);
    $tool = CustomTool::make('removable_tool', 'A removable tool')->using(fn () => 'Response');

    $loopTools->addTool($tool);
    expect($loopTools->getTools())->toHaveCount(1);

    $result = $loopTools->removeTool('removable_tool');

    expect($result)
        ->toBeTrue()
        ->and($loopTools->getTools())
        ->toHaveCount(0)
        ->and($loopTools->getTools()->getTool('removable_tool'))
        ->toBeNull();
});

test('removing non-existent tool returns false', function () {
    $loopTools = app(LoopTools::class);

    $result = $loopTools->removeTool('non_existent_tool');

    expect($result)
        ->toBeFalse()
        ->and($loopTools->getTools())
        ->toHaveCount(0);
});

test('can remove specific tool among multiple tools', function () {
    $loopTools = app(LoopTools::class);
    $tool1 = CustomTool::make('tool1', 'Tool 1')->using(fn () => 'Response 1');
    $tool2 = CustomTool::make('tool2', 'Tool 2')->using(fn () => 'Response 2');
    $tool3 = CustomTool::make('tool3', 'Tool 3')->using(fn () => 'Response 3');

    $loopTools->addTool($tool1);
    $loopTools->addTool($tool2);
    $loopTools->addTool($tool3);

    expect($loopTools->getTools())->toHaveCount(3);

    $result = $loopTools->removeTool('tool2');

    expect($result)
        ->toBeTrue()
        ->and($loopTools->getTools())
        ->toHaveCount(2)
        ->and($loopTools->getTools()->getTool('tool1'))
        ->not->toBeNull()
        ->and($loopTools->getTools()->getTool('tool2'))
        ->toBeNull()
        ->and($loopTools->getTools()->getTool('tool3'))
        ->not->toBeNull();
});

test('registerTool method still works for backward compatibility', function () {
    $loopTools = app(LoopTools::class);
    $tool = CustomTool::make('legacy_tool', 'A legacy tool')->using(fn () => 'Legacy response');

    $loopTools->registerTool($tool);

    expect($loopTools->getTools())
        ->toHaveCount(1)
        ->and($loopTools->getTools()->getTool('legacy_tool'))
        ->not->toBeNull();
});

test('clear method resets both tools and toolkits', function () {
    $loopTools = app(LoopTools::class);
    $tool = CustomTool::make('test_tool', 'Test tool')->using(fn () => 'Response');
    $toolkit = LaravelModelToolkit::make([User::class]);

    $loopTools->addTool($tool);
    $loopTools->registerToolkit($toolkit);

    expect($loopTools->getTools()->count())
        ->toBeGreaterThan(0)
        ->and($loopTools->getToolkits())
        ->toHaveCount(1);

    $loopTools->clear();

    expect($loopTools->getTools())
        ->toHaveCount(0)
        ->and($loopTools->getToolkits())
        ->toHaveCount(0);
});

test('multiple add and remove operations work correctly', function () {
    $loopTools = app(LoopTools::class);

    // Add some tools
    $loopTools->addTool(CustomTool::make('tool1', 'Tool 1')->using(fn () => 'Response 1'));
    $loopTools->addTool(CustomTool::make('tool2', 'Tool 2')->using(fn () => 'Response 2'));
    $loopTools->addTool(CustomTool::make('tool3', 'Tool 3')->using(fn () => 'Response 3'));

    expect($loopTools->getTools())->toHaveCount(3);

    // Remove middle tool
    $loopTools->removeTool('tool2');
    expect($loopTools->getTools())->toHaveCount(2);

    // Add another tool
    $loopTools->addTool(CustomTool::make('tool4', 'Tool 4')->using(fn () => 'Response 4'));
    expect($loopTools->getTools())->toHaveCount(3);

    // Remove multiple tools
    $loopTools->removeTool('tool1');
    $loopTools->removeTool('tool3');

    expect($loopTools->getTools())
        ->toHaveCount(1)
        ->and($loopTools->getTools()->getTool('tool4'))
        ->not->toBeNull();
});

// New tests for Loop class fluent interface

test('Loop addTool method returns fluent interface', function () {
    $loop = app(Loop::class);
    $tool = CustomTool::make('fluent_tool', 'Fluent tool')->using(fn () => 'Response');

    $result = $loop->addTool($tool);

    expect($result)
        ->toBe($loop)
        ->and($loop->getPrismTools())
        ->toHaveCount(1)
        ->and($loop->getPrismTools()->first()->name())
        ->toBe('fluent_tool');
});

test('Loop removeTool method returns fluent interface', function () {
    $loop = app(Loop::class);
    $tool = CustomTool::make('removable_fluent_tool', 'Removable fluent tool')->using(fn () => 'Response');

    $loop->addTool($tool);
    expect($loop->getPrismTools())->toHaveCount(1);

    $result = $loop->removeTool('removable_fluent_tool');

    expect($result)
        ->toBe($loop)
        ->and($loop->getPrismTools())
        ->toHaveCount(0);
});

test('Loop clear method returns fluent interface', function () {
    $loop = app(Loop::class);
    $tool = CustomTool::make('clearable_tool', 'Clearable tool')->using(fn () => 'Response');

    $loop->addTool($tool);
    expect($loop->getPrismTools())->toHaveCount(1);

    $result = $loop->clear();

    expect($result)
        ->toBe($loop)
        ->and($loop->getPrismTools())
        ->toHaveCount(0);
});

test('Loop fluent interface supports method chaining', function () {
    $loop = app(Loop::class);
    $tool1 = CustomTool::make('chain_tool1', 'Chain tool 1')->using(fn () => 'Response 1');
    $tool2 = CustomTool::make('chain_tool2', 'Chain tool 2')->using(fn () => 'Response 2');
    $tool3 = CustomTool::make('chain_tool3', 'Chain tool 3')->using(fn () => 'Response 3');

    // Test method chaining
    $result = $loop
        ->addTool($tool1)
        ->addTool($tool2)
        ->addTool($tool3)
        ->removeTool('chain_tool2');

    expect($result)
        ->toBe($loop)
        ->and($loop->getPrismTools())
        ->toHaveCount(2);

    // Verify specific tools exist - use map with name() method instead of pluck
    $toolNames = $loop->getPrismTools()->map(fn ($tool) => $tool->name())->toArray();
    expect($toolNames)
        ->toContain('chain_tool1')
        ->toContain('chain_tool3')
        ->not->toContain('chain_tool2');
});

test('Loop fluent interface integrates with existing methods', function () {
    $loop = app(Loop::class);
    $tool1 = CustomTool::make('integration_tool1', 'Integration tool 1')->using(fn () => 'Response 1');
    $tool2 = CustomTool::make('integration_tool2', 'Integration tool 2')->using(fn () => 'Response 2');

    // Mix new fluent methods with existing fluent methods
    $result = $loop
        ->tool($tool1)  // Existing method
        ->addTool($tool2)  // New method
        ->context('Some context')  // Existing method
        ->removeTool('integration_tool1');  // New method

    expect($result)
        ->toBe($loop)
        ->and($loop->getPrismTools())
        ->toHaveCount(1)
        ->and($loop->getPrismTools()->first()->name())
        ->toBe('integration_tool2');
});
