<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$config = require __DIR__ . '/config/database.local.php';

try {
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        ),
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $database = $pdo->query('SELECT DATABASE()')->fetchColumn();

    echo "Databaseverbinding geslaagd!\n";
    echo "Verbonden met: {$database}\n";
} catch (PDOException $exception) {
    echo "Databaseverbinding mislukt.\n";
    echo "Foutcode: " . $exception->getCode() . "\n";
    echo "Foutmelding: " . $exception->getMessage() . "\n";
}