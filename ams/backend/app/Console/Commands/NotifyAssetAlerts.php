<?php

namespace App\Console\Commands;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Warranty;
use App\Notifications\AssetAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

// Schedule in routes/console.php:  Schedule::command('assets:notify')->dailyAt('08:00');
class NotifyAssetAlerts extends Command
{
    protected $signature = 'assets:notify';

    protected $description = 'Notify IT admins of expiring warranties and upcoming maintenance';

    public function handle(): int
    {
        $admins = User::role(['Super Admin', 'IT Admin'])->get();
        foreach (Warranty::with('asset')->whereBetween('end_date', [now()->toDateString(), now()->addDays(30)->toDateString()])->get() as $w) {
            $this->notifyOnce($admins, "Warranty for {$w->asset->asset_tag} expires {$w->end_date}");
        }
        foreach (Maintenance::with('asset')->whereNotIn('status', ['completed', 'cancelled'])
            ->whereBetween('scheduled_at', [now()->toDateString(), now()->addDays(7)->toDateString()])->get() as $m) {
            $recipients = $m->performer ? collect([$m->performer])->merge($admins)->unique('id') : $admins;
            $this->notifyOnce($recipients, "Maintenance for {$m->asset->asset_tag} due {$m->scheduled_at}");
        }

        return self::SUCCESS;
    }

    private function notifyOnce(Collection $users, string $message): void
    {
        foreach ($users as $user) {
            $alreadyNotified = $user->notifications()
                ->where('type', AssetAlert::class)
                ->where('data->message', $message)
                ->exists();

            if (! $alreadyNotified) {
                $user->notify(new AssetAlert($message));
            }
        }
    }
}
