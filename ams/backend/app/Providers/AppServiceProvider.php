<?php

namespace App\Providers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn ($user): ?bool => $user->hasRole('Super Admin') ? true : null);

        $trackedTables = [
            'users', 'employees', 'departments', 'assets', 'asset_assignments', 'asset_returns',
            'asset_categories', 'brands', 'suppliers', 'purchases', 'warranties', 'maintenance_records',
            'locations', 'inventory_audits', 'inventory_audit_items', 'attachments',
        ];

        foreach (['created', 'updated', 'deleted'] as $event) {
            Model::$event(function (Model $model) use ($event, $trackedTables): void {
                if ($model instanceof ActivityLog || ! in_array($model->getTable(), $trackedTables, true)) {
                    return;
                }

                $user = request()->user();
                if ($user === null) {
                    return;
                }

                $changes = $event === 'created' ? $model->getAttributes() : $model->getChanges();
                unset($changes['password'], $changes['remember_token']);

                ActivityLog::create([
                    'user_id' => $user->id,
                    'action' => $model->getTable().'.'.$event,
                    'subject_type' => $model->getMorphClass(),
                    'subject_id' => $model->getKey(),
                    'description' => class_basename($model).' '.$event,
                    'properties' => ['changes' => $changes],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });
        }
    }
}
