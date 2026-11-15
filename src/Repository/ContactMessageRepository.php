<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\ContactMessage;
use WilyCode\Database\QueryExecutor\QueryExecutorInterface;
use WilyCode\Database\Repository\Repository;

/** @extends Repository<ContactMessage> */
final class ContactMessageRepository extends Repository
{
    public function __construct(QueryExecutorInterface $executor)
    {
        parent::__construct($executor, ContactMessage::class);
    }
}
