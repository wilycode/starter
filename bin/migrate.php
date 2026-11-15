<?php

declare(strict_types=1);

use App\Support\DatabaseFactory;
use App\Support\Environment;
use WilyCode\Migration\DatabaseMigrationRepository;
use WilyCode\Migration\Migrator;

require dirname(__DIR__) . '/vendor/autoload.php';
Environment::load(dirname(__DIR__));

$command = $argv[1] ?? 'status';
if (!in_array($command, ['status', 'up', 'down'], true) || count($argv) > 2) {
    fwrite(STDERR, "Usage: php bin/migrate.php [status|up|down]\n");
    exit(2);
}

try {
    $db = DatabaseFactory::connect();
    $migrator = Migrator::fromPhpDirectory(
        db: $db,
        repository: new DatabaseMigrationRepository($db),
        migrationsDir: dirname(__DIR__) . '/migrations',
    );

    $result = match ($command) {
        'status' => $migrator->status(),
        'up' => $migrator->up()->applied,
        'down' => $migrator->down()->rolledBack,
    };
    print_r($result);
} catch (Throwable $error) {
    fwrite(STDERR, 'Migration failed: ' . $error->getMessage() . "\n");
    exit(1);
}
