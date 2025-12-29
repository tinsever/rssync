<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateListsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('lists');
        $table
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('slug', 'string', ['limit' => 255])
            ->addColumn('is_public', 'boolean', ['default' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addIndex(['slug'], ['unique' => true])
            ->create();
    }
}

