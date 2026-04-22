<?php

declare(strict_types=1);

session_start();

const MH_DATA_DIR = __DIR__ . '/../storage';

if (!is_dir(MH_DATA_DIR)) {
    mkdir(MH_DATA_DIR, 0775, true);
}

$config = require __DIR__ . '/../config.php';

function db(): PDO
{
    global $config;
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (
        empty($config['db_host']) ||
        empty($config['db_name']) ||
        empty($config['db_user'])
    ) {
        throw new RuntimeException('Database config is incomplete.');
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        $config['db_name']
    );

    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

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
    $path = storagePath($filename);
    $dir  = dirname($path);

    if (!is_writable($dir)) {
        @chmod($dir, 0775);
    }

    $result = file_put_contents($path, json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

    if ($result === false) {
        jsonResponse(['message' => 'Storage write failed — check folder permissions on server.'], 500);
    }
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

function customerId(): ?int
{
    return isset($_SESSION['mh_customer_id']) ? (int) $_SESSION['mh_customer_id'] : null;
}

function requireCustomer(): int
{
    $customerId = customerId();
    if (!$customerId) {
        jsonResponse(['message' => 'Customer login required'], 401);
    }

    return $customerId;
}

function currentCustomer(): ?array
{
    $customerId = customerId();
    if (!$customerId) {
        return null;
    }

    try {
        $statement = db()->prepare('SELECT id, name, email, phone, created_at FROM customers WHERE id = ? LIMIT 1');
        $statement->execute([$customerId]);
        $customer = $statement->fetch();
        return $customer ?: null;
    } catch (Throwable $exception) {
        return null;
    }
}

function sortNewestFirst(array $items): array
{
    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? ''));
    });

    return $items;
}
