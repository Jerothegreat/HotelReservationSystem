<?php
declare(strict_types=1);

function loadEnvFileIfPresent(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        if (str_starts_with($trimmed, 'export ')) {
            $trimmed = trim(substr($trimmed, 7));
        }

        $separator = strpos($trimmed, '=');
        if ($separator === false) {
            continue;
        }

        $key = trim(substr($trimmed, 0, $separator));
        $value = trim(substr($trimmed, $separator + 1));

        if ($key === '' || !preg_match('/^[A-Z0-9_]+$/', $key)) {
            continue;
        }

        // Keep runtime/container-provided values as the source of truth.
        if (getenv($key) !== false) {
            continue;
        }

        $valueLength = strlen($value);
        if ($valueLength >= 2) {
            $first = $value[0];
            $last = $value[$valueLength - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function bootstrapEnv(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $rootDir = dirname(__DIR__, 2);
    loadEnvFileIfPresent($rootDir . '/.env');
}

bootstrapEnv();

function isHttpsRequest(): bool
{
    $httpsFlag = $_SERVER['HTTPS'] ?? '';
    $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

    return ($httpsFlag !== '' && strtolower((string)$httpsFlag) !== 'off') || $forwardedProto === 'https';
}

function configureSessionRuntime(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $configuredPath = getenv('HOTEL_SESSION_SAVE_PATH');
    $sessionPath = ($configuredPath !== false && trim($configuredPath) !== '')
        ? trim($configuredPath)
        : rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'hotel_sessions';

    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0775, true);
    }
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }

    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_samesite', 'Lax');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

configureSessionRuntime();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function envOrDefault(string $key, string $default): string
{
    $value = getenv($key);
    if ($value === false || trim($value) === '') {
        return $default;
    }

    return trim($value);
}

function envBool(string $key, bool $default): bool
{
    $value = getenv($key);
    if ($value === false || trim($value) === '') {
        return $default;
    }

    $normalized = strtolower(trim($value));
    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }
    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return $default;
}

function isVolatileDemoMode(): bool
{
    $mode = strtolower(envOrDefault('HOTEL_STORAGE_MODE', 'mysql'));
    if (in_array($mode, ['volatile', 'demo', 'request'], true)) {
        return true;
    }

    return envBool('HOTEL_DEMO_VOLATILE', false);
}

function getDemoCookieName(): string
{
    return envOrDefault('HOTEL_DEMO_COOKIE_NAME', 'hotel_demo_store');
}

function shouldMirrorDemoCookieStorage(): bool
{
    if (!isSessionStorageMode()) {
        return false;
    }

    if (isVolatileDemoMode()) {
        return false;
    }

    return envBool('HOTEL_DEMO_COOKIE_MIRROR', true);
}

function getVolatileHandoffCookieName(): string
{
    return envOrDefault('HOTEL_HANDOFF_COOKIE_NAME', 'hotel_demo_handoff');
}

function getVolatileHandoffTtlSeconds(): int
{
    $ttl = (int)envOrDefault('HOTEL_HANDOFF_TTL_SECONDS', '45');
    return max(10, min($ttl, 300));
}

function hasActiveReservationFilters(array $filters): bool
{
    $keys = ['search', 'payment_type', 'room_type', 'from_date', 'to_date'];

    foreach ($keys as $key) {
        if (trim((string)($filters[$key] ?? '')) !== '') {
            return true;
        }
    }

    return false;
}

function buildVolatileHandoffSigningKey(): string
{
    $sessionId = session_id();
    $secret = envOrDefault('HOTEL_HANDOFF_SECRET', '');

    return hash('sha256', $sessionId . '|' . $secret . '|hotel-handoff');
}

