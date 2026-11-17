<?php

declare(strict_types=1);

namespace Dakotajune\Seeders;

use Blueprint\Blueprint;
use Blueprint\Generators\FactoryGenerator as BlueprintFactoryGenerator;
use Blueprint\Generators\SeederGenerator as BlueprintSeederGenerator;
use Blueprint\Lexers\SeederLexer;
use Dakotajune\Seeders\Console\PreviewCommand;
use Dakotajune\Seeders\Generators\FactoryGenerator;
use Dakotajune\Seeders\Generators\SeederGenerator;
use Dakotajune\Seeders\Lexers\SeedPlanLexer;
use Dakotajune\Seeders\Support\Report;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use ReflectionProperty;
use Throwable;

/**
 * Deliberately NOT a DeferrableProvider: Blueprint's own provider already defers Blueprint::class, and two deferred
 * providers cannot both own one service.
 */
final class SeedersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfig();

        // extend() runs when Blueprint is resolved, whatever order the providers loaded in.
        $this->app->extend(Blueprint::class, fn (Blueprint $blueprint, $app): Blueprint => self::extendBlueprint($blueprint, $app->make('files')));
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([__DIR__ . '/../config/seeders.php' => config_path('seeders.php')], 'seeders-config');
        $this->commands([PreviewCommand::class]);

        // What the build could not do goes after Blueprint's own list of files.
        $this->app['events']->listen(CommandFinished::class, function (CommandFinished $event): void {
            $notes = Report::drain();

            if ($event->command === 'blueprint:build' && $notes !== []) {
                $event->output->writeln('');

                foreach ($notes as $note) {
                    $event->output->writeln('<comment>seeders:</comment> ' . $note);
                }
            }
        });
    }

    /**
     * Public and static so tests and other tooling can build the same Blueprint the provider does.
     */
    public static function extendBlueprint(Blueprint $blueprint, Filesystem $files): Blueprint
    {
        self::dropBlueprintSeederLexer($blueprint);

        $blueprint->registerLexer(new SeedPlanLexer());
        $blueprint->swapGenerator(BlueprintFactoryGenerator::class, new FactoryGenerator($files));
        $blueprint->swapGenerator(BlueprintSeederGenerator::class, new SeederGenerator($files));

        return $blueprint;
    }

    /**
     * Blueprint's SeederLexer splits `seeders:` as a comma string, so a list or map there is a TypeError; SeedPlanLexer
     * reads both forms. Blueprint has no way to unregister a lexer, hence the reflection. Should a future Blueprint rename
     * the property, the original stays, and only the map form of `seeders:` stops working.
     */
    private static function dropBlueprintSeederLexer(Blueprint $blueprint): void
    {
        try {
            $property = new ReflectionProperty(Blueprint::class, 'lexers');
            $property->setValue($blueprint, array_values(array_filter(
                $property->getValue($blueprint),
                fn (object $lexer): bool => ! ($lexer::class === SeederLexer::class),
            )));
        } catch (Throwable) {
            // Left as is; see above.
        }
    }

    /**
     * Like mergeConfigFrom(), one level deeper, so a published config that sets `factories.null_chance` keeps `factories.guesses`.
     */
    private function mergeConfig(): void
    {
        $defaults = require __DIR__ . '/../config/seeders.php';
        $current = (array) $this->app['config']->get('seeders', []);

        $this->app['config']->set('seeders', array_replace_recursive($defaults, $current));
    }
}
