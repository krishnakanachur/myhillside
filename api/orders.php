<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$ordersFile = 'orders.json';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $orders = readCollection($ordersFile);

    if (isset($_GET['track'])) {
        $orderId = trim((string) $_GET['track']);
        $found = null;

        foreach ($orders as $order) {
            if (($order['id'] ?? '') === $orderId) {
                $found = $order;
                break;
            }
        }

        if (!$found) {
            jsonResponse(['message' => 'Order not found'], 404);
        }

        jsonResponse(['order' => $found]);
    }

    requireAdmin();
    jsonResponse(['orders' => sortNewestFirst($orders)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = readJsonBody();
    $customer = $payload['customer'] ?? [];
    $items = $payload['items'] ?? [];

    if (!is_array($customer) || !is_array($items) || !$items) {
        jsonResponse(['message' => 'Incomplete order payload'], 422);
    }

    $orders = readCollection($ordersFile);
    $order = [
        'id' => generateOrderId(),
        'customer' => $customer,
        'items' => $items,
        'total' => (float) ($payload['total'] ?? 0),
        'paymentMethod' => (string) ($payload['paymentMethod'] ?? 'cod'),
        'paymentStatus' => (string) ($payload['paymentStatus'] ?? 'pending'),
        'status' => (string) ($payload['status'] ?? 'Confirmed - Preparing for dispatch'),
        'createdAt' => gmdate('c'),
        'notes' => (string) ($payload['notes'] ?? ''),
        'razorpay' => $payload['razorpay'] ?? null,
    ];

    array_unshift($orders, $order);
    writeCollection($ordersFile, $orders);

    jsonResponse(['message' => 'Order created', 'order' => $order], 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    requireAdmin();
    $payload = readJsonBody();
    $orderId = trim((string) ($payload['orderId'] ?? ''));
    $nextStatus = trim((string) ($payload['status'] ?? ''));
    $paymentStatus = trim((string) ($payload['paymentStatus'] ?? ''));
    $adminNote = trim((string) ($payload['adminNote'] ?? ''));

    if ($orderId === '') {
        jsonResponse(['message' => 'Order ID is required'], 422);
    }

    $orders = readCollection($ordersFile);
    $updated = null;

    foreach ($orders as &$order) {
        if (($order['id'] ?? '') !== $orderId) {
            continue;
        }

        if ($nextStatus !== '') {
            $order['status'] = $nextStatus;
        }

        if ($paymentStatus !== '') {
            $order['paymentStatus'] = $paymentStatus;
        }

        if ($adminNote !== '') {
            $order['adminNote'] = $adminNote;
        }

        $order['updatedAt'] = gmdate('c');
        $updated = $order;
        break;
    }
    unset($order);

    if (!$updated) {
        jsonResponse(['message' => 'Order not found'], 404);
    }

    writeCollection($ordersFile, $orders);
    jsonResponse(['message' => 'Order updated', 'order' => $updated]);
}

jsonResponse(['message' => 'Method not allowed'], 405);
