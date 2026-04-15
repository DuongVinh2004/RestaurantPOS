<?php

namespace App\Providers;

use App\Http\Middleware\RequireStaffCapability;
use App\Platform\ApiContract\Services\DatabaseContractInspector;
use App\Modules\Reservations\Application\Services\ReservationCodeGenerator;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ReservationLockService::class);
        $this->app->singleton(ReservationCodeGenerator::class);
        $this->app->singleton(RuntimeSettingService::class);
        $this->app->singleton(DatabaseContractInspector::class);
    }

    public function boot(): void
    {
        if ($this->app->bound('router')) {
            /** @var Router $router */
            $router = $this->app->make('router');
            $router->aliasMiddleware('staff.capability', RequireStaffCapability::class);
        }

        if (! (bool) config('booking.database_contract.enforce_supported_driver', false)) {
            return;
        }

        $defaultConnection = (string) config('database.default', '');
        $driver = (string) config("database.connections.{$defaultConnection}.driver", $defaultConnection);
        $supportedDrivers = array_values(array_filter((array) config('booking.database_contract.supported_drivers', ['mysql'])));

        if (! in_array($driver, $supportedDrivers, true)) {
            throw new RuntimeException(sprintf(
                'Unsupported database driver "%s" for this deployment contract. Supported drivers: %s.',
                $driver,
                implode(', ', $supportedDrivers) ?: 'none'
            ));
        }
    }
}
