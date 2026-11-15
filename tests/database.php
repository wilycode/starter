<?php

declare(strict_types=1);

use App\Repository\ContactMessageRepository;
use WilyCode\Database\Driver\DatabaseInterface;
use WilyCode\Database\Driver\PlatformProviderInterface;
use WilyCode\Database\DriverPdo\PdoBinder;
use WilyCode\Database\QueryExecutor\QueryExecutor;

require dirname(__DIR__) . '/vendor/autoload.php';

// Check the application's repository -> executor -> PDO binder path without a server.
// Wily Starter targets MySQL 8. This verifies its repository queries without
// requiring a live database. Do not claim other SQL dialects are supported here.
$checks = 0;
$check = static function (bool $valid, string $label) use (&$checks): void {
    $checks++;
    if (!$valid) {
        throw new RuntimeException('FAILED: ' . $label);
    }
};
foreach (['mysql'] as $platform) {
    $driver = new class($platform) implements DatabaseInterface, PlatformProviderInterface {
        public array $queries = [];
        public function __construct(private string $platform) {}
        public function getPlatformName(): string { return $this->platform; }
        public function fetchColumn(string $query, array $params = [], int $columnIndex = 0, array $types = []): mixed
        {
            $this->queries[] = [$query, $params];
            return 21;
        }
        public function fetchAll(string $query, array $params = [], array $types = []): array
        {
            $this->queries[] = [$query, $params];
            return [];
        }
        public function fetch(string $query, array $params = [], array $types = []): array { throw new LogicException('Unexpected fetch'); }
        public function execute(string $query, array $params = [], array $types = []): int { throw new LogicException('Unexpected write'); }
        public function getLastInsertId(): string { throw new LogicException('Unexpected insert'); }
        public function transaction(callable $callback): mixed { return $callback($this); }
        public function inTransaction(): bool { return false; }
    };
    $repository = new ContactMessageRepository(new QueryExecutor($driver, new PdoBinder()));
    $page = $repository->whereIn('id', [1, 2])->whereLike('email', 'example.test')->orderBy('id')->paginate(2, 10);
    $check($page->total === 21 && $page->page === 2 && $page->lastPage() === 3, $platform . ': pagination result');
    $check(count($driver->queries) === 2, $platform . ': count and page queries');
    $quote = $platform === 'mysql' ? '`' : '"';
    foreach ($driver->queries as [$sql, $params]) {
        $check(str_contains($sql, 'FROM contact_message AS cm'), $platform . ': singular contact message table');
        $check(str_contains($sql, $quote . 'id' . $quote), $platform . ': alias quoting');
        $check($platform === 'mysql' || !str_contains($sql, '`'), $platform . ': no MySQL identifiers');
        $check(!str_contains($sql, 'example.test') && in_array('%example.test%', $params, true), $platform . ': bound LIKE');
        $check(in_array(1, $params, true) && in_array(2, $params, true), $platform . ': bound IN');
    }
    $check(str_contains($driver->queries[0][0], 'COUNT('), $platform . ': count SQL');
    $check(str_contains($driver->queries[1][0], 'LIMIT 10 OFFSET 10'), $platform . ': page offset');
}
printf("OK: %d database compatibility assertions passed.\n", $checks);
