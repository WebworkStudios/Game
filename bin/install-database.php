<?php

/**
 * Database Installation Script
 * Sets up the database schema and initial data
 *
 * File: bin/install-database.php
 * Directory: /bin/
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }
}

echo "🏆 Football Manager - Database Installation\n";
echo "==========================================\n\n";

try {
    // Get database configuration
    $config = require __DIR__ . '/../config/app.php';
    $dbConfig = $config['database']['connections']['mysql']['write'];

    echo "📊 Connecting to database...\n";
    echo "Host: {$dbConfig['host']}:{$dbConfig['port']}\n";
    echo "Database: {$dbConfig['database']}\n\n";

    // Create PDO connection
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Create database if not exists
    echo "🔧 Creating database if not exists...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['database']}` CHARACTER SET {$dbConfig['charset']} COLLATE {$dbConfig['collation']}");
    $pdo->exec("USE `{$dbConfig['database']}`");

    // Read and execute schema
    echo "📋 Installing database schema...\n";
    $schema = file_get_contents(__DIR__ . '/../config/database_schema.sql');

    if (!$schema) {
        throw new Exception("Could not read database schema file");
    }

    // Split and execute statements
    $statements = array_filter(
        array_map('trim', explode(';', $schema)),
        fn($stmt) => !empty($stmt) && !str_starts_with($stmt, '--')
    );

    foreach ($statements as $statement) {
        if (!empty(trim($statement))) {
            try {
                $pdo->exec($statement);
                echo "  ✅ Executed: " . substr(trim($statement), 0, 50) . "...\n";
            } catch (PDOException $e) {
                echo "  ⚠️  Warning: " . $e->getMessage() . "\n";
            }
        }
    }

    // Verify installation
    echo "\n🔍 Verifying installation...\n";
    $tables = ['users', 'leagues', 'teams', 'players', 'rate_limits', 'email_queue', 'game_settings', 'audit_logs'];

    foreach ($tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '{$table}'")->fetch();
        if ($result) {
            $count = $pdo->query("SELECT COUNT(*) as count FROM {$table}")->fetch();
            echo "  ✅ Table '{$table}' exists with {$count['count']} rows\n";
        } else {
            echo "  ❌ Table '{$table}' not found\n";
        }
    }

    echo "\n✅ Database installation completed successfully!\n";
    echo "\n📝 Next Steps:\n";
    echo "1. Copy .env.example to .env and configure your settings\n";
    echo "2. Run: composer serve (to start development server)\n";
    echo "3. Visit: http://localhost:8000/register\n\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "\n💡 Troubleshooting:\n";
    echo "1. Make sure MySQL is running\n";
    echo "2. Check your .env database credentials\n";
    echo "3. Ensure the database user has CREATE privileges\n\n";
    exit(1);
}