<?php

namespace App\Console\Commands;

use App\Services\ChannelManagerApiTester;
use Illuminate\Console\Command;

class TestChannelManagerApis extends Command
{
    protected $signature = 'cm:test-apis';

    protected $description = 'Run all Channel Manager API connectivity tests';

    public function handle(ChannelManagerApiTester $tester): int
    {
        $this->info('Running Channel Manager API tests...');
        $this->newLine();

        $report = $tester->runAll();

        foreach ($report['results'] as $row) {
            $icon = match ($row['status']) {
                'pass' => '<fg=green>PASS</>',
                'skip' => '<fg=yellow>SKIP</>',
                default => '<fg=red>FAIL</>',
            };

            $code = $row['http_code'] ?? '—';
            $this->line("{$icon}  [{$row['method']}] {$row['name']} (HTTP {$code})");
            $this->line("     {$row['message']}");
        }

        $summary = $report['summary'];
        $this->newLine();
        $this->info("Done: {$summary['passed']} passed, {$summary['failed']} failed, {$summary['skipped']} skipped ({$summary['total']} total)");

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
