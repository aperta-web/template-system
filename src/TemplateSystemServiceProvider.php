<?php

namespace Aperta\TemplateSystem;

use Illuminate\Support\ServiceProvider;

class TemplateSystemServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'aperta-template-migrations');

        // Publish Vue pages & component
        $this->publishes([
            __DIR__ . '/../resources/js/Pages/LetterTemplates/' => resource_path('js/Pages/LetterTemplates'),
            __DIR__ . '/../resources/js/Components/GrapesEditor.vue' => resource_path('js/Components/GrapesEditor.vue'),
        ], 'aperta-template-views');

        // Load migrations automatically (optional — comment out to require manual publish)
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function register(): void
    {
        //
    }
}
