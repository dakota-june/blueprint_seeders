<?php

declare(strict_types=1);

use Dakotajune\Seeders\Support\DraftException;

// -- names -----------------------------------------------------------------------------------------------------------

it('chooses a value from the column name as well as the type', function (string $name, string $definition, string $method) {
    expect(guess($definition, $name)->expression)->toContain($method);
})->with([
    'email' => ['email', 'string', 'safeEmail()'],
    'billing_email' => ['billing_email', 'string', 'safeEmail()'],
    'work_phone' => ['work_phone', 'string', 'e164PhoneNumber()'],
    'mobile' => ['mobile', 'string', 'e164PhoneNumber()'],
    'first_name' => ['first_name', 'string', 'firstName()'],
    'last_name' => ['last_name', 'string', 'lastName()'],
    'slug' => ['slug', 'string', 'slug('],
    'website' => ['website', 'string', 'url()'],
    'avatar_url' => ['avatar_url', 'string', 'url()'],
    'timezone' => ['timezone', 'string', 'timezone()'],
    'country' => ['country', 'string', 'country()'],
    'country_code' => ['country_code', 'char:2', 'countryCode()'],
    'currency' => ['currency', 'char:3', 'currencyCode()'],
    'locale' => ['locale', 'string:10', 'locale()'],
    'postal_code' => ['postal_code', 'string:12', 'postcode()'],
    'city' => ['city', 'string', 'city()'],
    'company_name' => ['company_name', 'string', 'company()'],
    'ip_address' => ['ip_address', 'string:45', 'ipv4()'],
    'uuid' => ['uuid', 'string:36', 'uuid()'],
    'title' => ['title', 'string', 'sentence(4)'],
    'bio' => ['bio', 'text', 'paragraph()'],
    'body' => ['body', 'longText', 'paragraphs(3, true)'],
]);

it('does not mistake email_verified_at or phone_id for an email or a phone', function () {
    expect(guess('timestamp', 'email_verified_at')->expression)->toContain('dateTimeBetween')
        ->and(guess('string', 'email_verified_at')->expression)->not->toContain('safeEmail');
});

it('falls back to something sensible for a name it does not know', function () {
    expect(guess('string', 'frobnicator')->expression)->toContain('words(')
        ->and(guess('text', 'frobnicator')->expression)->toContain('paragraph()');
});

it('picks the kind of name from the model: a person, a company or a thing', function () {
    expect(guess('string', 'name', 'User')->expression)->toContain('->name()')
        ->and(guess('string', 'name', 'Company')->expression)->toContain('company()')
        ->and(guess('string', 'name', 'ContactType')->expression)->toContain('words(2, true)')
        ->and(guess('string:20', 'number', 'Phone')->expression)->toContain('e164PhoneNumber()');
});

// -- length ----------------------------------------------------------------------------------------------------------

it('never produces more characters than the column holds', function (string $definition, string $name) {
    $values = sample(guess($definition, $name), 300);
    $length = (int) explode(':', explode(' ', $definition)[0])[1];

    foreach ($values as $value) {
        expect(mb_strlen((string) $value))->toBeLessThanOrEqual($length);
    }
})->with([
    'email in 20' => ['string:20', 'email'],
    'name in 12' => ['string:12', 'first_name'],
    'title in 30' => ['string:30', 'title'],
    'words in 8' => ['string:8', 'frobnicator'],
    'words in 3' => ['string:3', 'frobnicator'],
    'phone in 12' => ['string:12', 'phone'],
    'slug in 20' => ['string:20', 'slug'],
]);

it('keeps an email an email when it has to fit', function () {
    foreach (sample(guess('string:25', 'email'), 200) as $value) {
        expect(filter_var($value, FILTER_VALIDATE_EMAIL))->not->toBeFalse();
    }
});

it('says so when a column is too short for a realistic value', function () {
    $g = guess('string:5', 'email');

    expect($g->notes)->toHaveCount(1)->and($g->notes[0])->toContain('string:5')->and($g->notes[0])->toContain('email');
});

// -- unique, nullable, default ---------------------------------------------------------------------------------------

it('draws unique columns through faker->unique() so values cannot collide', function () {
    $g = guess('string unique', 'email');

    expect($g->expression)->toContain('$this->faker->unique()->safeEmail()');

    $values = sample($g, 800);

    expect(count(array_unique($values)))->toBe(800);
});

