<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsersTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users');
        $table
            ->addColumn('email', 'string', ['limit' => 255])
            ->addColumn('password', 'string', ['limit' => 255])
            ->addColumn('email_verified_at', 'timestamp', ['null' => true])
            ->addColumn('verification_token', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('reset_token', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('reset_token_expires', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['email'], ['unique' => true])
            ->addIndex(['verification_token'])
            ->addIndex(['reset_token'])
            ->create();
    }
}

