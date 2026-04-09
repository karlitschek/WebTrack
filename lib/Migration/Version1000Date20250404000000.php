<?php

declare(strict_types=1);

namespace OCA\WebTrack\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20250404000000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('wn_monitors')) {
            $table = $schema->createTable('wn_monitors');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 200]);
            $table->addColumn('url', Types::STRING, ['notnull' => true, 'length' => 2048]);
            $table->addColumn('keyword', Types::STRING, ['notnull' => true, 'length' => 500]);
            $table->addColumn('check_interval', Types::INTEGER, ['notnull' => true, 'default' => 60]);
            $table->addColumn('is_active', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
            $table->addColumn('is_feed', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
            $table->addColumn('last_check_at', Types::STRING, ['notnull' => false, 'length' => 32, 'default' => null]);
            $table->addColumn('last_found_at', Types::STRING, ['notnull' => false, 'length' => 32, 'default' => null]);
            $table->addColumn('last_error_at', Types::STRING, ['notnull' => false, 'length' => 32, 'default' => null]);
            $table->addColumn('last_error_msg', Types::STRING, ['notnull' => false, 'length' => 2048, 'default' => null]);
            $table->addColumn('consecutive_errors', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            $table->addColumn('talk_room_token', Types::STRING, ['notnull' => false, 'length' => 32, 'default' => null]);
            $table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'ok']);
            $table->addColumn('created_at', Types::STRING, ['notnull' => true, 'length' => 32]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'wn_mon_uid');
            $table->addIndex(['is_active'], 'wn_mon_active');
        }

        if (!$schema->hasTable('wn_history')) {
            $table = $schema->createTable('wn_history');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('monitor_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            $table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('event', Types::STRING, ['notnull' => true, 'length' => 20]);
            $table->addColumn('snippet', Types::TEXT, ['notnull' => false, 'default' => null]);
            $table->addColumn('error_msg', Types::STRING, ['notnull' => false, 'length' => 2048, 'default' => null]);
            $table->addColumn('created_at', Types::STRING, ['notnull' => true, 'length' => 32]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['monitor_id'], 'wn_hist_mid');
            $table->addIndex(['user_id'], 'wn_hist_uid');
        }

        if (!$schema->hasTable('wn_feed_state')) {
            $table = $schema->createTable('wn_feed_state');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('monitor_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            $table->addColumn('seen_ids', Types::TEXT, ['notnull' => true, 'default' => '[]']);
            $table->addColumn('updated_at', Types::STRING, ['notnull' => true, 'length' => 32]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['monitor_id'], 'wn_fs_mid');
        }

        return $schema;
    }
}