function clearVolatileHandoffCookie(): void
{
    if (headers_sent()) {
        return;
    }

    setcookie(getVolatileHandoffCookieName(), '', [
        'expires' => 1,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function createVolatileHandoffCookie(array $reservation): void
{
    if (!isVolatileDemoMode()) {
        return;
    }

    if (headers_sent()) {
        return;
    }

    $normalized = normalizeReservationRecordForCookie($reservation);
    if ($normalized === null) {
        return;
    }

    $issuedAt = time();
    $data = [
        'reservation' => $normalized,
        'issued_at' => $issuedAt,
    ];

    $dataJson = json_encode($data, JSON_UNESCAPED_SLASHES);
    if ($dataJson === false) {
        return;
    }

    $signature = hash_hmac('sha256', $dataJson, buildVolatileHandoffSigningKey());
    $payloadJson = json_encode([
        'data' => $data,
        'sig' => $signature,
    ], JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        return;
    }

    $encoded = base64_encode($payloadJson);

    setcookie(getVolatileHandoffCookieName(), $encoded, [
        'expires' => time() + getVolatileHandoffTtlSeconds(),
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function consumeVolatileHandoffCookie(): ?array
{
    if (!isVolatileDemoMode()) {
        return null;
    }

    $cookieRaw = $_COOKIE[getVolatileHandoffCookieName()] ?? '';
    if (!is_string($cookieRaw) || $cookieRaw === '') {
        return null;
    }

    // Consume once regardless of validity to prevent replay attempts.
    clearVolatileHandoffCookie();

    $decoded = base64_decode($cookieRaw, true);
    if ($decoded === false) {
        return null;
    }

    $payload = json_decode($decoded, true);
    if (!is_array($payload)) {
        return null;
    }

    $data = $payload['data'] ?? null;
    $signature = (string)($payload['sig'] ?? '');

    if (!is_array($data) || $signature === '') {
        return null;
    }

    $dataJson = json_encode($data, JSON_UNESCAPED_SLASHES);
    if ($dataJson === false) {
        return null;
    }

    $expectedSignature = hash_hmac('sha256', $dataJson, buildVolatileHandoffSigningKey());
    if (!hash_equals($expectedSignature, $signature)) {
        return null;
    }

    $issuedAt = (int)($data['issued_at'] ?? 0);
    if ($issuedAt < 1) {
        return null;
    }

    if ((time() - $issuedAt) > getVolatileHandoffTtlSeconds()) {
        return null;
    }

    $reservation = $data['reservation'] ?? null;
    if (!is_array($reservation)) {
        return null;
    }

    return normalizeReservationRecordForCookie($reservation);
}

function hydrateVolatileHandoffForListing(array $filters, int $page): void
{
    static $processed = false;

    if ($processed) {
        return;
    }

    if (!isVolatileDemoMode()) {
        return;
    }

    if ($page !== 1 || hasActiveReservationFilters($filters)) {
        return;
    }

    $processed = true;

    $handoff = consumeVolatileHandoffCookie();
    if ($handoff === null) {
        return;
    }

    $handoffId = (int)$handoff['id'];
    foreach ($_SESSION['hotel_demo']['volatile_reservations'] as $existing) {
        if ((int)($existing['id'] ?? 0) === $handoffId) {
            return;
        }
    }

    $_SESSION['hotel_demo']['volatile_reservations'][] = $handoff;
    $_SESSION['hotel_demo']['volatile_next_id'] = max(
        (int)$_SESSION['hotel_demo']['volatile_next_id'],
        $handoffId + 1
    );
}

function emitLatestVolatileReservationHandoff(): void
{
    if (!isVolatileDemoMode()) {
        return;
    }

    ensureSessionStorageInitialized();

    $latestId = (int)$_SESSION['hotel_demo']['volatile_next_id'] - 1;
    if ($latestId < 1) {
        return;
    }

    $latestReservation = null;
    foreach ($_SESSION['hotel_demo']['volatile_reservations'] as $reservation) {
        if ((int)($reservation['id'] ?? 0) === $latestId) {
            $latestReservation = $reservation;
            break;
        }
    }

    if (!is_array($latestReservation)) {
        return;
    }

    createVolatileHandoffCookie($latestReservation);
}

function normalizeReservationRecordForCookie(array $reservation): ?array
{
    $id = (int)($reservation['id'] ?? 0);
    if ($id < 1) {
        return null;
    }

    return [
        'id' => $id,
        'customer_name' => (string)($reservation['customer_name'] ?? ''),
        'contact_number' => (string)($reservation['contact_number'] ?? ''),
        'from_date' => (string)($reservation['from_date'] ?? ''),
        'to_date' => (string)($reservation['to_date'] ?? ''),
        'room_type' => (string)($reservation['room_type'] ?? ''),
        'room_capacity' => (string)($reservation['room_capacity'] ?? ''),
        'payment_type' => (string)($reservation['payment_type'] ?? ''),
        'no_of_days' => (int)($reservation['no_of_days'] ?? 0),
        'rate_per_day' => (float)($reservation['rate_per_day'] ?? 0),
        'subtotal' => (float)($reservation['subtotal'] ?? 0),
        'adjust_label' => (string)($reservation['adjust_label'] ?? ''),
        'adjust_value' => (float)($reservation['adjust_value'] ?? 0),
        'total_bill' => (float)($reservation['total_bill'] ?? 0),
        'reserved_at' => (string)($reservation['reserved_at'] ?? ''),
        'created_at' => (string)($reservation['created_at'] ?? ''),
    ];
}

function hydrateSessionFromDemoCookie(): void
{
    if (!shouldMirrorDemoCookieStorage()) {
        return;
    }

    $existingReservations = $_SESSION['hotel_demo']['reservations'] ?? [];
    if (is_array($existingReservations) && !empty($existingReservations)) {
        return;
    }

    $cookieRaw = $_COOKIE[getDemoCookieName()] ?? '';
    if (!is_string($cookieRaw) || $cookieRaw === '') {
        return;
    }

    $decoded = base64_decode($cookieRaw, true);
    if ($decoded === false) {
        return;
    }

    $payload = json_decode($decoded, true);
    if (!is_array($payload)) {
        return;
    }

    $cookieReservations = $payload['reservations'] ?? [];
    if (!is_array($cookieReservations)) {
        return;
    }

    $normalizedReservations = [];
    $maxId = 0;
    foreach ($cookieReservations as $cookieReservation) {
        if (!is_array($cookieReservation)) {
            continue;
        }

        $normalized = normalizeReservationRecordForCookie($cookieReservation);
        if ($normalized === null) {
            continue;
        }

        $normalizedReservations[] = $normalized;
        $maxId = max($maxId, (int)$normalized['id']);
    }

    if (empty($normalizedReservations)) {
        return;
    }

    usort($normalizedReservations, static fn(array $a, array $b): int => (int)$a['id'] <=> (int)$b['id']);

    $_SESSION['hotel_demo']['reservations'] = $normalizedReservations;
    $nextIdFromCookie = (int)($payload['next_id'] ?? 1);
    $_SESSION['hotel_demo']['next_id'] = max($maxId + 1, $nextIdFromCookie, 1);
}

function syncDemoCookieFromSession(): void
{
    if (!shouldMirrorDemoCookieStorage()) {
        return;
    }

    if (headers_sent()) {
        return;
    }

    $cookieName = getDemoCookieName();
    $rawReservations = $_SESSION['hotel_demo']['reservations'] ?? [];
    if (!is_array($rawReservations)) {
        return;
    }

    $normalizedReservations = [];
    foreach ($rawReservations as $rawReservation) {
        if (!is_array($rawReservation)) {
            continue;
        }

        $normalized = normalizeReservationRecordForCookie($rawReservation);
        if ($normalized !== null) {
            $normalizedReservations[] = $normalized;
        }
    }

    // Keep payload small enough for cookie limits.
    $normalizedReservations = array_values(array_slice($normalizedReservations, -8));
    $nextId = max((int)($_SESSION['hotel_demo']['next_id'] ?? 1), 1);

    $encoded = base64_encode((string)json_encode([
        'next_id' => $nextId,
        'reservations' => $normalizedReservations,
    ], JSON_UNESCAPED_SLASHES));

    if (strlen($encoded) > 3800) {
        $normalizedReservations = array_values(array_slice($normalizedReservations, -4));
        $encoded = base64_encode((string)json_encode([
            'next_id' => $nextId,
            'reservations' => $normalizedReservations,
        ], JSON_UNESCAPED_SLASHES));
    }

    setcookie($cookieName, $encoded, [
        'expires' => time() + 86400,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function isSessionStorageMode(): bool
{
    $mode = strtolower(envOrDefault('HOTEL_STORAGE_MODE', 'mysql'));
    if (in_array($mode, ['session', 'volatile', 'demo', 'request'], true)) {
        return true;
    }

    if (envBool('HOTEL_DEMO_VOLATILE', false)) {
        return true;
    }

    return envBool('HOTEL_DEMO_SESSION_ONLY', false);
}

function ensureSessionStarted(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function getSeedRates(): array
{
    return [
        ['Single', 'Regular', 100.00],
        ['Single', 'De Luxe', 300.00],
        ['Single', 'Suite', 500.00],
        ['Double', 'Regular', 200.00],
        ['Double', 'De Luxe', 500.00],
        ['Double', 'Suite', 800.00],
        ['Family', 'Regular', 500.00],
        ['Family', 'De Luxe', 750.00],
        ['Family', 'Suite', 1000.00],
    ];
}

function ensureSessionStorageInitialized(): void
{
    ensureSessionStarted();

    if (!isset($_SESSION['hotel_demo']) || !is_array($_SESSION['hotel_demo'])) {
        $_SESSION['hotel_demo'] = [];
    }

    if (!isset($_SESSION['hotel_demo']['room_rates']) || !is_array($_SESSION['hotel_demo']['room_rates'])) {
        $rates = [];
        foreach (getSeedRates() as $seedRate) {
            $rates[$seedRate[0]][$seedRate[1]] = (float)$seedRate[2];
        }
        $_SESSION['hotel_demo']['room_rates'] = $rates;
    }

    if (!isset($_SESSION['hotel_demo']['reservations']) || !is_array($_SESSION['hotel_demo']['reservations'])) {
        $_SESSION['hotel_demo']['reservations'] = [];
    }

    if (!isset($_SESSION['hotel_demo']['next_id'])) {
        $_SESSION['hotel_demo']['next_id'] = 1;
    }

    if (!isset($_SESSION['hotel_demo']['volatile_reservations']) || !is_array($_SESSION['hotel_demo']['volatile_reservations'])) {
        $_SESSION['hotel_demo']['volatile_reservations'] = [];
    }

    if (!isset($_SESSION['hotel_demo']['volatile_next_id'])) {
        $_SESSION['hotel_demo']['volatile_next_id'] = 1;
    }

    hydrateSessionFromDemoCookie();
}

function getPDO(): ?PDO
{
    if (isSessionStorageMode()) {
        ensureSessionStorageInitialized();
        return null;
    }

    $host = envOrDefault('HOTEL_DB_HOST', '127.0.0.1');
    $port = envOrDefault('HOTEL_DB_PORT', '3306');
    $dbName = envOrDefault('HOTEL_DB_NAME', 'hotel_reservation_system');
    $username = envOrDefault('HOTEL_DB_USER', 'root');
    $password = envOrDefault('HOTEL_DB_PASS', '');
    $charset = envOrDefault('HOTEL_DB_CHARSET', 'utf8mb4');
    $allowCreateDatabase = envBool('HOTEL_DB_AUTO_CREATE_DATABASE', false);

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbName, $charset);

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if ($allowCreateDatabase) {
        $bootstrapDsn = sprintf('mysql:host=%s;port=%s;charset=%s', $host, $port, $charset);
        $bootstrapPdo = new PDO($bootstrapDsn, $username, $password, $options);
        $bootstrapPdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s_unicode_ci', $dbName, $charset, $charset));
    }

    return new PDO($dsn, $username, $password, $options);
}

function initializeDatabase(?PDO $pdo): void
{
    if (isSessionStorageMode()) {
        ensureSessionStorageInitialized();
        return;
    }

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Database connection is not available.');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS room_rates (
            room_capacity VARCHAR(20) NOT NULL,
            room_type VARCHAR(20) NOT NULL,
            rate_per_day DECIMAL(10,2) NOT NULL,
            PRIMARY KEY (room_capacity, room_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS reservations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_name VARCHAR(120) NOT NULL,
            contact_number VARCHAR(40) NOT NULL,
            from_date DATE NOT NULL,
            to_date DATE NOT NULL,
            room_type VARCHAR(20) NOT NULL,
            room_capacity VARCHAR(20) NOT NULL,
            payment_type VARCHAR(20) NOT NULL,
            no_of_days INT NOT NULL,
            rate_per_day DECIMAL(10,2) NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            adjust_label VARCHAR(30) NOT NULL,
            adjust_value DECIMAL(10,2) NOT NULL,
            total_bill DECIMAL(10,2) NOT NULL,
            reserved_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $insertRate = $pdo->prepare(
        'INSERT INTO room_rates (room_capacity, room_type, rate_per_day)
         VALUES (:room_capacity, :room_type, :rate_per_day)
         ON DUPLICATE KEY UPDATE rate_per_day = VALUES(rate_per_day)'
    );

    foreach (getSeedRates() as $rate) {
        $insertRate->execute([
            ':room_capacity' => $rate[0],
            ':room_type' => $rate[1],
            ':rate_per_day' => $rate[2],
        ]);
    }
}

function fetchRatePerDay(?PDO $pdo, string $roomCapacity, string $roomType): ?float
{
    if (isSessionStorageMode()) {
        ensureSessionStorageInitialized();
        $rate = $_SESSION['hotel_demo']['room_rates'][$roomCapacity][$roomType] ?? null;
        return $rate === null ? null : (float)$rate;
    }

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Database connection is not available.');
    }

    $stmt = $pdo->prepare(
        'SELECT rate_per_day FROM room_rates
         WHERE room_capacity = :room_capacity AND room_type = :room_type
         LIMIT 1'
    );

    $stmt->execute([
        ':room_capacity' => $roomCapacity,
        ':room_type' => $roomType,
    ]);

    $rate = $stmt->fetchColumn();
    if ($rate === false) {
        return null;
    }

    return (float)$rate;
}

function saveReservation(?PDO $pdo, array $payload): void
{
    if (isSessionStorageMode()) {
        ensureSessionStorageInitialized();

        if (isVolatileDemoMode()) {
            $id = (int)$_SESSION['hotel_demo']['volatile_next_id'];
            $_SESSION['hotel_demo']['volatile_next_id'] = $id + 1;

            $_SESSION['hotel_demo']['volatile_reservations'][] = [
                'id' => $id,
                'customer_name' => (string)$payload['customer_name'],
                'contact_number' => (string)$payload['contact_number'],
                'from_date' => (string)$payload['from_date'],
                'to_date' => (string)$payload['to_date'],
                'room_type' => (string)$payload['room_type'],
                'room_capacity' => (string)$payload['room_capacity'],
                'payment_type' => (string)$payload['payment_type'],
                'no_of_days' => (int)$payload['no_of_days'],
                'rate_per_day' => (float)$payload['rate_per_day'],
                'subtotal' => (float)$payload['subtotal'],
                'adjust_label' => (string)$payload['adjust_label'],
                'adjust_value' => (float)$payload['adjust_value'],
                'total_bill' => (float)$payload['total_bill'],
                'reserved_at' => (string)$payload['reserved_at'],
                'created_at' => (new DateTime())->format('Y-m-d H:i:s'),
            ];

            return;
        }

        $id = (int)$_SESSION['hotel_demo']['next_id'];
        $_SESSION['hotel_demo']['next_id'] = $id + 1;

        $_SESSION['hotel_demo']['reservations'][] = [
            'id' => $id,
            'customer_name' => (string)$payload['customer_name'],
            'contact_number' => (string)$payload['contact_number'],
            'from_date' => (string)$payload['from_date'],
            'to_date' => (string)$payload['to_date'],
            'room_type' => (string)$payload['room_type'],
            'room_capacity' => (string)$payload['room_capacity'],
            'payment_type' => (string)$payload['payment_type'],
            'no_of_days' => (int)$payload['no_of_days'],
            'rate_per_day' => (float)$payload['rate_per_day'],
            'subtotal' => (float)$payload['subtotal'],
            'adjust_label' => (string)$payload['adjust_label'],
            'adjust_value' => (float)$payload['adjust_value'],
            'total_bill' => (float)$payload['total_bill'],
            'reserved_at' => (string)$payload['reserved_at'],
            'created_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ];

        syncDemoCookieFromSession();

        return;
    }

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Database connection is not available.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO reservations (
            customer_name,
            contact_number,
            from_date,
            to_date,
            room_type,
            room_capacity,
            payment_type,
            no_of_days,
            rate_per_day,
            subtotal,
            adjust_label,
            adjust_value,
            total_bill,
            reserved_at
        ) VALUES (
            :customer_name,
            :contact_number,
            :from_date,
            :to_date,
            :room_type,
            :room_capacity,
            :payment_type,
            :no_of_days,
            :rate_per_day,
            :subtotal,
            :adjust_label,
            :adjust_value,
            :total_bill,
            :reserved_at
        )'
    );

    $stmt->execute([
        ':customer_name' => $payload['customer_name'],
        ':contact_number' => $payload['contact_number'],
        ':from_date' => $payload['from_date'],
        ':to_date' => $payload['to_date'],
        ':room_type' => $payload['room_type'],
        ':room_capacity' => $payload['room_capacity'],
        ':payment_type' => $payload['payment_type'],
        ':no_of_days' => $payload['no_of_days'],
        ':rate_per_day' => $payload['rate_per_day'],
        ':subtotal' => $payload['subtotal'],
        ':adjust_label' => $payload['adjust_label'],
        ':adjust_value' => $payload['adjust_value'],
        ':total_bill' => $payload['total_bill'],
        ':reserved_at' => $payload['reserved_at'],
    ]);
}

function reservationMatchesFilters(array $reservation, array $filters): bool
{
    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $needle = mb_strtolower($search);
        $haystack = mb_strtolower(
            (string)$reservation['customer_name'] . ' ' .
            (string)$reservation['contact_number'] . ' ' .
            (string)$reservation['id']
        );

        if (mb_strpos($haystack, $needle) === false) {
            return false;
        }
    }

    $paymentType = trim((string)($filters['payment_type'] ?? ''));
    if ($paymentType !== '' && (string)$reservation['payment_type'] !== $paymentType) {
        return false;
    }

    $roomType = trim((string)($filters['room_type'] ?? ''));
    if ($roomType !== '' && (string)$reservation['room_type'] !== $roomType) {
        return false;
    }

    $fromDate = trim((string)($filters['from_date'] ?? ''));
    if ($fromDate !== '' && (string)$reservation['from_date'] < $fromDate) {
        return false;
    }

    $toDate = trim((string)($filters['to_date'] ?? ''));
    if ($toDate !== '' && (string)$reservation['to_date'] > $toDate) {
        return false;
    }

    return true;
}

function fetchReservations(?PDO $pdo): array
{
    if (isSessionStorageMode()) {
        ensureSessionStorageInitialized();

        if (isVolatileDemoMode()) {
            $reservations = $_SESSION['hotel_demo']['volatile_reservations'];
            usort($reservations, static fn(array $a, array $b): int => (int)$b['id'] <=> (int)$a['id']);
            return array_values($reservations);
        }

        $reservations = $_SESSION['hotel_demo']['reservations'];
        usort($reservations, static fn(array $a, array $b): int => (int)$b['id'] <=> (int)$a['id']);
        return array_values($reservations);
    }

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Database connection is not available.');
    }

    $stmt = $pdo->query(
        'SELECT
            id,
            customer_name,
            contact_number,
            from_date,
            to_date,
            room_type,
            room_capacity,
            payment_type,
            no_of_days,
            rate_per_day,
            subtotal,
            adjust_label,
            adjust_value,
            total_bill,
            reserved_at,
            created_at
        FROM reservations
        ORDER BY id DESC'
    );

    return $stmt->fetchAll();
}

function fetchReservationsPage(?PDO $pdo, array $filters, int $page, int $perPage): array
{
    if (isSessionStorageMode()) {
        ensureSessionStorageInitialized();
        hydrateVolatileHandoffForListing($filters, $page);

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $rows = array_filter(
            isVolatileDemoMode() ? $_SESSION['hotel_demo']['volatile_reservations'] : $_SESSION['hotel_demo']['reservations'],
            static fn(array $reservation): bool => reservationMatchesFilters($reservation, $filters)
        );

        usort($rows, static fn(array $a, array $b): int => (int)$b['id'] <=> (int)$a['id']);

        if (isVolatileDemoMode()) {
            // Volatile mode: consume records after first listing so refresh clears demo data.
            $_SESSION['hotel_demo']['volatile_reservations'] = [];
        }

        return array_values(array_slice($rows, $offset, $perPage));
    }

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Database connection is not available.');
    }

    $page = max(1, $page);
    $perPage = max(1, $perPage);
    $offset = ($page - 1) * $perPage;

    $params = [];
    $whereClause = buildReservationFilterWhere($filters, $params);

    $sql =
        'SELECT
            id,
            customer_name,
            contact_number,
            from_date,
            to_date,
            room_type,
            room_capacity,
            payment_type,
            no_of_days,
            rate_per_day,
            subtotal,
            adjust_label,
            adjust_value,
            total_bill,
            reserved_at,
            created_at
        FROM reservations' . $whereClause . '
        ORDER BY id DESC
        LIMIT :limit OFFSET :offset';

    $stmt = $pdo->prepare($sql);

    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();

    return $stmt->fetchAll();
}

function countReservationsForFilters(?PDO $pdo, array $filters): int
{
    if (isSessionStorageMode()) {
        ensureSessionStorageInitialized();

        $requestedPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
        $page = $requestedPage === false || $requestedPage === null ? 1 : (int)$requestedPage;
        hydrateVolatileHandoffForListing($filters, max(1, $page));

        $rows = array_filter(
            isVolatileDemoMode() ? $_SESSION['hotel_demo']['volatile_reservations'] : $_SESSION['hotel_demo']['reservations'],
            static fn(array $reservation): bool => reservationMatchesFilters($reservation, $filters)
        );

        return count($rows);
    }

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Database connection is not available.');
    }

    $params = [];
    $whereClause = buildReservationFilterWhere($filters, $params);
    $sql = 'SELECT COUNT(*) FROM reservations' . $whereClause;
    $stmt = $pdo->prepare($sql);

    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value);
    }

    $stmt->execute();

    return (int)$stmt->fetchColumn();
}

function buildReservationFilterWhere(array $filters, array &$params): string
{
    $clauses = [];

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $clauses[] = '(customer_name LIKE :search OR contact_number LIKE :search OR CAST(id AS CHAR) LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    $paymentType = trim((string)($filters['payment_type'] ?? ''));
    if ($paymentType !== '') {
        $clauses[] = 'payment_type = :payment_type';
        $params[':payment_type'] = $paymentType;
    }

    $roomType = trim((string)($filters['room_type'] ?? ''));
    if ($roomType !== '') {
        $clauses[] = 'room_type = :room_type';
        $params[':room_type'] = $roomType;
    }

    $fromDate = trim((string)($filters['from_date'] ?? ''));
    if ($fromDate !== '') {
        $clauses[] = 'from_date >= :from_date';
        $params[':from_date'] = $fromDate;
    }

    $toDate = trim((string)($filters['to_date'] ?? ''));
    if ($toDate !== '') {
        $clauses[] = 'to_date <= :to_date';
        $params[':to_date'] = $toDate;
    }

    if (empty($clauses)) {
        return '';
    }

    return ' WHERE ' . implode(' AND ', $clauses);
}

function fetchReservationById(?PDO $pdo, int $id): ?array
{
    if (isSessionStorageMode()) {
        ensureSessionStorageInitialized();

        $source = isVolatileDemoMode()
            ? $_SESSION['hotel_demo']['volatile_reservations']
            : $_SESSION['hotel_demo']['reservations'];

        foreach ($source as $reservation) {
            if ((int)$reservation['id'] === $id) {
                return $reservation;
            }
        }

        return null;
    }

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Database connection is not available.');
    }

    $stmt = $pdo->prepare(
        'SELECT
            id,
            customer_name,
            contact_number,
            from_date,
            to_date,
            room_type,
            room_capacity,
            payment_type,
            no_of_days,
            rate_per_day,
            subtotal,
            adjust_label,
            adjust_value,
            total_bill,
            reserved_at,
            created_at
        FROM reservations
        WHERE id = :id
        LIMIT 1'
    );

    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function updateReservationById(?PDO $pdo, int $id, array $payload): bool
{
    if (isSessionStorageMode()) {
        ensureSessionStorageInitialized();

        $collectionKey = isVolatileDemoMode() ? 'volatile_reservations' : 'reservations';

        foreach ($_SESSION['hotel_demo'][$collectionKey] as $index => $reservation) {
            if ((int)$reservation['id'] !== $id) {
                continue;
            }

            $_SESSION['hotel_demo'][$collectionKey][$index] = [
                'id' => $id,
                'customer_name' => (string)$payload['customer_name'],
                'contact_number' => (string)$payload['contact_number'],
                'from_date' => (string)$payload['from_date'],
                'to_date' => (string)$payload['to_date'],
                'room_type' => (string)$payload['room_type'],
                'room_capacity' => (string)$payload['room_capacity'],
                'payment_type' => (string)$payload['payment_type'],
                'no_of_days' => (int)$payload['no_of_days'],
                'rate_per_day' => (float)$payload['rate_per_day'],
                'subtotal' => (float)$payload['subtotal'],
                'adjust_label' => (string)$payload['adjust_label'],
                'adjust_value' => (float)$payload['adjust_value'],
                'total_bill' => (float)$payload['total_bill'],
                'reserved_at' => (string)$payload['reserved_at'],
                'created_at' => (string)($reservation['created_at'] ?? (new DateTime())->format('Y-m-d H:i:s')),
            ];

            if (!isVolatileDemoMode()) {
                syncDemoCookieFromSession();
            }

            return true;
        }

        return false;
    }

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Database connection is not available.');
    }

    $stmt = $pdo->prepare(
        'UPDATE reservations
        SET
            customer_name = :customer_name,
            contact_number = :contact_number,
            from_date = :from_date,
            to_date = :to_date,
            room_type = :room_type,
            room_capacity = :room_capacity,
            payment_type = :payment_type,
            no_of_days = :no_of_days,
            rate_per_day = :rate_per_day,
            subtotal = :subtotal,
            adjust_label = :adjust_label,
            adjust_value = :adjust_value,
            total_bill = :total_bill,
            reserved_at = :reserved_at
        WHERE id = :id'
    );

    $stmt->execute([
        ':id' => $id,
        ':customer_name' => $payload['customer_name'],
        ':contact_number' => $payload['contact_number'],
        ':from_date' => $payload['from_date'],
        ':to_date' => $payload['to_date'],
        ':room_type' => $payload['room_type'],
        ':room_capacity' => $payload['room_capacity'],
        ':payment_type' => $payload['payment_type'],
        ':no_of_days' => $payload['no_of_days'],
        ':rate_per_day' => $payload['rate_per_day'],
        ':subtotal' => $payload['subtotal'],
        ':adjust_label' => $payload['adjust_label'],
        ':adjust_value' => $payload['adjust_value'],
        ':total_bill' => $payload['total_bill'],
        ':reserved_at' => $payload['reserved_at'],
    ]);

    return $stmt->rowCount() > 0;
}

function deleteReservationById(?PDO $pdo, int $id): bool
{
    if (isSessionStorageMode()) {
        ensureSessionStorageInitialized();

        $collectionKey = isVolatileDemoMode() ? 'volatile_reservations' : 'reservations';

        foreach ($_SESSION['hotel_demo'][$collectionKey] as $index => $reservation) {
            if ((int)$reservation['id'] !== $id) {
                continue;
            }

            unset($_SESSION['hotel_demo'][$collectionKey][$index]);
            $_SESSION['hotel_demo'][$collectionKey] = array_values($_SESSION['hotel_demo'][$collectionKey]);

            if (!isVolatileDemoMode()) {
                syncDemoCookieFromSession();
            }

            return true;
        }

        return false;
    }

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Database connection is not available.');
    }

    $stmt = $pdo->prepare('DELETE FROM reservations WHERE id = :id');
    $stmt->execute([':id' => $id]);

    return $stmt->rowCount() > 0;
}
