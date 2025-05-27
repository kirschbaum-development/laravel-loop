<?php

namespace Kirschbaum\Loop\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Core service for handling Server-Sent Events (SSE) functionality.
 *
 * This class provides a unified interface for SSE event formatting and sending
 * that can be used across different transports (streamable HTTP and legacy HTTP+SSE).
 * It handles environment preparation, event formatting, and response creation.
 */
class SseService
{
    /**
     * Get SSE headers for response.
     *
     * @return array<string, string>
     */
    protected function getSseHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ];
    }

    /**
     * Format a JSON-RPC message as an SSE event.
     *
     * @param  array<array-key, mixed>  $message  JSON-RPC message
     */
    protected function formatSseEvent(array|string $message, ?string $eventName = 'message', ?string $eventId = null): string
    {
        $output = '';

        if ($eventId !== null) {
            $output .= "id: {$eventId}\n";
        }

        $output .= "event: {$eventName}\n";

        if (is_string($message)) {
            $output .= "data: {$message}\n\n";
        } else {
            $jsonData = json_encode($message);
            $output .= "data: {$jsonData}\n\n";
        }

        return $output;
    }

    /**
     * Send an SSE event to the client.
     *
     * @param  array<mixed>|string  $message  The message data to send (array or string)
     * @param  string|null  $eventName  The event name (default: 'message')
     * @param  string|null  $eventId  Optional event ID
     */
    public function sendEvent(array|string $message, ?string $eventName = 'message', ?string $eventId = null): void
    {
        echo $this->formatSseEvent($message, $eventName, $eventId);
        flush();
    }

    /**
     * Send a text-based event (when data is already formatted).
     */
    public function sendRawEvent(string $formattedEvent): void
    {
        echo $formattedEvent;
        flush();
    }

    /**
     * Send a ping/heartbeat event.
     */
    public function sendPing(): void
    {
        $this->sendEvent((string) time(), 'ping');
    }

    /**
     * Send a comment (useful for keepalive).
     */
    public function sendComment(string $comment = ''): void
    {
        echo ": {$comment}\n\n";
        flush();
    }

    /**
     * Send a debug event.
     */
    public function sendDebug(string $message): void
    {
        $this->sendEvent(
            ['message' => $message, 'time' => time()],
            'debug'
        );
    }

    /**
     * Setup SSE response environment.
     */
    protected function prepareEnvironment(): void
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        ob_implicit_flush(true);

        if (! ini_get('safe_mode')) {
            set_time_limit(0);
        }

        ignore_user_abort(false); // Detects client disconnects
    }

    /**
     * Create a StreamedResponse with SSE headers.
     *
     * @param  callable  $callback  The callback function that generates the response
     */
    public function createSseResponse(callable $callback): StreamedResponse
    {
        $this->prepareEnvironment();

        return new StreamedResponse($callback, 200, $this->getSseHeaders());
    }
}
