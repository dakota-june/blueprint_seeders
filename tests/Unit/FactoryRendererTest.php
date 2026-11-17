<?php

declare(strict_types=1);

use Dakotajune\Seeders\Support\DraftException;

const CRM = <<<'YAML'
models:
  Contact:
    name: string:60
    email: string:120 unique
    phone: string:20 nullable
    status: enum:pending,active,archived default:pending
    is_vip: boolean default:false
    verified_at: nullable timestamp
    contact_type_id: id
    company_id: id nullable
    parent_contact_id: id:contact nullable
    softDeletes
  ContactType:
    name: string:60 unique
  Company:
    name: string
    owner_contact_id: id:contact
YAML;

it('writes a factory that parses, in the namespace and path Laravel looks for', function () {
    $f = factory(CRM, 'Contact');

    expect($f->path)->toBe('database/factories/ContactFactory.php')
        ->and($f->source)->toContain('namespace Database\Factories;')
        ->and($f->source)->toContain('class ContactFactory extends Factory')
        ->and($f->source)->toContain('@extends Factory<Contact>')
        ->and(lints($f->source))->toBeTrue();
});

it('puts a namespaced model in the matching folder and namespace', function () {
    $f = factory("models:\n  Blog\\Comment:\n    body: text\n", 'Blog\Comment');

    expect($f->path)->toBe('database/factories/Blog/CommentFactory.php')
        ->and($f->source)->toContain('namespace Database\Factories\Blog;')
        ->and(lints($f->source))->toBeTrue();
});

it('gives every column a value suited to its name and type, and leaves out keys and timestamps', function () {
    $s = factory(CRM, 'Contact')->source;

    expect($s)
        ->toContain("'name' => \$this->faker->name()")
        ->toContain("'email' => \$this->faker->unique()->safeEmail()")
        ->toContain("'phone' => \$this->faker->boolean(80) ? \$this->faker->e164PhoneNumber() : null")
        ->toContain("'status' => 'pending'")
        ->toContain("'is_vip' => false")
        ->not->toContain("'id' =>")
        ->not->toContain("'created_at'")
        ->not->toContain("'deleted_at'");
});

it('points a foreign key at the related factory, lazily, and imports the model', function () {
    $s = factory(CRM, 'Contact')->source;

    expect($s)->toContain("'contact_type_id' => ContactType::factory()")
        ->toContain('use ' . '');
    expect(preg_match('/use [\w\\\\]+\\\\ContactType;/', $s))->toBe(1);
});

it('lets a nullable foreign key be null some of the time', function () {
    $f = factory("models:\n  Post:\n    title: string\n    editor_id: id:user nullable\n  User:\n    name: string\n", 'Post');

    expect($f->source)->toContain("'editor_id' => \$this->faker->boolean(80) ? User::factory() : null");
});

it('defaults a nullable self-reference to null and gives a state that makes the parent, one level deep', function () {
    $s = factory(CRM, 'Contact')->source;

    expect($s)->toContain("'parent_contact_id' => null")
        ->toContain('public function withParentContact(): static')
        ->toContain("'parent_contact_id' => Contact::factory()");
});

it('refuses a required self-reference, naming the column and what to do', function () {
    $yaml = "models:\n  Node:\n    parent_id: id:node\n";

    expect(fn () => factory($yaml, 'Node'))->toThrow(DraftException::class, 'Node.parent_id')
        ->and(fn () => factory($yaml, 'Node'))->toThrow(DraftException::class, 'nullable');
});

it('accepts a required self-reference once the draft says how to fill it', function () {
    $yaml = "models:\n  Node:\n    parent_id: id:node fake:numberBetween(1,9)\n";

    expect(factory($yaml, 'Node')->source)->toContain("'parent_id' => \$this->faker->numberBetween(1,9)");
});

it('cuts a loop between two models at the nullable key, with a state to fill it', function () {
    $contact = factory(CRM, 'Contact')->source;
    $company = factory(CRM, 'Company')->source;

    expect($contact)->toContain("'company_id' => null")
        ->toContain('public function withCompany(): static')
        ->and($company)->toContain("'owner_contact_id' => Contact::factory()");
});

it('follows a key that is not id with a closure, so nothing is created until the factory runs', function () {
    $f = factory("models:\n  Note:\n    owner_uuid: uuid foreign:users.uuid\n  User:\n    name: string\n", 'Note');

    expect($f->source)->toContain("'owner_uuid' => fn (): mixed => User::factory()->create()->getAttribute('uuid')");
});

it('makes a state for each enum value, each boolean and each nullable timestamp', function () {
    $s = factory(CRM, 'Contact')->source;

    expect($s)
        ->toContain('public function pending(): static')
        ->toContain('public function active(): static')
        ->toContain('public function archived(): static')
        ->toContain("'status' => 'archived'")
        ->toContain('public function verified(): static')
        ->toContain('public function unverified(): static')
        ->toContain("'verified_at' => now()")
        ->toContain("'verified_at' => null")
        ->and(lints($s))->toBeTrue();
});

