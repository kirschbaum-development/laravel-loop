<?php

namespace Kirschbaum\Loop\SseDrivers;

use Illuminate\Support\Facades\File;
use Kirschbaum\Loop\Concerns\LogsMessages;
use Kirschbaum\Loop\Contracts\SseDriverInterface;

class FileDriver implements SseDriverInterface
{
    use LogsMessages;

    /**
     * Base directory for storing SSE files
     */
    protected string $storageDir;

    /**
     * Directory for session files
     */
    protected string $sessionsDir;

    /**
     * Directory for message files
     */
    protected string $messagesDir;

    /**
     * Time-to-live for sessions in seconds (24 hours default)
     */
    protected int $sessionTtl;

    /**
     * Constructor.
     *
     * @param  array<string, mixed>  $config  Driver configuration
     */
    public function __construct(array $config = [])
    {
        $storageDir = $config['storage_dir'] ?? storage_path('app/mcp_sse');
        $this->storageDir = is_string($storageDir) ? $storageDir : storage_path('app/mcp_sse');
        $this->sessionsDir = $this->storageDir.'/sessions';
        $this->messagesDir = $this->storageDir.'/messages';

        $sessionTtl = $config['session_ttl'] ?? 86400;
        $this->sessionTtl = is_int($sessionTtl) ? $sessionTtl : 86400; // 24 hours

        $this->ensureDirectoriesExist();
    }

