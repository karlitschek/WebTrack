<?php

declare(strict_types=1);

namespace OCA\WebTrack\Command;

use OCA\WebTrack\Db\MonitorMapper;
use OCA\WebTrack\Service\DomainLookupService;
use OCA\WebTrack\Service\TablesRowBuilder;
use OCA\WebTrack\Service\TablesService;
use OCP\AppFramework\Db\DoesNotExistException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * occ webtrack:test-tables
 *
 * Dry-runs Tables row insertion for a monitor against a sample article URL.
 * Shows what would be written without actually inserting anything.
 *
 * Usage:
 *   occ webtrack:test-tables --monitor-id=42 --url=https://heise.de/article
 *   occ webtrack:test-tables --monitor-id=42 --url=https://heise.de/article --insert
 *   occ webtrack:test-tables --list-tables
 */
class TestTables extends Command {

    public function __construct(
        private MonitorMapper       $monitorMapper,
        private TablesService       $tablesService,
        private TablesRowBuilder    $tablesRowBuilder,
        private DomainLookupService $domainLookup,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->setName('webtrack:test-tables')
            ->setDescription('Dry-run Nextcloud Tables row insertion for a monitor')
            ->addOption('monitor-id',   'm', InputOption::VALUE_REQUIRED, 'Monitor ID to test')
            ->addOption('url',          null, InputOption::VALUE_REQUIRED, 'Sample article URL to use', 'https://heise.de/newsticker/example-article')
            ->addOption('title',        null, InputOption::VALUE_REQUIRED, 'Sample article title', 'Example article title')
            ->addOption('pub-date',     null, InputOption::VALUE_REQUIRED, 'Sample publish date (YYYY-MM-DD)', date('Y-m-d'))
            ->addOption('insert',       null, InputOption::VALUE_NONE,     'Actually insert the row (default: dry-run)')
            ->addOption('list-tables',  null, InputOption::VALUE_NONE,     'List all Tables accessible to the system')
            ->addOption('check-dup',    null, InputOption::VALUE_NONE,     'Check whether a row for this URL already exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if ($input->getOption('list-tables')) {
            return $this->listTables($output);
        }

        $monitorId = $input->getOption('monitor-id');
        if ($monitorId === null) {
            $output->writeln('<error>--monitor-id is required (or use --list-tables).</error>');
            return Command::FAILURE;
        }

        try {
            $monitor = $this->monitorMapper->find((int) $monitorId);
        } catch (DoesNotExistException) {
            $output->writeln("<error>Monitor {$monitorId} not found.</error>");
            return Command::FAILURE;
        }

        $tableId = $monitor->getTablesTableId();
        if ($tableId === null) {
            $output->writeln('<error>This monitor has no Tables table configured. Set tablesTableId first.</error>');
            return Command::FAILURE;
        }

        $url     = (string) $input->getOption('url');
        $title   = (string) $input->getOption('title');
        $pubDate = (string) $input->getOption('pub-date');

        $output->writeln(sprintf(
            "\n<comment>Monitor #%d:</comment> %s  →  table <info>%d</info>  campaign=%s",
            $monitor->getId(),
            $monitor->getName(),
            $tableId,
            $monitor->getTablesCampaignId() ?? 'none',
        ));
        $output->writeln(sprintf("  article url   : %s", $url));
        $output->writeln(sprintf("  title         : %s", $title));
        $output->writeln(sprintf("  pubDate       : %s", $pubDate));

        // Domain lookup debug
        $output->writeln("\n<comment>Domain lookup:</comment>");
        $output->writeln(sprintf("  country_id    : %d", $this->domainLookup->getCountryId($url)));
        $output->writeln(sprintf("  tier_id       : %d", $this->domainLookup->getTierId($url)));
        $output->writeln(sprintf("  category_id   : %d", $this->domainLookup->getCategoryId($url, $title)));

        // Fetch column schema
        $output->writeln("\n<comment>Fetching column schema for table {$tableId}…</comment>");
        try {
            $columns = $this->tablesService->getColumns($tableId);
        } catch (\Throwable $e) {
            $output->writeln('<error>Could not fetch columns: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        $output->writeln(sprintf("  Found <info>%d</info> columns.", count($columns)));
        foreach ($columns as $col) {
            $output->writeln(sprintf("  [%d] %s (%s)", $col['id'], $col['title'], $col['type']));
        }

        // Build row payload
        $data = $this->tablesRowBuilder->build(
            columns:    $columns,
            monitor:    $monitor,
            entryUrl:   $url,
            title:      $title,
            pubDate:    $pubDate,
            campaignId: $monitor->getTablesCampaignId(),
        );

        $output->writeln("\n<comment>Row payload that would be inserted:</comment>");
        // Map column IDs back to titles for readability
        $colById = [];
        foreach ($columns as $col) {
            $colById[$col['id']] = $col['title'];
        }
        foreach ($data as $cell) {
            $output->writeln(sprintf(
                "  [%d] %-20s = %s",
                $cell['columnId'],
                $colById[$cell['columnId']] ?? '?',
                is_scalar($cell['value']) ? $cell['value'] : json_encode($cell['value']),
            ));
        }

        // Duplicate check
        if ($input->getOption('check-dup') || $input->getOption('insert')) {
            $headlineCol = null;
            foreach ($columns as $col) {
                if (strcasecmp($col['title'], 'headline') === 0) {
                    $headlineCol = $col;
                    break;
                }
            }
            if ($headlineCol !== null) {
                $output->writeln("\n<comment>Checking for duplicate (Headline contains URL)…</comment>");
                try {
                    $exists = $this->tablesService->rowExistsForUrl($tableId, $headlineCol['id'], $url);
                    $output->writeln($exists
                        ? '  <comment>Duplicate found — row already exists for this URL.</comment>'
                        : '  <info>No duplicate — URL not yet in table.</info>');
                    if ($exists && !$input->getOption('insert')) {
                        return Command::SUCCESS;
                    }
                } catch (\Throwable $e) {
                    $output->writeln('<error>Duplicate check failed: ' . $e->getMessage() . '</error>');
                }
            }
        }

        // Optionally insert
        if ($input->getOption('insert')) {
            $output->writeln("\n<comment>Inserting row…</comment>");
            try {
                $row = $this->tablesService->insertRow($tableId, $data);
                $output->writeln(sprintf('  <info>Row inserted! id=%s</info>', $row['id'] ?? '?'));
            } catch (\Throwable $e) {
                $output->writeln('<error>Insert failed: ' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }
        } else {
            $output->writeln("\n<comment>[Dry-run] Nothing inserted. Use --insert to actually write.</comment>");
        }

        return Command::SUCCESS;
    }

    private function listTables(OutputInterface $output): int {
        $output->writeln('<comment>Fetching tables from Nextcloud Tables API…</comment>');
        try {
            $tables = $this->tablesService->listTables();
        } catch (\Throwable $e) {
            $output->writeln('<error>Failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        if (empty($tables)) {
            $output->writeln('<info>No tables found (Tables app not installed or no tables created).</info>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf("\nFound <info>%d</info> table(s):\n", count($tables)));
        foreach ($tables as $tbl) {
            $output->writeln(sprintf(
                '  [%4d]  %s %s',
                $tbl['id'],
                $tbl['emoji'] ?? '  ',
                $tbl['title'],
            ));
        }
        return Command::SUCCESS;
    }
}
