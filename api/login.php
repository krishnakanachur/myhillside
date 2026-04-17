<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonResponse([
        'authenticated' => isAdmin(),
        'username' => $config['admin_username'],
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = readJsonBody();
    $username = trim((string) ($payload['username'] ?? ''));
    $password = (string) ($payload['password'] ?? '');

    if ($username === $config['admin_username'] && $password === $config['admin_password']) {
        $_SESSION['mh_admin'] = true;
        jsonResponse(['message' => 'Login successful']);
    }

    jsonResponse(['message' => 'Invalid username or password'], 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    unset($_SESSION['mh_admin']);
    jsonResponse(['message' => 'Logged out']);
}

jsonResponse(['message' => 'Method not allowed'], 405);
