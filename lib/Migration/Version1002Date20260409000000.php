<?php

declare(strict_types=1);

namespace OCA\WebTrack\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1002Date20260409000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $table = $schema->getTable('wn_monitors');
        if (!$table->hasColumn('last_found_hash')) {
            $table->addColumn('last_found_hash', Types::STRING, [
                'notnull' => false,
                'length'  => 32,
                'default' => null,
            ]);
        }

        return $schema;
    }
}
