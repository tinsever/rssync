<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateFeedItemsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('feed_items');
        $table
            ->addColumn('source_id', 'integer', ['signed' => false])
            ->addColumn('guid', 'string', ['limit' => 500])
            ->addColumn('title', 'string', ['limit' => 500])
            ->addColumn('link', 'string', ['limit' => 500])
            ->addColumn('content', 'text', ['null' => true])
            ->addColumn('author', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('categories', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('pub_date', 'datetime')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('source_id', 'sources', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addIndex(['source_id', 'guid'], ['unique' => true])
            ->addIndex(['pub_date'])
            ->create();
    }
}

