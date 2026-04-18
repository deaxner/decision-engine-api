<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create decision engine core schema';
    }

    public function up(Schema $schema): void
    {
        $users = $schema->createTable('users');
        $users->addColumn('id', 'integer', ['autoincrement' => true]);
        $users->addColumn('email', 'string', ['length' => 180]);
        $users->addColumn('password_hash', 'string');
        $users->addColumn('display_name', 'string');
        $users->addColumn('avatar_url', 'string', ['notnull' => false]);
        $users->addColumn('created_at', 'datetime_immutable');
        $users->setPrimaryKey(['id']);
        $users->addUniqueIndex(['email'], 'uniq_users_email');

        $workspaces = $schema->createTable('workspaces');
        $workspaces->addColumn('id', 'integer', ['autoincrement' => true]);
        $workspaces->addColumn('created_by', 'integer');
        $workspaces->addColumn('name', 'string');
        $workspaces->addColumn('slug', 'string');
        $workspaces->addColumn('created_at', 'datetime_immutable');
        $workspaces->setPrimaryKey(['id']);
        $workspaces->addUniqueIndex(['slug'], 'uniq_workspaces_slug');
        $workspaces->addIndex(['created_by'], 'idx_workspaces_created_by');
        $workspaces->addForeignKeyConstraint('users', ['created_by'], ['id'], [], 'fk_workspaces_created_by');

        $members = $schema->createTable('workspace_members');
        $members->addColumn('id', 'integer', ['autoincrement' => true]);
        $members->addColumn('workspace_id', 'integer');
        $members->addColumn('user_id', 'integer');
        $members->addColumn('role', 'string', ['length' => 20]);
        $members->addColumn('joined_at', 'datetime_immutable');
        $members->setPrimaryKey(['id']);
        $members->addUniqueIndex(['workspace_id', 'user_id'], 'uniq_workspace_user');
        $members->addIndex(['workspace_id'], 'idx_workspace_members_workspace');
        $members->addIndex(['user_id'], 'idx_workspace_members_user');
        $members->addForeignKeyConstraint('workspaces', ['workspace_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_workspace_members_workspace');
        $members->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_workspace_members_user');

        $sessions = $schema->createTable('decision_sessions');
        $sessions->addColumn('id', 'integer', ['autoincrement' => true]);
        $sessions->addColumn('workspace_id', 'integer');
        $sessions->addColumn('created_by', 'integer');
        $sessions->addColumn('title', 'string');
        $sessions->addColumn('description', 'text', ['notnull' => false]);
        $sessions->addColumn('status', 'string', ['length' => 20]);
        $sessions->addColumn('voting_type', 'string', ['length' => 20]);
        $sessions->addColumn('starts_at', 'datetime_immutable', ['notnull' => false]);
        $sessions->addColumn('ends_at', 'datetime_immutable', ['notnull' => false]);
        $sessions->addColumn('created_at', 'datetime_immutable');
        $sessions->setPrimaryKey(['id']);
        $sessions->addIndex(['workspace_id'], 'idx_decision_sessions_workspace');
        $sessions->addIndex(['created_by'], 'idx_decision_sessions_created_by');
        $sessions->addForeignKeyConstraint('workspaces', ['workspace_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_decision_sessions_workspace');
        $sessions->addForeignKeyConstraint('users', ['created_by'], ['id'], [], 'fk_decision_sessions_created_by');

        $options = $schema->createTable('options');
        $options->addColumn('id', 'integer', ['autoincrement' => true]);
        $options->addColumn('session_id', 'integer');
        $options->addColumn('title', 'string');
        $options->addColumn('position', 'integer');
        $options->setPrimaryKey(['id']);
        $options->addIndex(['session_id'], 'idx_options_session');
        $options->addForeignKeyConstraint('decision_sessions', ['session_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_options_session');

        $votes = $schema->createTable('votes');
        $votes->addColumn('id', 'integer', ['autoincrement' => true]);
        $votes->addColumn('session_id', 'integer');
        $votes->addColumn('user_id', 'integer');
        $votes->addColumn('payload_json', 'json');
        $votes->addColumn('created_at', 'datetime_immutable');
        $votes->setPrimaryKey(['id']);
        $votes->addIndex(['session_id'], 'idx_votes_session');
        $votes->addIndex(['session_id', 'user_id'], 'idx_votes_session_user');
        $votes->addIndex(['session_id', 'user_id', 'created_at'], 'idx_votes_session_user_created');
        $votes->addForeignKeyConstraint('decision_sessions', ['session_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_votes_session');
        $votes->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_votes_user');

        $results = $schema->createTable('session_results');
        $results->addColumn('session_id', 'integer');
        $results->addColumn('winning_option_id', 'integer', ['notnull' => false]);
        $results->addColumn('version', 'integer');
        $results->addColumn('result_data_json', 'json');
        $results->addColumn('calculated_at', 'datetime_immutable');
        $results->setPrimaryKey(['session_id']);
        $results->addIndex(['winning_option_id'], 'idx_session_results_winner');
        $results->addForeignKeyConstraint('decision_sessions', ['session_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_session_results_session');
        $results->addForeignKeyConstraint('options', ['winning_option_id'], ['id'], ['onDelete' => 'SET NULL'], 'fk_session_results_winner');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('session_results');
        $schema->dropTable('votes');
        $schema->dropTable('options');
        $schema->dropTable('decision_sessions');
        $schema->dropTable('workspace_members');
        $schema->dropTable('workspaces');
        $schema->dropTable('users');
    }
}

