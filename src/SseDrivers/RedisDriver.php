<?php

namespace Kirschbaum\Loop\SseDrivers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Kirschbaum\Loop\Contracts\SseDriverInterface;

class RedisDriver implements SseDriverInterface
{
    /**
     * Redis key prefix for SSE data
     */
    protected string $prefix;

    /**
     * Time-to-live for sessions in seconds (24 hours default)
     */
    protected int $sessionTtl;

    /**
     * Redis connection
     */
    protected string $connection;

    /**
     * Constructor.
     *
     * @param  array<string, mixed>  $config  Driver configuration
     */
    public function __construct(array $config = [])
    {
        $prefix = $config['prefix'] ?? 'sse';
        $this->prefix = is_string($prefix) ? $prefix : 'sse';

        $sessionTtl = $config['session_ttl'] ?? 86400;
        $this->sessionTtl = is_int($sessionTtl) ? $sessionTtl : 86400; // 24 hours

        $connection = $config['connection'] ?? 'default';
        $this->connection = is_string($connection) ? $connection : 'default';
    }

    /**
     * Register a new client session.
     */
    public function registerSession(string $sessionId): bool
    {
        try {
            $sessionKey = $this->getSessionKey($sessionId);

            // Create session data with expiration time
            $sessionData = [
                'created_at' => time(),
                'expires_at' => time() + $this->sessionTtl,
                'last_activity' => time(),
            ];

            $result = Redis::connection($this->connection)->hmset($sessionKey, $sessionData);
            Redis::connection($this->connection)->expire($sessionKey, $this->sessionTtl);

            Log::debug("SSE session registered: {$sessionId}");

            return (bool) $result;
        } catch (\Exception $e) {
            Log::error("Failed to register SSE session: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Check if a session exists and is valid.
     */
    public function sessionExists(string $sessionId): bool
    {
        $sessionKey = $this->getSessionKey($sessionId);

        try {
            $exists = Redis::connection($this->connection)->exists($sessionKey);

            if (! $exists) {
                return false;
            }

            $expiresAt = Redis::connection($this->connection)->hget($sessionKey, 'expires_at');

            // Check if session has expired
            if ($expiresAt && (int) $expiresAt < time()) {
                $this->removeSession($sessionId);

                return false;
            }

            // Update last activity time
            Redis::connection($this->connection)->hset($sessionKey, 'last_activity', time());

            // Refresh TTL
            Redis::connection($this->connection)->expire($sessionKey, $this->sessionTtl);

            return true;
        } catch (\Exception $e) {
            Log::error("Error checking session existence: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Send a message to a specific client.
     */
    public function sendMessage(string $sessionId, array $data): bool
    {
        if (! $this->sessionExists($sessionId)) {
            Log::warning("Attempted to send message to non-existent session: {$sessionId}");

            return false;
        }

        try {
            $messagesKey = $this->getMessagesKey($sessionId);

            // Get current message count for ID
            $messageCount = Redis::connection($this->connection)->llen($messagesKey);

            // Create new message
            $newMessage = [
                'id' => $messageCount,
                'timestamp' => time(),
                'data' => $data,
            ];

            // Add message to list
            $result = Redis::connection($this->connection)->rpush(
                $messagesKey,
                json_encode($newMessage)
            );

            // Ensure the messages list expires at the same time as the session
            Redis::connection($this->connection)->expire($messagesKey, $this->sessionTtl);

            Log::debug("Message sent to session {$sessionId}");

            return is_int($result) && $result > 0;
        } catch (\Exception $e) {
            Log::error("Error sending message: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Get messages for a specific client.
     *
     * @return array<array<string, mixed>>
     */
    public function getMessages(string $sessionId, int $lastMessageId = -1): array
    {
        if (! $this->sessionExists($sessionId)) {
            return [];
        }

        try {
            $messagesKey = $this->getMessagesKey($sessionId);

            $rawMessages = Redis::connection($this->connection)->lrange($messagesKey, 0, -1);

            if (empty($rawMessages)) {
                return [];
            }

            $messages = [];
            foreach ($rawMessages as $rawMessage) {
                if (is_string($rawMessage)) {
                    $message = json_decode($rawMessage, true);
                    if (is_array($message) && isset($message['id']) && $message['id'] > $lastMessageId) {
                        $messages[] = $message;
                    }
                }
            }

            /**
             * Forces json encoding of: [] to {}
             *
             * @var array<array<string, mixed>> $messages
             */
            return collect($messages)->map(function (array $message): array {
                if (isset($message['data']) && is_array($message['data']) &&
                    isset($message['data']['result']) && is_array($message['data']['result']) &&
                    isset($message['data']['result']['tools']) && is_array($message['data']['result']['tools'])) {
                    /** @var array<mixed> $tools */
                    $tools = $message['data']['result']['tools'];
                    $message['data']['result']['tools'] = collect($tools)
                        ->map(function ($tool) {
                            if (is_array($tool) &&
                                isset($tool['inputSchema']) && is_array($tool['inputSchema']) &&
                                isset($tool['inputSchema']['properties']) && is_array($tool['inputSchema']['properties'])) {
                                $tool['inputSchema']['properties'] = empty($tool['inputSchema']['properties'])
                                    ? new \stdClass
                                    : (object) $tool['inputSchema']['properties'];
                            }

                            return $tool;
                        })
                        ->all();
                }

                return $message;
            })->all();
        } catch (\Exception $e) {
            Log::error("Error getting messages: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Remove a client session.
     */
    public function removeSession(string $sessionId): bool
    {
        try {
            $sessionKey = $this->getSessionKey($sessionId);
            $messagesKey = $this->getMessagesKey($sessionId);

            $result = Redis::connection($this->connection)->del($sessionKey, $messagesKey);

            Log::debug("SSE session removed: {$sessionId}");

            return is_int($result) && $result > 0;
        } catch (\Exception $e) {
            Log::error("Failed to remove SSE session: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Get all active session IDs.
     *
     * @return array<string>
     */
    public function getActiveSessions(): array
    {
        try {
            $sessionPattern = $this->prefix.':sessions:*';
            $keys = Redis::connection($this->connection)->keys($sessionPattern);

            if (empty($keys)) {
                return [];
            }

            $sessions = [];
            $prefix = $this->prefix.':sessions:';

            foreach ($keys as $key) {
                $sessionId = str_replace($prefix, '', $key);
                if ($this->sessionExists($sessionId)) {
                    $sessions[] = $sessionId;
                }
            }

            return $sessions;
        } catch (\Exception $e) {
            Log::error("Error getting active sessions: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Get the Redis key for a session.
     */
    protected function getSessionKey(string $sessionId): string
    {
        return "{$this->prefix}:sessions:{$sessionId}";
    }

    /**
     * Get the Redis key for messages.
     */
    protected function getMessagesKey(string $sessionId): string
    {
        return "{$this->prefix}:messages:{$sessionId}";
    }
}
