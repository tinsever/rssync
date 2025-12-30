<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddWorkosUserIdToUsers extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users');
        $table
            ->addColumn('workos_user_id', 'string', ['limit' => 255, 'null' => true])
            ->addIndex(['workos_user_id'], ['unique' => true, 'name' => 'idx_users_workos_user_id'])
            ->update();
    }
}

