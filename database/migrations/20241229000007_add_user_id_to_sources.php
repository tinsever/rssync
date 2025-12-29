<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddUserIdToSources extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('sources');
        $table
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => true, 'after' => 'id'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addIndex(['user_id'])
            ->update();
    }
}

