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
            'would_award' => 0,
        ];

        foreach ($users as $user) {
            $result = $achievementService->evaluateAndAward($user, $dryRun);
            $totals['users_scanned']++;
            $totals['awarded'] += $result['awarded'];
            $totals['skipped_duplicates'] += $result['skipped_duplicates'];
            $totals['would_award'] += $result['would_award'];
        }

        $this->info('Dry run: '.($dryRun ? 'yes' : 'no'));
        $this->info("Users scanned: {$totals['users_scanned']}");

        if ($dryRun) {
            $this->info("Would award: {$totals['would_award']}");
        }

        $this->info("Achievements awarded: {$totals['awarded']}");
        $this->info("Skipped duplicates: {$totals['skipped_duplicates']}");

        return self::SUCCESS;
    }
}
