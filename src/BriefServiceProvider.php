<?php


namespace AlwaysBlank\Brief;


use Illuminate\Support\ServiceProvider;

class BriefServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('brief', function() {
            return new EmptyBrief();
        });
    }
}