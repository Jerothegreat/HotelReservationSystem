<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/db.php';

$snapshot = getStorageDebugSnapshot();

http_response_code(200);
header('Content-Type: text/plain; charset=UTF-8');

echo "OK\n";
echo 'storage_mode=' . $snapshot['storage_mode'] . "\n";
echo 'session_storage=' . ($snapshot['session_storage'] ? '1' : '0') . "\n";
echo 'volatile_mode=' . ($snapshot['volatile_mode'] ? '1' : '0') . "\n";
echo 'database_required=' . ($snapshot['database_required'] ? '1' : '0') . "\n";