    /**
     * Ensure the required directories exist.
     */
    protected function ensureDirectoriesExist(): void
    {
        foreach ([$this->storageDir, $this->sessionsDir, $this->messagesDir] as $dir) {
            if (! File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
        }
    }

    /**
     * Register a new client session.
     */
    public function registerSession(string $sessionId): bool
    {
        try {
            $sessionFile = $this->getSessionFilePath($sessionId);

            $sessionData = [
                'created_at' => time(),
                'expires_at' => time() + $this->sessionTtl,
                'last_activity' => time(),
            ];

            File::put($sessionFile, json_encode($sessionData) ?: '{}');

            $messageFile = $this->getMessageFilePath($sessionId);
            File::put($messageFile, json_encode([]) ?: '[]');

            $this->log("SSE session registered: {$sessionId}", level: 'debug');

            return true;
        } catch (\Exception $e) {
            $this->log("Failed to register SSE session: {$e->getMessage()}", level: 'error');

            return false;
        }
    }

    /**
     * Check if a session exists and is valid.
     */
    public function sessionExists(string $sessionId): bool
    {
        $sessionFile = $this->getSessionFilePath($sessionId);

        if (! File::exists($sessionFile)) {
            return false;
        }

        try {
            $sessionData = json_decode(File::get($sessionFile), true);

            if (is_array($sessionData) && isset($sessionData['expires_at']) && $sessionData['expires_at'] < time()) {
                $this->removeSession($sessionId);

                return false;
            }

            if (is_array($sessionData)) {
                $sessionData['last_activity'] = time();
                File::put($sessionFile, json_encode($sessionData) ?: '{}');
            }

            return true;
        } catch (\Exception $e) {
            $this->log("Error checking session existence: {$e->getMessage()}", level: 'error');

            return false;
        }
    }

    /**
     * Send a message to a specific client.
     */
    public function sendMessage(string $sessionId, array $data): bool
    {
        if (! $this->sessionExists($sessionId)) {
            $this->log("Attempted to send message to non-existent session: {$sessionId}", level: 'warning');

            return false;
        }

        try {
            $messageFile = $this->getMessageFilePath($sessionId);

            // File locking to prevent race conditions
            $fp = fopen($messageFile, 'c+');
            if ($fp !== false && flock($fp, LOCK_EX)) {
                $messages = [];
                $fileContents = stream_get_contents($fp);

                if (! empty($fileContents)) {
                    $decodedMessages = json_decode($fileContents, true);
                    $messages = is_array($decodedMessages) ? $decodedMessages : [];
                }

                $newMessage = [
                    'id' => count($messages),
                    'timestamp' => time(),
                    'data' => $data,
                ];

                $messages[] = $newMessage;

                // Rewind and truncate before writing
                rewind($fp);
                ftruncate($fp, 0);
                fwrite($fp, json_encode($messages) ?: '[]');
                flock($fp, LOCK_UN);

                $this->log("Message sent to session {$sessionId}", level: 'debug');

                return true;
            } else {
                $this->log("Could not acquire lock for message file: {$sessionId}", level: 'error');

                return false;
            }
        } catch (\Exception $e) {
            $this->log("Error sending message: {$e->getMessage()}", level: 'error');

            return false;
        } finally {
            if (isset($fp) && is_resource($fp)) {
                fclose($fp);
            }
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
            $messageFile = $this->getMessageFilePath($sessionId);

            if (! File::exists($messageFile)) {
                return [];
            }

            $decodedMessages = json_decode(File::get($messageFile), true);
            $messages = is_array($decodedMessages) ? $decodedMessages : [];

            // Filter messages newer than lastMessageId
            $newMessages = array_filter($messages, function ($message) use ($lastMessageId) {
                return is_array($message) && isset($message['id']) && $message['id'] > $lastMessageId;
            });

            /**
             * Forces json encoding of: [] to {}.
             *
             * @var array<array<string, mixed>> $newMessages
             */
            $newMessages = collect($newMessages)->map(function (array $message): array {
                if (isset($message['data']['result']['tools']) && is_array($message['data']['result']['tools'])) {
                    /** @var array<mixed> $tools */
                    $tools = $message['data']['result']['tools'];
                    $message['data']['result']['tools'] = collect($tools)
                        ->map(function ($tool) {
                            if (is_array($tool) &&
                                isset($tool['inputSchema']) && is_array($tool['inputSchema']) &&
                                isset($tool['inputSchema']['properties']) && is_array($tool['inputSchema']['properties'])) {
                                $properties = $tool['inputSchema']['properties'];
                                $tool['inputSchema']['properties'] = empty($properties)
                                    ? new \stdClass
                                    : (object) $properties;
                            }

                            return $tool;
                        })
                        ->all();
                }

                return $message;
            })->all();

            return $newMessages;

        } catch (\Exception $e) {
            $this->log("Error getting messages: {$e->getMessage()}", level: 'error');

            return [];
        }
    }

    /**
     * Remove a client session.
     */
    public function removeSession(string $sessionId): bool
    {
        try {
            $sessionFile = $this->getSessionFilePath($sessionId);
            $messageFile = $this->getMessageFilePath($sessionId);

            if (File::exists($sessionFile)) {
                File::delete($sessionFile);
            }

            if (File::exists($messageFile)) {
                File::delete($messageFile);
            }

            $this->log("SSE session removed: {$sessionId}", level: 'debug');

            return true;
        } catch (\Exception $e) {
            $this->log("Failed to remove SSE session: {$e->getMessage()}", level: 'error');

            return false;
        }
    }

    /**
     * Get all active session IDs.
     */
    public function getActiveSessions(): array
    {
        try {
            $sessionFiles = File::files($this->sessionsDir);
            $sessions = [];

            foreach ($sessionFiles as $file) {
                $sessionId = pathinfo($file, PATHINFO_FILENAME);
                if ($this->sessionExists($sessionId)) {
                    $sessions[] = $sessionId;
                }
            }

            return $sessions;
        } catch (\Exception $e) {
            $this->log("Error getting active sessions: {$e->getMessage()}", level: 'error');

            return [];
        }
    }

    /**
     * Get the path to a session file.
     */
    protected function getSessionFilePath(string $sessionId): string
    {
        return $this->sessionsDir.'/'.$sessionId.'.json';
    }

    /**
     * Get the path to a message file.
     */
    protected function getMessageFilePath(string $sessionId): string
    {
        return $this->messagesDir.'/'.$sessionId.'.json';
    }
}
