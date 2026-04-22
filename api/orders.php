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

    if (isset($_GET['mine'])) {
        $customerId = requireCustomer();

        try {
            $statement = db()->prepare('
                SELECT order_code AS id, total, payment_method AS paymentMethod, payment_status AS paymentStatus,
                       order_status AS status, address_line AS address, city, pincode, created_at AS createdAt,
                       items_json
                FROM orders
                WHERE customer_id = ?
                ORDER BY id DESC
            ');
            $statement->execute([$customerId]);
            $rows = $statement->fetchAll();

            $orders = array_map(static function (array $row): array {
                $row['items'] = json_decode((string) $row['items_json'], true) ?: [];
                unset($row['items_json']);
                return $row;
            }, $rows);

            jsonResponse(['orders' => $orders]);
        } catch (Throwable $exception) {
            jsonResponse(['orders' => []]);
        }
    }

    requireAdmin();

    try {
        $statement = db()->query('
            SELECT o.order_code AS id, o.total, o.payment_method AS paymentMethod, o.payment_status AS paymentStatus,
                   o.order_status AS status, o.created_at AS createdAt, o.items_json,
                   c.name, c.email, c.phone
            FROM orders o
            LEFT JOIN customers c ON c.id = o.customer_id
            ORDER BY o.id DESC
        ');
        $rows = $statement->fetchAll();

        $orders = array_map(static function (array $row): array {
            $row['items'] = json_decode((string) $row['items_json'], true) ?: [];
            $row['customer'] = [
                'name' => $row['name'] ?? '',
                'email' => $row['email'] ?? '',
                'phone' => $row['phone'] ?? '',
            ];
            unset($row['items_json'], $row['name'], $row['email'], $row['phone']);
            return $row;
        }, $rows);
    } catch (Throwable $exception) {
        // Fallback to JSON only.
    }

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
    $orderCode = generateOrderId();
    $order = [
        'id' => $orderCode,
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

    $customerId = customerId();
    if (!$customerId && !empty($customer['email'])) {
        try {
            $lookup = db()->prepare('SELECT id FROM customers WHERE email = ? LIMIT 1');
            $lookup->execute([strtolower((string) $customer['email'])]);
            $customerRow = $lookup->fetch();
            $customerId = $customerRow ? (int) $customerRow['id'] : null;
        } catch (Throwable $exception) {
            $customerId = null;
        }
    }

    try {
        $insert = db()->prepare('
            INSERT INTO orders (
                customer_id, order_code, items_json, total, payment_method, payment_status,
                order_status, address_line, city, pincode, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ');
        $insert->execute([
            $customerId,
            $orderCode,
            json_encode($items, JSON_UNESCAPED_SLASHES),
            (float) ($payload['total'] ?? 0),
            (string) ($payload['paymentMethod'] ?? 'cod'),
            (string) ($payload['paymentStatus'] ?? 'pending'),
            (string) ($payload['status'] ?? 'Confirmed - Preparing for dispatch'),
            (string) ($customer['address'] ?? ''),
            (string) ($customer['city'] ?? ''),
            (string) ($customer['pincode'] ?? ''),
            (string) ($payload['notes'] ?? ''),
        ]);
    } catch (Throwable $exception) {
        // JSON fallback only.
    }

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

    try {
        $statement = db()->prepare('
            UPDATE orders
            SET order_status = ?, payment_status = ?, notes = ?, updated_at = NOW()
            WHERE order_code = ?
        ');
        $statement->execute([
            $updated['status'] ?? '',
            $updated['paymentStatus'] ?? '',
            $updated['adminNote'] ?? '',
            $orderId,
        ]);
    } catch (Throwable $exception) {
        // JSON fallback only.
    }

    writeCollection($ordersFile, $orders);
    jsonResponse(['message' => 'Order updated', 'order' => $updated]);
}

jsonResponse(['message' => 'Method not allowed'], 405);
