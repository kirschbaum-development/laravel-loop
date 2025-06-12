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
