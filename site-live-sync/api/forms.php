<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$formsFile = 'forms.json';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    requireAdmin();
    $forms = readCollection($formsFile);

    if (isset($_GET['type'])) {
        $type = trim((string) $_GET['type']);
        $forms = array_values(array_filter($forms, static fn(array $entry): bool => ($entry['formType'] ?? '') === $type));
    }

    jsonResponse(['entries' => sortNewestFirst($forms)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = readJsonBody();
    $formType = trim((string) ($payload['formType'] ?? ''));
    $data = $payload['payload'] ?? [];

    if ($formType === '' || !is_array($data)) {
        jsonResponse(['message' => 'Invalid form payload'], 422);
    }

    $forms = readCollection($formsFile);
    $entry = [
        'id' => strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
        'formType' => $formType,
        'payload' => $data,
        'createdAt' => gmdate('c'),
    ];

    array_unshift($forms, $entry);
    writeCollection($formsFile, $forms);

    jsonResponse(['message' => 'Form saved', 'entry' => $entry], 201);
}

jsonResponse(['message' => 'Method not allowed'], 405);
