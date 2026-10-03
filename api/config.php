<?php

declare(strict_types=1);

load_dotenv_file(__DIR__ . '/../.env');

function env_or_default(string $key, string $default): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        $local = local_config();
        $localValue = $local[$key] ?? '';
        if (is_string($localValue) && $localValue !== '') {
            return $localValue;
        }
    }

    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function load_dotenv_file(string $path): void
{
    static $loaded = false;
    if ($loaded || !is_file($path) || !is_readable($path)) {
        $loaded = true;
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        $loaded = true;
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $parts = explode('=', $trimmed, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    $loaded = true;
}

function local_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $configPath = __DIR__ . '/../config/local.php';
    if (!is_file($configPath)) {
        $config = [];
        return $config;
    }

    $loaded = require $configPath;
    $config = is_array($loaded) ? $loaded : [];
    return $config;
}

function db_connection(): PDO
{
    $host = env_or_default('DB_HOST', '127.0.0.1');
    $name = env_or_default('DB_NAME', 'mi_portafolio');
    $envPort = getenv('DB_PORT');
    $envUser = getenv('DB_USER');
    $envPass = getenv('DB_PASS');

    $ports = $envPort !== false && $envPort !== '' ? [$envPort] : ['8889', '3306'];
    $credentials = [];

    if ($envUser !== false && $envUser !== '') {
        $credentials[] = [$envUser, $envPass === false ? '' : $envPass];
    } else {
        $credentials[] = ['root', 'root'];
        $credentials[] = ['root', ''];
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $lastError = null;

    foreach ($ports as $port) {
        foreach ($credentials as [$user, $pass]) {
            try {
                $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass, $options);
                ensure_contact_schema($pdo);
                return $pdo;
            } catch (PDOException $e) {
                $lastError = $e;

                // 1049 = Unknown database. Try to create it and continue.
                if ((int) ($e->errorInfo[1] ?? 0) === 1049) {
                    $serverDsn = "mysql:host={$host};port={$port};charset=utf8mb4";
                    $serverPdo = new PDO($serverDsn, $user, $pass, $options);
                    $serverPdo->exec(
                        "CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                    );
                    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
                    $pdo = new PDO($dsn, $user, $pass, $options);
                    ensure_contact_schema($pdo);
                    return $pdo;
                }
            }
        }
    }

    if ($lastError instanceof Throwable) {
        throw $lastError;
    }

    throw new RuntimeException('No se pudo establecer conexion a la base de datos.');
}

function ensure_contact_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS contact_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(80) NOT NULL,
            email VARCHAR(150) NOT NULL,
            message TEXT NOT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );
}
