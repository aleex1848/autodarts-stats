<?php

namespace App\Providers;

use App\Models\DartMatch;
use App\Observers\DartMatchObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

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
        $this->configureSecureUrls();
        DartMatch::observe(DartMatchObserver::class);
        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('discord', \SocialiteProviders\Discord\Provider::class);
        });
    }

    protected function configureSecureUrls()
    {
        // Determine if HTTPS should be enforced
        $enforceHttps = $this->app->environment(['production', 'local'])
            && !$this->app->runningUnitTests();
 
        // Force HTTPS for all generated URLs
        URL::forceHttps($enforceHttps);
 
        // Ensure proper server variable is set
        if ($enforceHttps) {
            $this->app['request']->server->set('HTTPS', 'on');
        }
 
        // // Set up global middleware for security headers
        // if ($enforceHttps) {
        //     $this->app['router']->pushMiddlewareToGroup('web', function ($request, $next){
        //         $response = $next($request);
 
        //         return $response->withHeaders([
        //             'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        //             'Content-Security-Policy' => "upgrade-insecure-requests",
        //             'X-Content-Type-Options' => 'nosniff'
        //         ]);
        //     });
        // }
    }
}
