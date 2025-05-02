<?php

namespace Kirschbaum\Loop\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class SseService
{
    /**
     * Create a streamed SSE response for a POST request.
     */
    public function createPostSseResponse(array $messages, callable $processor): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ];

        if (! ini_get('safe_mode')) {
            set_time_limit(0);
        }

        return new StreamedResponse(function () use ($messages, $processor) {
            if (ob_get_level()) {
                ob_end_clean();
            }

            echo ": stream opened\n\n";
            flush();

            if (! isset($messages[0])) {
                $messages = [$messages];
            }

            foreach ($messages as $message) {
                if (! isset($message['id'])) {
                    logger('Missing ID in message');

                    continue;
                }

                $response = $processor($message);

                echo $this->formatSseEvent($response, eventId: null);
                flush();
            }
        }, 200, $headers);
    }

    /**
     * Format a JSON-RPC message as an SSE event.
     */
    public function formatSseEvent(array $message, ?string $eventId = null): string
    {
        $output = '';

        if ($eventId !== null) {
            $output .= "id: {$eventId}\n";
        }

        // Add event type (default to 'message')
        $output .= "event: message\n";

        $jsonData = json_encode($message);
        $output .= "data: {$jsonData}\n\n";

        return $output;
    }
}
