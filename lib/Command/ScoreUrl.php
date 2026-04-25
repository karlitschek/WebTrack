<?php

declare(strict_types=1);

namespace OCA\WebTrack\Command;

use OCA\WebTrack\Db\MonitorMapper;
use OCA\WebTrack\Service\ScoringService;
use OCP\AppFramework\Db\DoesNotExistException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * occ webtrack:score-url
 *
 * Shows the relevance score a feed entry would receive for a given monitor.
 * Useful for tuning boost keywords and exclude patterns.
 *
 * Usage:
 *   occ webtrack:score-url --monitor-id=42 --url=https://reddit.com/r/privacy
 *   occ webtrack:score-url --monitor-id=42 --url=https://heise.de/article --title="Nextcloud Hub 9" --content="open source privacy"
 */
class ScoreUrl extends Command {

    public function __construct(
        private MonitorMapper  $monitorMapper,
        private ScoringService $scoringService,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->setName('webtrack:score-url')
            ->setDescription('Show the relevance score for a URL against a monitor\'s filter rules')
            ->addOption('monitor-id', 'm', InputOption::VALUE_REQUIRED, 'Monitor ID whose boost/exclude lists to use')
            ->addOption('url',        null, InputOption::VALUE_REQUIRED, 'Article URL to score')
            ->addOption('title',      null, InputOption::VALUE_OPTIONAL, 'Article title', '')
            ->addOption('content',    null, InputOption::VALUE_OPTIONAL, 'Article body/description text', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $monitorId = $input->getOption('monitor-id');
        $url       = (string) $input->getOption('url');

        if ($monitorId === null || $url === '') {
            $output->writeln('<error>Both --monitor-id and --url are required.</error>');
            return Command::FAILURE;
        }

        try {
            $monitor = $this->monitorMapper->find((int) $monitorId);
        } catch (DoesNotExistException) {
            $output->writeln("<error>Monitor {$monitorId} not found.</error>");
            return Command::FAILURE;
        }

        $title   = (string) $input->getOption('title');
        $content = (string) $input->getOption('content');

        $output->writeln(sprintf("\n<comment>Monitor #%d:</comment> %s", $monitor->getId(), $monitor->getName()));
        $output->writeln(sprintf("  scoreThreshold  : <info>%d</info>", $monitor->getScoreThreshold()));
        $output->writeln(sprintf("  boost keywords  : %s", $monitor->getBoostKeywords()));
        $output->writeln(sprintf("  exclude patterns: %s", $monitor->getExcludePatterns()));
        $output->writeln(sprintf("\n  url     : %s", $url));
        $output->writeln(sprintf("  title   : %s", $title ?: '(empty)'));
        $output->writeln(sprintf("  content : %s", $content ? mb_substr($content, 0, 120) . '…' : '(empty)'));

        $score     = $this->scoringService->score($monitor, $url, $title, $content);
        $threshold = $monitor->getScoreThreshold();
        $relevant  = $score >= $threshold;

        // Breakdown
        $boostKeywords   = $monitor->getBoostKeywordsArray();
        $excludePatterns = $monitor->getExcludePatternsArray();
        $haystack        = mb_strtolower($title . ' ' . strip_tags($content));
        $urlLower        = mb_strtolower($url);

        $output->writeln("\n<comment>Score breakdown:</comment>");
        foreach ($boostKeywords as $kw) {
            if ($kw !== '' && str_contains($haystack, mb_strtolower($kw))) {
                $output->writeln(sprintf("  <info>+1</info>  boost keyword matched: \"%s\"", $kw));
            }
        }
        foreach ($excludePatterns as $pat) {
            if ($pat !== '') {
                $p = mb_strtolower($pat);
                if (str_contains($urlLower, $p) || str_contains($haystack, $p)) {
                    $output->writeln(sprintf("  <error>-2</error>  exclude pattern matched: \"%s\"", $pat));
                }
            }
        }

        $scoreTag = $relevant ? "<info>{$score}</info>" : "<comment>{$score}</comment>";
        $verdict  = $relevant ? "<info>RELEVANT</info> (score {$score} >= threshold {$threshold})" : "<comment>FILTERED OUT</comment> (score {$score} < threshold {$threshold})";
        $output->writeln("\n  Total score : {$scoreTag}");
        $output->writeln("  Verdict     : {$verdict}");

        return Command::SUCCESS;
    }
}
