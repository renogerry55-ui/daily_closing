<?php
// /daily_closing/includes/db.php

if (!function_exists('dc_env')) {
    function dc_env(string $key, $default = null)
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

if (!function_exists('dc_bootstrap_env')) {
    function dc_bootstrap_env(): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $envPath = dirname(__DIR__) . '/.env';
        if (!is_readable($envPath)) {
            $loaded = true;
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            $loaded = true;
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $hashPos = strpos($line, '#');
            if ($hashPos !== false) {
                $line = rtrim(substr($line, 0, $hashPos));
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if ($name === '') {
                continue;
            }
            if ($value !== '') {
                $first = $value[0];
                $last  = substr($value, -1);
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }
            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
            }
            if (!array_key_exists($name, $_SERVER)) {
                $_SERVER[$name] = $value;
            }
            putenv($name . '=' . $value);
        }

        $loaded = true;
    }
}

dc_bootstrap_env();

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $dbHost    = dc_env('DB_HOST', '127.0.0.1');
    $dbPort    = dc_env('DB_PORT', '3306');
    $dbName    = dc_env('DB_DATABASE', 'daily_closing');
    $dbUser    = dc_env('DB_USERNAME', 'root');
    $dbPass    = dc_env('DB_PASSWORD', '');
    $dbCharset = dc_env('DB_CHARSET', 'utf8mb4');
    $dbSocket  = dc_env('DB_SOCKET');

    if ($dbSocket) {
        $dsn = "mysql:unix_socket={$dbSocket};dbname={$dbName};charset={$dbCharset}";
    } else {
        $dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";
        if ($dbPort) {
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset={$dbCharset}";
        }
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        exit('Database connection failed: ' . $e->getMessage());
    }
}
