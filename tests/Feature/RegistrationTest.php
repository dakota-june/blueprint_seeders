<?php

declare(strict_types=1);

use Blueprint\Blueprint;
use Blueprint\Generators\FactoryGenerator as BlueprintFactoryGenerator;
use Blueprint\Generators\SeederGenerator as BlueprintSeederGenerator;
use Blueprint\Lexers\ModelLexer;
use Blueprint\Lexers\SeederLexer;
use Dakotajune\Seeders\Generators\FactoryGenerator;
use Dakotajune\Seeders\Generators\SeederGenerator;
use Dakotajune\Seeders\Lexers\SeedPlanLexer;
use Dakotajune\Seeders\SeedersServiceProvider;
use Illuminate\Support\Facades\Artisan;

/** @return list<class-string> */
function registered(string $property): array
{
    $list = (new ReflectionProperty(Blueprint::class, $property))->getValue(app(Blueprint::class));

    return array_values(array_map(fn (object $item): string => $item::class, $list));
}

it('swaps Blueprint\'s factory and seeder generators for its own', function () {
    $generators = registered('generators');

    expect($generators)->toContain(FactoryGenerator::class, SeederGenerator::class)
        ->not->toContain(BlueprintFactoryGenerator::class)
        ->not->toContain(BlueprintSeederGenerator::class);
});

it('reads seeders: with its own lexer and keeps Blueprint\'s model lexer', function () {
    $lexers = registered('lexers');

    expect($lexers)->toContain(SeedPlanLexer::class, ModelLexer::class)->not->toContain(SeederLexer::class);
});

it('registers the preview command', function () {
    expect(array_keys(Artisan::all()))->toContain('seeders:preview');
});

it('has its config merged in', function () {
    expect(config('seeders.overwrite'))->toBe('always')
        ->and(config('seeders.factories.null_chance'))->toBe(0.2)
        ->and(config('seeders.demo_environments'))->toBe(['local', 'testing']);
});

it('keeps the keys a partly published config leaves out', function () {
    config(['seeders' => ['factories' => ['null_chance' => 0.5]]]);

    (new SeedersServiceProvider($this->app))->register();

    expect(config('seeders.factories.null_chance'))->toBe(0.5)
        ->and(config('seeders.factories.guesses'))->toBe([])
        ->and(config('seeders.overwrite'))->toBe('always');
});

it('works next to blueprint_models, whichever lexer registered first', function () {
    $this->draft("models:\n  Tag:\n    extends: \\App\\Support\\Base\n    name: string:30 unique\nseeders:\n  reference:\n    Tag:\n      rows: [a, b]\n");

    Artisan::call('blueprint:build', ['draft' => 'draft.yaml', '--only' => 'models,factories,seeders']);

    expect(file_get_contents(base_path('app/Models/Tag.php')))->toContain('extends Base')
        ->and(file_exists(base_path('database/factories/TagFactory.php')))->toBeTrue()
        ->and(file_exists(base_path('database/seeders/Reference/TagSeeder.php')))->toBeTrue();
})->skip(fn () => ! class_exists(Dakotajune\BlueprintModels\BlueprintModelsServiceProvider::class), 'dakotajune/blueprint_models is not installed');
