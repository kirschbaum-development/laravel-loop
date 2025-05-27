<?php

namespace Kirschbaum\Loop\Contracts;

/**
 * Interface for SSE drivers.
 */
interface SseDriverInterface
{
    /**
     * Register a new client session.
     *
     * @param  string  $sessionId  Unique identifier for the client session
     * @return bool Whether registration was successful
     */
    public function registerSession(string $sessionId): bool;

    /**
     * Check if a session exists.
     *
     * @param  string  $sessionId  The session identifier to check
     * @return bool Whether the session exists
     */
    public function sessionExists(string $sessionId): bool;

    /**
     * Send a message to a specific client.
     *
     * @param  string  $sessionId  Session identifier
     * @param  array<string, mixed>  $data  Message data to send
     * @return bool Whether the message was sent successfully
     */
    public function sendMessage(string $sessionId, array $data): bool;

    /**
     * Get messages for a specific client.
     *
     * @param  string  $sessionId  Session identifier
     * @param  int  $lastMessageId  ID of the last received message (for pagination)
     * @return array<array<string, mixed>> Array of messages
     */
    public function getMessages(string $sessionId, int $lastMessageId = -1): array;

    /**
     * Remove a client session.
     *
     * @param  string  $sessionId  Session identifier
     * @return bool Whether removal was successful
     */
    public function removeSession(string $sessionId): bool;

    /**
     * Get all active session IDs.
     *
     * @return array<string> Array of active session IDs
     */
    public function getActiveSessions(): array;
}