it('keeps a unique name unique even when it has to be shortened', function () {
    $g = guess('string:40 unique', 'name', 'User');
    $values = sample($g, 400);

    expect(count(array_unique($values)))->toBe(400);
});

it('warns when a unique column can only ever hold a few values', function () {
    $enum = guess('enum:a,b,c unique', 'kind');
    $bool = guess('boolean unique', 'flag');
    $tiny = guess('unsignedTinyInteger unique', 'slot');

    expect($enum->notes[0])->toContain('3')->and($enum->pool)->toBe(3)
        ->and($bool->notes[0])->toContain('2')
        ->and($tiny->pool)->toBe(256);
});

it('makes a nullable column null some of the time, the same way under the same seed', function () {
    $g = guess('string nullable', 'nickname');

    expect($g->expression)->toContain('boolean(80) ?');

    $first = sample($g, 1000, 7);
    $nulls = count(array_filter($first, fn ($v) => $v === null));

    expect($nulls)->toBeGreaterThan(120)->and($nulls)->toBeLessThan(280)
        ->and(sample($g, 1000, 7))->toBe($first);
});

it('honours the chance of null from the settings, and a chance of zero means never', function () {
    expect(guess('string nullable', 'nickname', settings: ['nullChance' => 0.5])->expression)->toContain('boolean(50) ?')
        ->and(guess('string nullable', 'nickname', settings: ['nullChance' => 0.0])->expression)->not->toContain('?');
});

it('combines unique and nullable with an explicit ternary', function () {
    $g = guess('string nullable unique', 'email');

    expect($g->expression)->toBe('$this->faker->boolean(80) ? $this->faker->unique()->safeEmail() : null');

    $values = array_filter(sample($g, 500), fn ($v) => $v !== null);

    expect(count(array_unique($values)))->toBe(count($values));
});

it('uses the default of a column that is not unique', function (string $definition, string $expected) {
    expect(guess($definition, 'x')->expression)->toBe($expected);
})->with([
    'boolean' => ['boolean default:true', 'true'],
    'false' => ['boolean default:false', 'false'],
    'string' => ['string default:"Joe Dir"', "'Joe Dir'"],
    'quote' => ['string default:"it\'s"', "'it\\'s'"],
    'integer' => ['integer default:5', '5'],
    'decimal' => ['decimal:8,2 default:9.5', '9.5'],
    'enum' => ['enum:pending,active default:pending', "'pending'"],
]);

it('ignores the default of a unique column, which could only be used once', function () {
    expect(guess('string unique default:"Joe Dir"', 'name', 'ContactType')->expression)->toContain('unique()');
});

it('leaves columns the database fills out of the factory', function () {
    expect(guess('timestamp useCurrent', 'seen_at')->skip)->toBeTrue()
        ->and(guess('bigIncrements', 'serial')->skip)->toBeTrue()
        ->and(guess('string', 'id')->skip)->toBeTrue();
});

// -- enums, numbers, dates -------------------------------------------------------------------------------------------

it('draws an enum from its values', function () {
    $g = guess('enum:pending,active,"on hold"', 'status');

    expect($g->expression)->toBe("\$this->faker->randomElement(['pending', 'active', 'on hold'])")
        ->and(array_unique(sample($g, 100)))->toContain('pending', 'active', 'on hold');
});

it('escapes a quote in an enum value', function () {
    expect(guess('enum:"it\'s",b', 'kind')->expression)->toContain("'it\\'s'");
});

it('draws a set as a comma list of its values', function () {
    foreach (sample(guess('set:a,b,c', 'tags'), 50) as $value) {
        foreach (explode(',', $value) as $part) {
            expect(in_array($part, ['a', 'b', 'c'], true))->toBeTrue();
        }
    }
});

it('keeps integers inside what the column type holds', function (string $definition, int $min, int $max) {
    foreach (sample(guess($definition, 'x'), 400) as $value) {
        expect($value)->toBeGreaterThanOrEqual($min)->and($value)->toBeLessThanOrEqual($max);
    }
})->with([
    'unsigned small' => ['unsignedSmallInteger', 0, 65535],
    'unsigned tiny' => ['unsignedTinyInteger', 0, 255],
    'tiny' => ['tinyInteger', 0, 127],
    'integer' => ['integer', 0, 10000],
    'unsigned medium' => ['unsignedMediumInteger', 0, 16777215],
]);