it('names the state for a boolean after what it says, with a natural opposite', function () {
    $s = factory("models:\n  User:\n    is_active: boolean default:true\n    is_beta: boolean\n", 'User')->source;

    expect($s)->toContain('public function active(): static')
        ->toContain('public function inactive(): static')
        ->toContain("'is_active' => false")
        ->toContain('public function beta(): static')
        ->toContain('public function notBeta(): static');
});

it('does not write a state for soft deletes: Laravel\'s own trashed() is there', function () {
    expect(factory(CRM, 'Contact')->source)->not->toContain('function trashed');
});

it('prefixes a state with its column when two columns would claim the same name', function () {
    $s = factory("models:\n  Job:\n    status: enum:open,closed\n    outcome: enum:open,lost\n", 'Job')->source;

    expect($s)->toContain('public function statusOpen(): static')
        ->toContain('public function outcomeOpen(): static')
        ->toContain('public function closed(): static')
        ->toContain('public function lost(): static');
});

it('never names a state after a method of Laravel\'s factory', function () {
    $s = factory("models:\n  Job:\n    kind: enum:count,make\n", 'Job')->source;

    expect($s)->toContain('public function kindCount(): static')->toContain('public function kindMake(): static')
        ->and(lints($s))->toBeTrue();
});

it('adds the states written under factories:, with a PHP expression after an equals sign', function () {
    $yaml = CRM . "\nfactories:\n  Contact:\n    states:\n      vip: { is_vip: true, name: Ada }\n      fresh: { verified_at: '=now()' }\n";
    $s = factory($yaml, 'Contact')->source;

    expect($s)->toContain('public function vip(): static')
        ->toContain("'is_vip' => true")
        ->toContain("'name' => 'Ada'")
        ->toContain('public function fresh(): static')
        ->toContain("'verified_at' => now()");
});

it('draws a morph from the models that declare it, both columns from the same parent', function () {
    $yaml = "models:\n  Comment:\n    body: text\n    commentable: morphs\n  Post:\n    title: string\n    relationships:\n      morphMany: Comment\n  Video:\n    title: string\n    relationships:\n      morphMany: Comment\n";
    $s = factory($yaml, 'Comment')->source;

    expect($s)->toContain('$commentable = $this->faker->randomElement([Post::class, Video::class]);')
        ->toContain("'commentable_id' => \$commentable::factory()")
        ->toContain("'commentable_type' => (new \$commentable)->getMorphClass()")
        ->and(lints($s))->toBeTrue();
});

it('warns, naming model and column, when nothing says what a morph may belong to', function () {
    $f = factory("models:\n  Comment:\n    body: text\n    commentable: morphs\n", 'Comment');

    expect($f->notes)->toHaveCount(1)->and($f->notes[0])->toContain('Comment.commentable')
        ->and(lints($f->source))->toBeTrue();
});

it('lets an inline fake: beat factories: which beats the project guess', function () {
    $yaml = "models:\n  Item:\n    sku: string fake:ean13\n    code: string\nfactories:\n  guesses:\n    sku: \"bothify('S-###')\"\n  Item:\n    fake:\n      sku: \"isbn13\"\n      code: \"bothify('C-##')\"\n";
    $s = factory($yaml, 'Item')->source;

    expect($s)->toContain("'sku' => \$this->faker->ean13()")->toContain("'code' => \$this->faker->bothify('C-##')");
});

it('uses a model\'s own chance of null over the project\'s', function () {
    $yaml = "models:\n  Item:\n    note: string nullable\nfactories:\n  Item:\n    null_chance: 0.5\n";

    expect(factory($yaml, 'Item')->source)->toContain('boolean(50)');
});

it('reports what it could not do, each note starting with Model.column', function () {
    $f = factory("models:\n  Job:\n    kind: enum:a,b unique\n    area: point\n", 'Job');

    expect($f->notes)->toHaveCount(2)
        ->and($f->notes[0])->toStartWith('Job.kind')
        ->and($f->notes[1])->toStartWith('Job.area');
});

it('declares the password once per run', function () {
    $s = factory("models:\n  User:\n    password: string\n", 'User')->source;

    expect($s)->toContain('protected static ?string $password = null;')
        ->toContain("'password' => static::\$password ??= bcrypt('password')")
        ->and(lints($s))->toBeTrue();
});

it('leaves docblocks out when asked', function () {
    $s = factory(CRM, 'Contact', docblocks: false)->source;

    expect($s)->not->toContain('/**')->and(lints($s))->toBeTrue();
});

it('writes a factory for a model with no columns of its own', function () {
    $f = factory("models:\n  Tag:\n    timestamps\n", 'Tag');

    expect($f->source)->toContain('return [')->and(lints($f->source))->toBeTrue();
});

it('writes PHP that parses for a whole draft', function () {
    $tree = draft(CRM);

    foreach (array_keys($tree->models()) as $name) {
        expect(lints(factory(CRM, (string) $name)->source))->toBeTrue("{$name}Factory does not parse");
    }
});
