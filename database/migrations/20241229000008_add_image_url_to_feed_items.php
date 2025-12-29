<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddImageUrlToFeedItems extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('feed_items');
        $table
            ->addColumn('image_url', 'string', ['limit' => 1000, 'null' => true, 'after' => 'categories'])
            ->update();
    }
}