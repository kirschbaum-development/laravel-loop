<?php

namespace Kirschbaum\Loop\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Kirschbaum\Loop\Http\Requests\AskRequest;
use Kirschbaum\Loop\Loop;

class LoopController extends Controller
{
    protected Loop $loop;

    protected string $cacheKey = 'loop_mcp_messages';

    public function __construct(Loop $loop)
    {
        $this->loop = $loop;
    }

    /**
     * Ask the AI a question
     */
    public function ask(AskRequest $request): JsonResponse
    {
        info('Request received', $request->all());

        /** @var array<array-key, mixed> $messages */
        $messages = $request->input('messages');

        // If no messages are provided, use the stored messages
        if (empty($messages)) {
            $messages = $this->getStoredMessages();
        }

        $response = $this->loop->ask(
            $request->string('message'),
            collect($messages)
        );

        // Store the user question and AI response
        $this->storeMessageInCache([
            'user' => 'User',
            'message' => $request->input('message'),
        ]);

        $this->storeMessageInCache([
            'user' => 'AI',
            'message' => (string) $response,
        ]);

        // Format response according to MCP specification
        return response()->json([
            'id' => now()->timestamp,
            'created_at' => now()->toIso8601String(),
            'message' => [
                'role' => 'assistant',
                'content' => (string) $response,
            ],
            'model' => $response->provider ?? config('loop.default_model', 'gpt-4o-mini'),
            'usage' => [
                'prompt_tokens' => $response->usage['prompt_tokens'] ?? 0,
                'completion_tokens' => $response->usage['completion_tokens'] ?? 0,
                'total_tokens' => $response->usage['total_tokens'] ?? 0,
            ],
        ]);
    }

    /**
     * Store a new message in the conversation
     */
    public function storeMessage(Request $request): JsonResponse
    {
        // Validate the request
        $request->validate([
            'message' => 'required|string',
            'role' => 'required|string|in:user,assistant,system',
        ]);

        $message = [
            'user' => $request->input('role') === 'user' ? 'User' : 'AI',
            'message' => $request->input('message'),
        ];

        // Store the message
        $this->storeMessageInCache($message);

        return response()->json([
            'success' => true,
            'message' => 'Message stored successfully',
        ]);
    }

    /**
     * Get all messages in the conversation
     */
    public function getMessages(): JsonResponse
    {
        $messages = $this->getStoredMessages();

        return response()->json([
            'messages' => collect($messages)->map(function ($message) {
                return [
                    'role' => $message['user'] === 'User' ? 'user' : 'assistant',
                    'content' => $message['message'],
                ];
            }),
        ]);
    }

    /**
     * Clear all messages in the conversation
     */
    public function clearMessages(): JsonResponse
    {
        Cache::forget($this->cacheKey);

        return response()->json([
            'success' => true,
            'message' => 'Messages cleared successfully',
        ]);
    }

    /**
     * Store a message in the cache
     */
    protected function storeMessageInCache(array $message): void
    {
        $messages = Cache::get($this->cacheKey, []);
        $messages[] = $message;
        Cache::put($this->cacheKey, $messages, 3600); // Store for 1 hour
    }

    /**
     * Get stored messages from cache
     *
     * @return array<array-key, mixed>
     */
    protected function getStoredMessages(): array
    {
        return Cache::get($this->cacheKey, []);
    }
}
