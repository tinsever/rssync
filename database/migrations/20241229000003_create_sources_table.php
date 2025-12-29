<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSourcesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('sources');
        $table
            ->addColumn('category_id', 'integer', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('url', 'string', ['limit' => 500])
            ->addColumn('last_refresh', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('category_id', 'categories', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addIndex(['url'], ['unique' => true])
            ->create();
    }
}

