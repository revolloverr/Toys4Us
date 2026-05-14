<?php

declare(strict_types=1);

namespace App\Services;

/**
 * FlashService
 *
 * A simple session-based flash messaging system.
 * Supports success, error, warning, and info message types.
 */
class FlashService
{
    private const SESSION_KEY = '_flash_messages';

    /**
     * Add a flash message to the session.
     *
     * @param string $type    One of: 'success', 'error', 'warning', 'info'
     * @param string $message The message text.
     */
    public function add(string $type, string $message): void
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
        $_SESSION[self::SESSION_KEY][] = [
            'type'    => $type,
            'message' => $message,
        ];
    }

    /**
     * Retrieve and clear all flash messages.
     *
     * @return array<int, array{type: string, message: string}>
     */
    public function getMessages(): array
    {
        $messages = $_SESSION[self::SESSION_KEY] ?? [];
        unset($_SESSION[self::SESSION_KEY]);
        return $messages;
    }

    /**
     * Check if there are any flash messages pending.
     */
    public function hasMessages(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY]);
    }

    // ── Convenience shortcuts ──────────────────────────────────────────────

    public function success(string $message): void
    {
        $this->add('success', $message);
    }

    public function error(string $message): void
    {
        $this->add('error', $message);
    }

    public function warning(string $message): void
    {
        $this->add('warning', $message);
    }

    public function info(string $message): void
    {
        $this->add('info', $message);
    }
}