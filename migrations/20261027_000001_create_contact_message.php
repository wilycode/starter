<?php

declare(strict_types=1);

use WilyCode\Database\Driver\DatabaseInterface;
use WilyCode\Migration\AbstractMigration;

return new class extends AbstractMigration {
    public function useTransaction(): bool
    {
        // MySQL DDL statements may implicitly commit a transaction.
        return false;
    }

    public function up(DatabaseInterface $db): void
    {
        $this->execSqlFile($db, '20261027_000001_create_contact_message.up.sql');
    }

    public function down(DatabaseInterface $db): void
    {
        $this->execSqlFile($db, '20261027_000001_create_contact_message.down.sql');
    }
};
