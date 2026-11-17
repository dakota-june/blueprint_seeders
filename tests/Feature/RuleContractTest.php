<?php

declare(strict_types=1);

use Dakotajune\BlueprintModels\Support\RuleBuilder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

/**
 * What makes "factories honour the model's validation rules" true: values from the factory, checked by Laravel's
 * validator against the rules blueprint_models infers from the same columns. `unique` and `exists` are left out because
 * they need rows; every other rule is checked.
 */
const CONTRACT_DRAFT = <<<'YAML'
models:
  RuleProbe:
    name: string:20
    email: string:60
    email_address: string:30
    website: string:80
    slug: string:40
    phone: string:20
    code: char:6
    bio: text
    notes: text nullable
    age: unsignedTinyInteger
    quantity: integer
    big: unsignedBigInteger
    rating: unsignedSmallInteger
    price: decimal:8,2
    ratio: float
    is_active: boolean
    joined_on: date
    seen_at: nullable timestamp
    stamp: timestamp
    options: json
    status: enum:new,open,closed
    external: uuid
    tag: string:12 unique
YAML;

it('makes values that pass the rules blueprint_models infers for the same columns', function () {
    $this->draft(CONTRACT_DRAFT);
    Artisan::call('blueprint:build', ['draft' => 'draft.yaml', '--only' => 'factories']);
    require_once base_path('database/factories/RuleProbeFactory.php');

    $tree = draft(CONTRACT_DRAFT);
    $rules = RuleBuilder::forModel($tree->models()['RuleProbe'], $tree, true);
    $rules = array_map(
        fn (string $rule): string => implode('|', array_filter(explode('|', $rule), fn (string $part): bool => ! str_starts_with($part, 'unique:') && ! str_starts_with($part, 'exists:'))),
        $rules,
    );

    $factory = new Database\Factories\RuleProbeFactory();

    for ($i = 0; $i < 100; $i++) {
        // A json column is cast to an array, which is what the factory makes; the rules see what Eloquent stores, a string.
        $attributes = array_map(fn (mixed $value): mixed => is_array($value) ? json_encode($value) : $value, $factory->raw());
        $validator = Validator::make($attributes, $rules);

        expect($validator->passes())->toBeTrue(json_encode($validator->errors()->toArray(), JSON_PRETTY_PRINT));
    }
})->skip(fn () => ! class_exists(RuleBuilder::class), 'dakotajune/blueprint_models is not installed');
