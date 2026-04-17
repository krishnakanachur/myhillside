<?php

declare(strict_types=1);

session_start();

const MH_DATA_DIR = __DIR__ . '/../storage';

if (!is_dir(MH_DATA_DIR)) {
    mkdir(MH_DATA_DIR, 0775, true);
}

$config = require __DIR__ . '/../config.php';

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit;
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function storagePath(string $filename): string
{
    return MH_DATA_DIR . '/' . $filename;
}

function readCollection(string $filename): array
{
    $path = storagePath($filename);
    if (!file_exists($path)) {
        file_put_contents($path, json_encode([], JSON_PRETTY_PRINT));
    }

    $content = file_get_contents($path);
    $decoded = json_decode((string) $content, true);
    return is_array($decoded) ? $decoded : [];
}

function writeCollection(string $filename, array $items): void
{
    file_put_contents(storagePath($filename), json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function generateOrderId(): string
{
    return 'MHS' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

function requireAdmin(): void
{
    if (empty($_SESSION['mh_admin'])) {
        jsonResponse(['message' => 'Unauthorized'], 401);
    }
}

function isAdmin(): bool
{
    return !empty($_SESSION['mh_admin']);
}

function sortNewestFirst(array $items): array
{
    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? ''));
    });

    return $items;
}
