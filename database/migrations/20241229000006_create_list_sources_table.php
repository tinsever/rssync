<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateListSourcesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('list_sources');
        $table
            ->addColumn('list_id', 'integer', ['signed' => false])
            ->addColumn('source_id', 'integer', ['signed' => false])
            ->addColumn('author_whitelist', 'string', ['limit' => 1000, 'null' => true])
            ->addColumn('author_blacklist', 'string', ['limit' => 1000, 'null' => true])
            ->addColumn('category_whitelist', 'string', ['limit' => 1000, 'null' => true])
            ->addColumn('category_blacklist', 'string', ['limit' => 1000, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('list_id', 'lists', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('source_id', 'sources', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addIndex(['list_id', 'source_id'], ['unique' => true])
            ->create();
    }
}