it('gives counts, ratings and ages believable numbers', function () {
    foreach (sample(guess('integer', 'rating'), 100) as $v) {
        expect($v)->toBeGreaterThanOrEqual(1)->and($v)->toBeLessThanOrEqual(5);
    }

    foreach (sample(guess('unsignedInteger', 'age'), 100) as $v) {
        expect($v)->toBeGreaterThanOrEqual(18)->and($v)->toBeLessThanOrEqual(90);
    }
});

it('fits a decimal into its precision and scale', function () {
    foreach (sample(guess('decimal:5,2', 'price'), 300) as $v) {
        expect($v)->toBeLessThanOrEqual(999.99)->and($v)->toBeGreaterThanOrEqual(0.0)
            ->and(abs($v * 100 - round($v * 100)))->toBeLessThan(0.0001);
    }

    foreach (sample(guess('decimal:2,2', 'ratio'), 300) as $v) {
        expect($v)->toBeLessThanOrEqual(0.99);
    }
});

it('keeps latitude and longitude on the planet', function () {
    foreach (sample(guess('decimal:9,6', 'latitude'), 200) as $v) {
        expect(abs($v))->toBeLessThanOrEqual(90.0);
    }

    foreach (sample(guess('decimal:9,6', 'longitude'), 200) as $v) {
        expect(abs($v))->toBeLessThanOrEqual(180.0);
    }
});

it('writes dates the way the column wants them, in the past unless the name says otherwise', function () {
    foreach (sample(guess('date', 'joined_on'), 50) as $v) {
        expect($v)->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    }

    $seen = sample(guess('timestamp', 'seen_at'), 50);
    $now = new DateTime();

    foreach ($seen as $v) {
        expect($v)->toBeInstanceOf(DateTimeInterface::class)->and($v <= $now)->toBeTrue();
    }

    foreach (sample(guess('timestamp', 'expires_at'), 50) as $v) {
        expect($v >= (new DateTime())->modify('-1 minute'))->toBeTrue();
    }
});

it('puts a birth date between 18 and 80 years ago', function () {
    foreach (sample(guess('date', 'birth_date'), 100) as $v) {
        $age = (new DateTime($v))->diff(new DateTime())->y;

        expect($age)->toBeGreaterThanOrEqual(17)->and($age)->toBeLessThanOrEqual(80);
    }
});

it('draws json as an empty array, uuid and ip addresses as such', function () {
    expect(guess('json', 'options')->expression)->toBe('[]')
        ->and(guess('uuid', 'ref')->expression)->toContain('uuid()')
        ->and(guess('ipAddress', 'seen_from')->expression)->toContain('ipv4()')
        ->and(guess('rememberToken', 'remember_token')->expression)->toBe("\$this->faker->regexify('[A-Za-z0-9]{10}')")
        ->and(guess('rememberToken', 'remember_token')->imports)->toBe([]);
});

it('draws only from Faker, so that one seed fixes the data: never Str::random, Str::ulid or random_int', function () {
    foreach (['rememberToken', 'ulid', 'uuid', 'string', 'json', 'boolean', 'ipAddress'] as $type) {
        $expression = guess($type, 'x')->expression;

        expect($expression)->not->toContain('Str::')->not->toContain('random_int')->not->toContain('rand(');
    }
});

it('makes a ulid that the ulid rule accepts, from Faker', function () {
    foreach (sample(guess('ulid', 'ref'), 100) as $value) {
        expect($value)->toMatch('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/');
    }

    expect(sample(guess('ulid', 'ref'), 20, 5))->toBe(sample(guess('ulid', 'ref'), 20, 5));
});

it('hashes a password once per run instead of once per row', function () {
    $g = guess('string', 'password', 'User');

    expect($g->expression)->toBe("static::\$password ??= bcrypt('password')")
        ->and($g->members)->toBe(['password' => 'protected static ?string $password = null;']);
});

it('says when a type has no value it can make', function () {
    $g = guess('point', 'location');

    expect($g->skip)->toBeTrue()->and($g->notes[0])->toContain('point');
});

// -- overrides and guesses -------------------------------------------------------------------------------------------

it('lets an override name a faker method, with arguments', function () {
    $g = guess('integer', 'score', override: 'numberBetween(1,5)');

    expect($g->expression)->toBe('$this->faker->numberBetween(1,5)');
});

