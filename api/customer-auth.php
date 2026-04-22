<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $customer = currentCustomer();
    jsonResponse([
        'authenticated' => (bool) $customer,
        'customer' => $customer,
    ]);
}

$payload = readJsonBody();
$action = (string) ($payload['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'signup') {
    $name = trim((string) ($payload['name'] ?? ''));
    $email = strtolower(trim((string) ($payload['email'] ?? '')));
    $password = (string) ($payload['password'] ?? '');
    $phone = trim((string) ($payload['phone'] ?? ''));

    if ($name === '' || $email === '' || $password === '') {
        jsonResponse(['message' => 'Name, email, and password are required'], 422);
    }

    try {
        $check = db()->prepare('SELECT id FROM customers WHERE email = ? LIMIT 1');
        $check->execute([$email]);

        if ($check->fetch()) {
            jsonResponse(['message' => 'An account with this email already exists'], 409);
        }

        $insert = db()->prepare('
            INSERT INTO customers (name, email, password_hash, phone, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ');
        $insert->execute([
            $name,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $phone,
        ]);

        $_SESSION['mh_customer_id'] = (int) db()->lastInsertId();
        jsonResponse([
            'message' => 'Account created',
            'customer' => currentCustomer(),
        ], 201);
    } catch (Throwable $exception) {
        jsonResponse(['message' => 'Database setup is not complete yet.'], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $email = strtolower(trim((string) ($payload['email'] ?? '')));
    $password = (string) ($payload['password'] ?? '');

    if ($email === '' || $password === '') {
        jsonResponse(['message' => 'Email and password are required'], 422);
    }

    try {
        $statement = db()->prepare('SELECT id, password_hash FROM customers WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        $customer = $statement->fetch();

        if (!$customer || !password_verify($password, (string) $customer['password_hash'])) {
            jsonResponse(['message' => 'Invalid email or password'], 401);
        }

        $_SESSION['mh_customer_id'] = (int) $customer['id'];
        jsonResponse([
            'message' => 'Login successful',
            'customer' => currentCustomer(),
        ]);
    } catch (Throwable $exception) {
        jsonResponse(['message' => 'Database setup is not complete yet.'], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    unset($_SESSION['mh_customer_id']);
    jsonResponse(['message' => 'Logged out']);
}

jsonResponse(['message' => 'Method not allowed'], 405);
