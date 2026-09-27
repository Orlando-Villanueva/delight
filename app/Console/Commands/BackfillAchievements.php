<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Console\Command;

class BackfillAchievements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'achievements:backfill
                            {user_id? : The ID of the user to backfill}
                            {--dry-run : Report what would be awarded without writing records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill permanent achievements from existing reading activity';

    /**
     * Execute the console command.
     */
    public function handle(AchievementService $achievementService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $userId = $this->argument('user_id');

        $users = User::query()
            ->when($userId, fn ($query) => $query->whereKey($userId))
            ->whereHas('readingLogs')
            ->lazy();

        $totals = [
            'users_scanned' => 0,
            'awarded' => 0,
            'skipped_duplicates' => 0,
            'updated_completion_dates' => 0,
            'would_award' => 0,
            'would_award_by_key' => [],
            'would_update_completion_dates' => 0,
            'would_update_completion_dates_by_key' => [],
        ];

        foreach ($users as $user) {
            $result = $achievementService->evaluateAndAward($user, $dryRun);
            $totals['users_scanned']++;
            $totals['awarded'] += $result['awarded'];
            $totals['skipped_duplicates'] += $result['skipped_duplicates'];
            $totals['updated_completion_dates'] += $result['updated_completion_dates'];
            $totals['would_award'] += $result['would_award'];
            $totals['would_update_completion_dates'] += $result['would_update_completion_dates'];

            foreach ($result['would_award_by_key'] as $key => $count) {
                $totals['would_award_by_key'][$key] = ($totals['would_award_by_key'][$key] ?? 0) + $count;
            }

            foreach ($result['would_update_completion_dates_by_key'] as $key => $count) {
                $totals['would_update_completion_dates_by_key'][$key] = ($totals['would_update_completion_dates_by_key'][$key] ?? 0) + $count;
            }
        }

        $this->info('Dry run: '.($dryRun ? 'yes' : 'no'));
        $this->info("Users scanned: {$totals['users_scanned']}");

        if ($dryRun) {
            $this->info("Would award: {$totals['would_award']}");
            $this->info("Would update completion dates: {$totals['would_update_completion_dates']}");

            if ($totals['would_award_by_key'] !== []) {
                $this->info('Would award by achievement type:');

                foreach ($totals['would_award_by_key'] as $key => $count) {
                    $this->line("  {$key}: {$count}");
                }
            }

            if ($totals['would_update_completion_dates_by_key'] !== []) {
                $this->info('Would update dates by achievement type:');

                foreach ($totals['would_update_completion_dates_by_key'] as $key => $count) {
                    $this->line("  {$key}: {$count}");
                }
            }
        }

        $this->info("Achievements awarded: {$totals['awarded']}");
        $this->info("Completion dates updated: {$totals['updated_completion_dates']}");
        $this->info("Skipped duplicates: {$totals['skipped_duplicates']}");

        return self::SUCCESS;
    }
}