it('makes a unique column unique even when a faker override is used', function () {
    expect(guess('string unique', 'handle', override: 'userName')->expression)->toBe('$this->faker->unique()->userName()');
});

it('uses an override that is not a faker method as the PHP it is', function () {
    expect(guess('timestamp', 'trial_at', override: 'now()->addDays(3)')->expression)->toBe('now()->addDays(3)');
});

it('names the model and column, and the nearest method, when a faker method does not exist', function () {
    expect(fn () => guess('string', 'email', 'Contact', override: 'safeEmial'))
        ->toThrow(DraftException::class, 'Contact.email')
        ->and(fn () => guess('string', 'email', 'Contact', override: 'safeEmial'))
        ->toThrow(DraftException::class, 'safeEmail');
});

it('applies the project-wide guesses by pattern, before the built-in names', function () {
    $g = guess('string', 'product_sku', settings: ['guesses' => ['*_sku' => "bothify('SKU-####')"]]);

    expect($g->expression)->toBe("\$this->faker->bothify('SKU-####')");
});

it('lets the column override win over the project-wide guess', function () {
    $g = guess('string', 'product_sku', settings: ['guesses' => ['*_sku' => "bothify('SKU-####')"]], override: 'ean13');

    expect($g->expression)->toBe('$this->faker->ean13()');
});

it('never produces a longer value than its rule says it can, for any rule in the table', function () {
    // The length fitter trusts these numbers: a rule that claims 80 and makes 109 overflows an 80-character column.
    $faker = Faker\Factory::create();
    $faker->seed(20260101);
    $table = (new ReflectionClass(Dakotajune\Seeders\Support\ValueGuesser::class))->getReflectionConstant('TEXT')->getValue();
    $worst = [];

    foreach ($table as [$pattern, $template, $max]) {
        if ($max === null) {
            continue;
        }

        $expression = str_replace('{f}', '$faker', $template);
        $longest = 0;

        for ($i = 0; $i < 3000; $i++) {
            $longest = max($longest, mb_strlen((string) eval("return {$expression};")));
        }

        if ($longest > $max) {
            $worst[] = "{$template} claims {$max} but made {$longest}";
        }
    }

    expect($worst)->toBe([]);
});

it('fits every column name it knows into every string length, and never overflows a unique one without saying so', function () {
    $names = [
        'name', 'first_name', 'last_name', 'full_name', 'username', 'user_name', 'email', 'billing_email', 'phone', 'mobile', 'fax',
        'title', 'slug', 'url', 'website', 'avatar_url', 'company', 'company_name', 'job_title', 'department', 'address', 'street',
        'street_address', 'city', 'state', 'zip', 'postcode', 'country', 'country_code', 'currency', 'locale', 'timezone', 'language',
        'ip', 'ip_address', 'mac', 'user_agent', 'mime_type', 'color', 'hex_color', 'summary', 'excerpt', 'tagline', 'description',
        'notes', 'sku', 'isbn', 'code', 'token', 'api_key', 'password', 'remember_token', 'uuid', 'ulid', 'path', 'filename', 'label',
        'status', 'type', 'category', 'brand', 'iban', 'swift', 'vat_number', 'credit_card', 'license_plate', 'latitude', 'twitter',
    ];

    // Faker cannot keep a value unique and also filter it, so a unique value that is too long is warned about instead.
    $overflow = [];
    $silent = [];

    foreach ($names as $name) {
        foreach ([4, 8, 12, 20, 40, 80] as $length) {
            foreach (['' => false, ' unique' => true] as $suffix => $unique) {
                $guess = guess("string:{$length}{$suffix}", $name);

                if ($guess->skip) {
                    continue;
                }

                try {
                    $values = sample($guess, 120);
                } catch (Throwable) {
                    continue; // a unique pool that runs out is reported as a note, not here
                }

                $key = $name . ":{$length}{$suffix}";

                foreach ($values as $value) {
                    if ($value !== null && mb_strlen((string) $value) > $length) {
                        $unique && $guess->notes !== [] ? null : $silent[$key] = mb_strlen((string) $value) . ' > ' . $length;
                        $overflow[$key] = true;

                        break;
                    }
                }
            }
        }
    }

    expect($silent)->toBe([])
        // The warning is not decoration: unique values do overflow, and the guesser knows it.
        ->and($overflow)->not->toBeEmpty();
});
