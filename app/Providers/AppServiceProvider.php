<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configurePasswords();
        $this->configureModels();
        $this->configureDatabase();
        $this->configureUrls();
        $this->configureRateLimiting();
    }

    private function configurePasswords(): void
    {
        Password::defaults(fn () => Password::min(8)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols()
            // Checks the candidate against the haveibeenpwned breach corpus.
            // Production only — it is a network call, and the test suite should
            // not depend on an external service being reachable.
            ->when($this->app->isProduction(), fn (Password $rule) => $rule->uncompromised()));
    }

    private function configureModels(): void
    {
        // Strict mode turns silent bugs into exceptions: accessing an attribute
        // that was never selected, mass-assigning something not fillable, and —
        // most valuable here — triggering a lazy load. Disabled in production so
        // a missed eager load degrades to a slow page rather than a 500 for a
        // student mid-registration.
        $strict = ! $this->app->isProduction();

        Model::shouldBeStrict($strict);
        Model::preventLazyLoading($strict);
        Model::automaticallyEagerLoadRelationships();
    }

    private function configureDatabase(): void
    {
        // Blocks migrate:fresh, db:wipe and migrate:refresh against production.
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    private function configureUrls(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    private function configureRateLimiting(): void
    {
        // Global ceiling for API traffic. Keyed by user where we know them, so
        // one busy client cannot exhaust the quota for an entire campus NAT.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Login is keyed by email *and* IP. Keying on IP alone lets one shared
        // campus gateway lock out a whole building, and lets credential
        // stuffing against a single account rotate source addresses freely.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        // Bulk import and media upload are the two genuinely expensive endpoints.
        RateLimiter::for('heavy', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
