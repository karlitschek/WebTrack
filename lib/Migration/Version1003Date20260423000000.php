<?php

declare(strict_types=1);

namespace OCA\WebTrack\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add columns to wn_monitors that support Google News / YouTube RSS sources,
 * relevance scoring, and writing matched articles to Nextcloud Tables.
 *
 * New columns:
 *   source_type       – 'custom' (default) | 'google_news' | 'youtube'
 *   source_language   – BCP-47 tag used for Google News hl/gl params (e.g. 'en-US')
 *   score_threshold   – minimum relevance score required to act on a feed entry (default 2)
 *   boost_keywords    – JSON array of extra terms that raise the score (+1 each)
 *   exclude_patterns  – JSON array of URL/title substrings that lower the score (-2 each)
 *   tables_table_id   – Nextcloud Tables table ID to write matched articles into (nullable)
 *   tables_campaign_id – pre-set Campaign selection option ID for auto-filled rows (nullable)
 */
class Version1003Date20260423000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        $table  = $schema->getTable('wn_monitors');

        if (!$table->hasColumn('source_type')) {
            $table->addColumn('source_type', Types::STRING, [
                'notnull' => true,
                'length'  => 20,
                'default' => 'custom',
            ]);
        }

        if (!$table->hasColumn('source_language')) {
            $table->addColumn('source_language', Types::STRING, [
                'notnull' => true,
                'length'  => 10,
                'default' => 'en-US',
            ]);
        }

        if (!$table->hasColumn('score_threshold')) {
            $table->addColumn('score_threshold', Types::INTEGER, [
                'notnull' => true,
                'default' => 2,
            ]);
        }

        if (!$table->hasColumn('boost_keywords')) {
            $table->addColumn('boost_keywords', Types::TEXT, [
                'notnull' => true,
                'default' => '[]',
            ]);
        }

        if (!$table->hasColumn('exclude_patterns')) {
            $table->addColumn('exclude_patterns', Types::TEXT, [
                'notnull' => true,
                'default' => '["reddit","forum","stackoverflow"]',
            ]);
        }

        if (!$table->hasColumn('tables_table_id')) {
            $table->addColumn('tables_table_id', Types::BIGINT, [
                'notnull' => false,
                'default' => null,
                'unsigned' => true,
            ]);
        }

        if (!$table->hasColumn('tables_campaign_id')) {
            $table->addColumn('tables_campaign_id', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
            ]);
        }

        return $schema;
    }
}
