<?php
namespace App\Console\Commands;
use App\Models\{Maintenance, User, Warranty};
use App\Notifications\AssetAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

// Schedule in routes/console.php:  Schedule::command('assets:notify')->dailyAt('08:00');
class NotifyAssetAlerts extends Command {
    protected $signature = 'assets:notify';
    protected $description = 'Notify IT admins of expiring warranties and upcoming maintenance';
    public function handle() {
        $admins = User::role(['Super Admin', 'IT Admin'])->get();
        foreach (Warranty::with('asset')->whereBetween('end_date', [now(), now()->addDays(30)])->get() as $w)
            Notification::send($admins, new AssetAlert("Warranty for {$w->asset->asset_tag} expires {$w->end_date}"));
        foreach (Maintenance::with('asset')->whereNull('completed_at')->whereBetween('scheduled_at', [now(), now()->addDays(7)])->get() as $m)
            Notification::send($admins, new AssetAlert("Maintenance for {$m->asset->asset_tag} due {$m->scheduled_at}"));
    }
}
