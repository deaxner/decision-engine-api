<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add decision metadata and assignees';
    }

    public function up(Schema $schema): void
    {
        $sessions = $schema->getTable('decision_sessions');
        $sessions->addColumn('category', 'string', ['notnull' => false]);
        $sessions->addColumn('due_at', 'datetime_immutable', ['notnull' => false]);

        $assignees = $schema->createTable('session_assignees');
        $assignees->addColumn('id', 'integer', ['autoincrement' => true]);
        $assignees->addColumn('session_id', 'integer');
        $assignees->addColumn('user_id', 'integer');
        $assignees->addColumn('assigned_at', 'datetime_immutable');
        $assignees->setPrimaryKey(['id']);
        $assignees->addUniqueIndex(['session_id', 'user_id'], 'uniq_session_assignee');
        $assignees->addIndex(['session_id'], 'idx_session_assignees_session');
        $assignees->addIndex(['user_id'], 'idx_session_assignees_user');
        $assignees->addForeignKeyConstraint('decision_sessions', ['session_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_session_assignees_session');
        $assignees->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_session_assignees_user');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('session_assignees');
        $sessions = $schema->getTable('decision_sessions');
        $sessions->dropColumn('due_at');
        $sessions->dropColumn('category');
    }
}
