<?php

use Kirschbaum\Loop\McpHandler;

test('it can process notification messages', function () {
    $handler = app(McpHandler::class);

    $message = [
        'jsonrpc' => '2.0',
        'method' => 'notifications/cancelled',
        'id' => '123',
        'params' => [
            'type' => 'notification',
            'content' => 'Hello, world!',
        ],
    ];

    $result = $handler->handle($message);

    expect($result)->toBeArray();
    expect($result['jsonrpc'])->toBe('2.0');
    expect($result['id'])->toBe('123');
    expect($result['result'])->toBe(['success' => true]);
    expect($result['error'])->toBeNull();
});
