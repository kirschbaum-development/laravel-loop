<?php

namespace Kirschbaum\Loop\Services;

use Kirschbaum\Loop\Concerns\LogsMessages;

class SseSessionManager
{
    use LogsMessages;

    /**
     * Store active SSE client IDs
     *
     * @var array<string, bool>
     */
    protected array $activeClients = [];

    public function __construct(
        protected SseDriverManager $driverManager,
    ) {}

    /**
     * Register a new SSE connection
     */
    public function registerConnection(string $clientId): void
    {
        $this->activeClients[$clientId] = true;
        $this->driverManager->driver()->registerSession($clientId);

        $this->log("SSE connection registered for client: {$clientId}", level: 'debug');
    }

    /**
     * Remove an SSE connection
     */
    public function removeConnection(string $clientId): void
    {
        if (isset($this->activeClients[$clientId])) {
            unset($this->activeClients[$clientId]);
            $this->driverManager->driver()->removeSession($clientId);

            $this->log("SSE connection removed for client: {$clientId}", level: 'debug');
        }
    }

    /**
     * Send a message to a specific client through their SSE connection
     *
     * @param  string  $clientId  The client session ID
     * @param  array<mixed>  $data  Message data to send
     */
    public function sendToClient(string $clientId, array $data): bool
    {
        return $this->driverManager->driver()->sendMessage($clientId, $data);
    }

    /**
     * Check for new messages for a client
     *
     * @param  string  $clientId  The client session ID
     * @param  int  $lastMessageId  ID of the last received message
     * @return array<mixed> Array of messages
     */
    public function getMessages(string $clientId, int $lastMessageId = -1): array
    {
        return $this->driverManager->driver()->getMessages($clientId, $lastMessageId);
    }

    /**
     * Check if a session exists
     */
    public function sessionExists(string $clientId): bool
    {
        return $this->driverManager->driver()->sessionExists($clientId);
    }

    /**
     * Get all active client connections
     *
     * @return array<string>
     */
    public function getActiveClients(): array
    {
        return array_keys($this->activeClients);
    }
}
