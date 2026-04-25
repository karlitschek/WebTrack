<?php

declare(strict_types=1);

namespace OCA\WebTrack\Command;

use OCA\WebTrack\Db\MonitorMapper;
use OCA\WebTrack\Service\CheckService;
use OCA\WebTrack\Service\FeedService;
use OCA\WebTrack\Service\MonitorService;
use OCA\WebTrack\Service\ScoringService;
use OCA\WebTrack\Service\SnippetService;
use OCP\AppFramework\Db\DoesNotExistException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * occ webtrack:check
 *
 * Runs monitor checks immediately without waiting for the background job.
 *
 * Usage:
 *   occ webtrack:check
 *   occ webtrack:check --monitor-id=42
 *   occ webtrack:check --monitor-id=42 --debug
 *   occ webtrack:check --user=admin
 */
class CheckMonitors extends Command {

    public function __construct(
        private MonitorMapper  $monitorMapper,
        private MonitorService $monitorService,
        private CheckService   $checkService,
        private FeedService    $feedService,
        private ScoringService $scoringService,
        private SnippetService $snippetService,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->setName('webtrack:check')
            ->setDescription('Run monitor checks now (same as the background job)')
            ->addOption('monitor-id', 'm', InputOption::VALUE_REQUIRED, 'Only check this monitor ID')
            ->addOption('user',       'u', InputOption::VALUE_REQUIRED, 'Only check monitors belonging to this user')
            ->addOption('debug',      'd', InputOption::VALUE_NONE,     'Show verbose debug output for each check')
            ->addOption('dry-run',    null, InputOption::VALUE_NONE,    'Fetch and parse feeds but do not write any changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $monitorId = $input->getOption('monitor-id');
        $userId    = $input->getOption('user');
        $debug     = (bool) $input->getOption('debug');
        $dryRun    = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $output->writeln('<comment>Dry-run mode: no database writes will occur.</comment>');
        }

        if ($monitorId !== null) {
            // Check a single monitor
            try {
                $monitors = [$this->monitorMapper->find((int) $monitorId)];
            } catch (DoesNotExistException) {
                $output->writeln("<error>Monitor {$monitorId} not found.</error>");
                return Command::FAILURE;
            }
        } elseif ($userId !== null) {
            $monitors = $this->monitorMapper->findAllByUser($userId);
        } else {
            $monitors = $this->monitorMapper->findAllActive();
        }

        if (empty($monitors)) {
            $output->writeln('<info>No monitors found.</info>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('Checking <info>%d</info> monitor(s)…', count($monitors)));

        foreach ($monitors as $monitor) {
            $output->writeln(sprintf(
                "\n<comment>[%d] %s</comment>  user=%s  source=%s  active=%s",
                $monitor->getId(),
                $monitor->getName(),
                $monitor->getUserId(),
                $monitor->getSourceType(),
                $monitor->getIsActive() ? 'yes' : 'no',
            ));

            if ($debug) {
                $output->writeln(sprintf(
                    "  url=%s\n  keyword=%s  regex=%s\n  scoreThreshold=%d\n  boost=%s\n  exclude=%s\n  tablesTableId=%s  campaignId=%s",
                    $monitor->getUrl(),
                    $monitor->getKeyword(),
                    $monitor->getUseRegex() ? 'yes' : 'no',
                    $monitor->getScoreThreshold(),
                    $monitor->getBoostKeywords(),
                    $monitor->getExcludePatterns(),
                    $monitor->getTablesTableId() ?? 'none',
                    $monitor->getTablesCampaignId() ?? 'none',
                ));
            }

            if (!$monitor->getIsActive()) {
                $output->writeln('  <comment>Skipped (paused).</comment>');
                continue;
            }

            // Fetch the URL
            try {
                $output->write('  Fetching…');
                $content = $this->checkService->fetch($monitor->getUrl());
                $isFeed  = $this->feedService->isFeed($content);
                $output->writeln(sprintf(' <info>OK</info> (%d bytes, %s)', strlen($content), $isFeed ? 'feed' : 'page'));
            } catch (\Throwable $e) {
                $output->writeln(' <error>FAILED: ' . $e->getMessage() . '</error>');
                continue;
            }

            if ($isFeed) {
                $entries = $this->feedService->parseEntries($content);
                $output->writeln(sprintf('  Parsed <info>%d</info> feed entries.', count($entries)));

                if ($debug) {
                    foreach ($entries as $i => $entry) {
                        $score = $this->scoringService->score($monitor, $entry['id'], $entry['title'], $entry['content']);
                        $pass  = $score >= $monitor->getScoreThreshold() ? '<info>PASS</info>' : '<comment>SKIP</comment>';
                        $snippet = $this->snippetService->findSnippet(
                            $entry['title'] . ' ' . strip_tags($entry['content']),
                            $monitor->getKeyword(),
                            $monitor->getUseRegex(),
                        );
                        $kwHit = $snippet !== null ? '<info>keyword match</info>' : 'no keyword match';
                        $output->writeln(sprintf(
                            "  [%d] score=%+d %s %s  \"%s\"",
                            $i + 1, $score, $pass, $kwHit,
                            mb_substr($entry['title'], 0, 70),
                        ));
                    }
                }
            } else {
                $text    = $this->checkService->htmlToText($content);
                $snippet = $this->snippetService->findSnippet($text, $monitor->getKeyword(), $monitor->getUseRegex());
                $output->writeln($snippet !== null
                    ? sprintf('  <info>Keyword matched:</info> %s', mb_substr($snippet, 0, 120))
                    : '  Keyword not found on page.');
            }

            if ($dryRun) {
                $output->writeln('  <comment>[dry-run] No changes written.</comment>');
                continue;
            }

            // Delegate the actual write to MonitorService (same path as the background job)
            try {
                $this->monitorService->runCheckForMonitor($monitor);
                $output->writeln('  Check recorded.');
            } catch (\Throwable $e) {
                $output->writeln('  <error>Check error: ' . $e->getMessage() . '</error>');
            }
        }

        $output->writeln("\n<info>Done.</info>");
        return Command::SUCCESS;
    }
}
