<?php
/**
 * Finpay Technologies - Health Check Endpoint
 * Used by Docker HEALTHCHECK, ALB target group health checks,
 * and ECS container health checks.
 *
 * Returns HTTP 200 with JSON when the application is healthy.
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

$health = [
    'status'      => 'healthy',
    'service'     => 'finpay-app',
    'environment' => getenv('APP_ENV') ?: 'unknown',
    'timestamp'   => date('c'),
    'php_version' => PHP_VERSION,
];

// Optional: check DB connectivity
$dbHost = getenv('DB_HOST');
if ($dbHost) {
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $dbHost,
            getenv('DB_PORT') ?: '3306',
            getenv('DB_NAME') ?: 'finpaydb'
        );
        $pdo = new PDO(
            $dsn,
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD'),
            [PDO::ATTR_TIMEOUT => 2]
        );
        $health['database'] = 'connected';
    } catch (Exception $e) {
        $health['database'] = 'unavailable';
        // Don't fail health check on DB — ECS may start before RDS is ready
    }
}

http_response_code(200);
echo json_encode($health, JSON_PRETTY_PRINT);
