<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add workspace activity events';
    }

    public function up(Schema $schema): void
    {
        $events = $schema->createTable('activity_events');
        $events->addColumn('id', 'integer', ['autoincrement' => true]);
        $events->addColumn('workspace_id', 'integer');
        $events->addColumn('session_id', 'integer', ['notnull' => false]);
        $events->addColumn('actor_id', 'integer', ['notnull' => false]);
        $events->addColumn('event_type', 'string', ['length' => 50]);
        $events->addColumn('summary', 'string');
        $events->addColumn('metadata_json', 'json');
        $events->addColumn('created_at', 'datetime_immutable');
        $events->setPrimaryKey(['id']);
        $events->addIndex(['workspace_id', 'created_at'], 'idx_activity_events_workspace_created');
        $events->addIndex(['session_id', 'created_at'], 'idx_activity_events_session_created');
        $events->addIndex(['event_type'], 'idx_activity_events_type');
        $events->addForeignKeyConstraint('workspaces', ['workspace_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_activity_events_workspace');
        $events->addForeignKeyConstraint('decision_sessions', ['session_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_activity_events_session');
        $events->addForeignKeyConstraint('users', ['actor_id'], ['id'], ['onDelete' => 'SET NULL'], 'fk_activity_events_actor');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('activity_events');
    }
}
