<?php

declare(strict_types=1);

namespace OCA\WebTrack\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1001Date20260406000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $table = $schema->getTable('wn_monitors');
        if (!$table->hasColumn('use_regex')) {
            $table->addColumn('use_regex', Types::SMALLINT, [
                'notnull' => true,
                'default' => 0,
                'unsigned' => true,
            ]);
        }

        return $schema;
    }
}
