<?php

declare(strict_types=1);

namespace Dakotajune\Seeders\Support;

/**
 * Chooses the PHP expression that gives a column a believable value.
 *
 * In this order: an override written for the column (`fake:` in the draft); the column's default; the project-wide
 * guesses; the built-in rules by name (and by the kind of model, for `name`); the built-in rules by type. The value is
 * then fitted to the column's length, drawn through `unique()` for a unique column, and made null some of the time for a
 * nullable one. Foreign keys and morphs are not decided here: they need the other models.
 */
final class ValueGuesser
{
    private const PERSON = '/(User|Customer|Contact|Person|Employee|Author|Member|Client|Lead|Patient|Student|Teacher|Owner|Admin|Guest|Visitor|Subscriber|Candidate|Applicant)$/';

    private const COMPANY = '/(Company|Organi[sz]ation|Vendor|Supplier|Brand|Business|Employer|Manufacturer|Agency|Firm)$/';

    private const POSITIVE = '/^(is_|has_|can_)?(active|enabled|published|visible|verified|public|approved|confirmed|featured|available|subscribed)$/';

    private const SEARCH_KEY = '/(^|_)(api_key|access_token|token|secret|hash|checksum)$/';

    /**
     * Text by column name. Each row: pattern, template, longest typical value, shortest believable value, may be cut
     * short, what it is. First match wins, so the narrow names come before the broad ones (`country_code` before `code`).
     */
    private const TEXT = [
        ['/(^|_)e?mail(_address)?$/', '{f}->safeEmail()', 60, 8, false, 'email address'],
        ['/(^|_)(phone|mobile|cell|telephone|fax)(_number|_no)?$/', '{f}->e164PhoneNumber()', 16, 8, false, 'phone number'],
        ['/^(first|given|fore|middle)_?name$/', '{f}->firstName()', 20, 2, true, 'first name'],
        ['/^(last|family|sur)_?name$/', '{f}->lastName()', 25, 2, true, 'last name'],
        ['/^(full|display|contact|person|customer|client|employee|author|owner|recipient|sender)_?name$/', '{f}->name()', 45, 3, true, 'name'],
        ['/^(user_?name|login|handle|nick_?name)$/', '{f}->userName()', 30, 3, true, 'user name'],
        ['/(^|_)slug$/', '{f}->slug(3)', 70, 3, false, 'slug'],
        ['/(^|_)(domain|hostname)$/', '{f}->domainName()', 40, 4, false, 'domain name'],
        ['/(^|_)(url|uri|link|website|homepage)$/', '{f}->url()', 140, 12, false, 'URL'],
        ['/(^|_)(avatar|image|photo|picture|logo|thumbnail|banner|cover)(_path|_file|_name)?$/', "'images/' . {f}->uuid() . '.jpg'", 47, 12, false, 'image path'],
        ['/(^|_)ip(_address)?$/', '{f}->ipv4()', 15, 7, false, 'IP address'],
        ['/(^|_)mac(_address)?$/', '{f}->macAddress()', 17, 17, false, 'MAC address'],
        ['/(^|_)(uuid|guid)$/', '{f}->uuid()', 36, 36, false, 'UUID'],
        ['/^(address|address_?(line)?_?1|street|street_address)$/', '{f}->streetAddress()', 50, 5, true, 'street address'],
        ['/^(address_?(line)?_?2|suite|apartment|unit)$/', '{f}->secondaryAddress()', 20, 3, true, 'address line'],
        ['/^(city|town)$/', '{f}->city()', 30, 2, true, 'city'],
        ['/^state_(code|abbr)$/', '{f}->stateAbbr()', 2, 2, false, 'state code'],
        ['/^(state|province|region|county)$/', '{f}->state()', 25, 2, true, 'state'],
        ['/^country_?(code|iso)$/', '{f}->countryCode()', 2, 2, false, 'country code'],
        ['/^country$/', '{f}->country()', 60, 3, true, 'country'],
        ['/^(zip|zip_?code|postal_?code|post_?code)$/', '{f}->postcode()', 10, 3, false, 'postal code'],
        ['/(^|_)(timezone|time_zone|tz)$/', '{f}->timezone()', 32, 3, false, 'time zone'],
        ['/(^|_)locale$/', '{f}->locale()', 8, 2, false, 'locale'],
        ['/(^|_)(language|lang)(_code)?$/', '{f}->languageCode()', 2, 2, false, 'language code'],
        ['/(^|_)currency(_code)?$/', '{f}->currencyCode()', 3, 3, false, 'currency code'],
        ['/(^|_)colou?r$/', '{f}->hexColor()', 7, 7, false, 'colour'],
        ['/^(company|organi[sz]ation|employer|business|vendor|supplier|brand|manufacturer)(_name)?$/', '{f}->company()', 40, 3, true, 'company name'],
        ['/^(job_?title|position|occupation)$/', '{f}->jobTitle()', 100, 3, true, 'job title'],
        ['/(^|_)mime(_type)?$/', '{f}->mimeType()', 90, 9, false, 'MIME type'],
        ['/(^|_)user_?agent$/', '{f}->userAgent()', 180, 20, true, 'user agent'],
        ['/(^|_)iban$/', '{f}->iban()', 34, 15, false, 'IBAN'],
        ['/^(title|headline|heading|subject|caption)$/', "rtrim({f}->sentence(4), '.')", 70, 5, true, 'title'],
        ['/^(summary|excerpt|blurb|tagline)$/', '{f}->sentence(10)', 200, 10, true, 'summary'],
        ['/(^|_)(sku|serial(_number)?|reference|ref|reference_number|order_number|invoice_number|code)$/', "strtoupper({f}->bothify('???-#####'))", 9, 9, false, 'code'],
    ];

    private const BODY = '/^(description|bio|biography|about|body|content|notes?|comments?|message|details?|remarks?)$/';

    /** Whole-number columns by name: pattern, lowest, highest. */
    private const NUMBERS = [
        ['/^age$/', 18, 90],
        ['/(^|_)(rating|stars|score|grade)$/', 1, 5],
        ['/(^|_)(percent(age)?|progress|completion)$/', 0, 100],
        ['/(^|_)priority$/', 1, 5],
        ['/(^|_)(duration|minutes|seconds|hours|days)$/', 1, 120],
        ['/(_cents|_pence)$/', 100, 100000],
        ['/(^|_)(price|cost|amount|total|subtotal|fee|rate|salary|balance|budget|revenue|tax|discount|payment)$/', 1, 1000],
        ['/((^|_)(quantity|qty|stock|inventory|units|views|visits|likes|downloads|count)|_count)$/', 0, 100],
        ['/(^|_)(position|sort(_order)?|order|rank|ordering|display_order)$/', 0, 100],
    ];

    private const BIRTH = '/(^|_)(birth_?day|birth_?date|dob|born_(at|on))$/';

    private const FUTURE = '/^(expires?|expiry|expiration|due|ends?|valid_until|until|scheduled|starts?|begins?|next_\w+|remind\w*|renews?|trial_ends)(_(at|on|date|time))?$|_until$/';

    public function __construct(private readonly Settings $settings = new Settings()) {}

    public function guess(ColumnProfile $c, string $model, ?string $override = null): Guess
    {
        $where = $model . '.' . $c->name;

        if ($c->isKey() || $c->dbFilled) {
            return Guess::skipped();
        }

        if ($c->isSpatial()) {
            return Guess::skipped($c->nullable ? [] : [
                "{$where}: {$c->type} has no value this package can make. Make the column nullable, or give it one under factories: {$model}: fake:.",
            ]);
        }

        if ($override !== null) {
            return $this->finish($this->expressionRule($override, $c, $where), $c, $where, nullable: false);
        }

        $literal = $this->literalDefault($c);

        if ($literal !== null) {
            return new Guess($literal);
        }

        foreach ($this->settings->guesses as $pattern => $expression) {
            if (fnmatch((string) $pattern, $c->name, FNM_CASEFOLD)) {
                return $this->finish($this->expressionRule($expression, $c, $where), $c, $where, nullable: true);
            }
        }

        $rule = $this->builtIn($c, $model);

        if ($rule === null) {
            return $this->finish(
                new Rule('{f}->word()'),
                $c,
                $where,
                nullable: true,
                notes: ["{$where}: no built-in rule for {$c->type}; using a random word. Set one with fake: in the draft."],
            );
        }

        return $this->finish($rule, $c, $where, nullable: true);
    }

    // -- the pipeline after a rule is chosen --------------------------------------------------------------------------

    /**
     * @param  list<string>  $notes
     */
    private function finish(Rule $rule, ColumnProfile $c, string $where, bool $nullable, array $notes = []): Guess
    {
        [$rule, $fitNotes] = $this->fit($rule, $c, $where);
        $notes = [...$notes, ...$fitNotes];

        if ($c->unique && $rule->pool !== null && $rule->pool < 1_000_000) {
            $notes[] = "{$where}: unique, but only {$rule->pool} different values exist, so at most {$rule->pool} rows can be created.";
        }

        $expression = $rule->render($c->unique);

        if ($nullable && $c->nullable && $this->settings->nullChance > 0) {
            $present = (int) round((1 - $this->settings->nullChance) * 100);
            $expression = $present <= 0 ? 'null' : '$this->faker->boolean(' . $present . ') ? ' . $expression . ' : null';
        }

        return new Guess($expression, false, $rule->imports, $rule->members, $notes, $rule->pool);
    }

    /**
     * Makes the value fit the column's length: untouched when it already does; random letters when the column is too
     * short for a believable value; cut short when a shorter name is still a name; otherwise redrawn until it fits.
     *
     * @return array{0: Rule, 1: list<string>}
     */
    private function fit(Rule $rule, ColumnProfile $c, string $where): array
    {
        $n = $c->length;

        if ($n === null || $rule->max === null || $rule->max <= $n) {
            return [$rule, []];
        }

        if ($n < $rule->min) {
            $k = min($n, 12);
            $note = "{$where}: string:{$n} is shorter than a realistic {$rule->what} ({$rule->min}+ characters); using random letters.";

            return [new Rule("strtolower({f}->lexify('" . str_repeat('?', $k) . "'))", $k, pool: 26 ** $k), [$note]];
        }

        if ($rule->truncatable) {
            $note = $c->unique ? ["{$where}: values are cut to {$n} characters after uniqueness is checked; two could in theory become equal."] : [];

            return [$rule->withTemplate("rtrim(mb_substr({$rule->template}, 0, {$n}), ' .,')", $n), $note];
        }

        if ($c->unique) {
            return [$rule, ["{$where}: a unique {$rule->what} is not cut to string:{$n} (Faker cannot keep a value unique and fit it in one go), so it may be longer than that. Raise the length, or give the column shorter values with fake:."]];
        }

        $filter = '{f}->valid(fn (string $v): bool => mb_strlen($v) <= ' . $n . ')';
        $at = strpos($rule->template, '{f}');
        $template = $at === false ? $rule->template : substr_replace($rule->template, $filter, $at, 3);

        return [$rule->withTemplate($template, $n), []];
    }

    // -- overrides and project-wide guesses ---------------------------------------------------------------------------

    /**
     * `numberBetween(1,5)` or `userName` is a faker method; anything else (`now()->addDays(3)`, `Str::random(8)`) is PHP
     * and is used as written. A name that is neither a faker method nor a PHP function is a typo.
     */
    private function expressionRule(string $expression, ColumnProfile $c, string $where): Rule
    {
        $expression = trim($expression);

        if (preg_match('/^([A-Za-z_]\w*)(\(.*\))?$/s', $expression, $m) === 1) {
            $method = $m[1];

            if ($this->settings->catalog()->has($method)) {
                return new Rule('{f}->' . $method . ($m[2] ?? '()'));
            }

            if (! function_exists($method)) {
                $suggestion = $this->settings->catalog()->suggest($method);

                throw DraftException::at(
                    $where,
                    "unknown Faker method \"{$method}\".",
                    $suggestion !== null ? "Did you mean \"{$suggestion}\"?" : 'Check the spelling, or write a PHP expression instead.',
                );
            }
        }

        return new Rule($expression);
    }

    // -- defaults -----------------------------------------------------------------------------------------------------

    /**
     * The PHP literal for a column's `default:`, or null when there is none to use: a unique column cannot repeat its
     * default, and a date's default is the database's business.
     */
    private function literalDefault(ColumnProfile $c): ?string
    {
        $d = $c->default;

        if ($d === null || strtolower($d) === 'null' || $c->unique || $c->isTemporal()) {
            return null;
        }

        if ($c->base() === 'boolean') {
            return match (strtolower($d)) {
                'true', '1' => 'true',
                'false', '0' => 'false',
                default => null,
            };
        }

        if ($c->isInteger() || $c->isDecimal()) {
            return is_numeric($d) ? var_export($d + 0, true) : null;
        }

        if ($c->isTextual() || in_array($c->base(), ['enum', 'set'], true)) {
            return self::quote($d);
        }

        return null;
    }

    public static function quote(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }

    // -- the built-in rules -------------------------------------------------------------------------------------------

    private function builtIn(ColumnProfile $c, string $model): ?Rule
    {
        $base = $c->base();

        return match (true) {
            $base === 'enum' => $this->enum($c),
            $base === 'set' => $this->set($c),
            $c->isTextual() => $this->textual($c, $model),
            $c->isInteger() => $this->integer($c),
            $c->isDecimal() => $this->decimal($c),
            $base === 'boolean' => new Rule(preg_match(self::POSITIVE, $c->name) === 1 ? '{f}->boolean(80)' : '{f}->boolean()', pool: 2),
            $c->isTemporal() => $this->temporal($c),
            default => match ($base) {
                'json', 'jsonb' => new Rule('[]'),
                'uuid' => new Rule('{f}->uuid()', 36, 36, what: 'UUID'),
                // Faker draws from PHP's seeded generator, Str::ulid() and Str::random() do not: one seed has to fix all of it.
                'ulid' => new Rule("strtoupper({f}->regexify('[0-7][0-9a-hjkmnp-tv-z]{25}'))", 26, 26, what: 'ULID'),
                'ipaddress' => new Rule('{f}->ipv4()'),
                'macaddress' => new Rule('{f}->macAddress()'),
                'binary' => new Rule('{f}->sha256()'),
                'remembertoken' => new Rule("{f}->regexify('[A-Za-z0-9]{10}')"),
                default => null,
            },
        };
    }

    private function enum(ColumnProfile $c): ?Rule
    {
        if ($c->values === []) {
            return null;
        }

        return new Rule('{f}->randomElement(' . self::quoted($c->values) . ')', pool: count($c->values));
    }

    private function set(ColumnProfile $c): ?Rule
    {
        if ($c->values === []) {
            return null;
        }

        return new Rule("implode(',', {f}->randomElements(" . self::quoted($c->values) . ', {f}->numberBetween(1, ' . count($c->values) . ')))');
    }

    /** @param list<string> $values */
    private static function quoted(array $values): string
    {
        return '[' . implode(', ', array_map(self::quote(...), $values)) . ']';
    }

    private function textual(ColumnProfile $c, string $model): Rule
    {
        $name = $c->name;
        $short = self::short($model);
        $base = $c->base();

        if ($name === 'name') {
            return match (true) {
                preg_match(self::PERSON, $short) === 1 => new Rule('{f}->name()', 45, 3, true, 'name'),
                preg_match(self::COMPANY, $short) === 1 => new Rule('{f}->company()', 40, 3, true, 'company name'),
                default => new Rule('ucwords({f}->words(2, true))', 30, 3, true, 'name'),
            };
        }

        if ($name === 'number' && preg_match('/Phone$/i', $short) === 1) {
            return new Rule('{f}->e164PhoneNumber()', 16, 8, false, 'phone number');
        }

        if (preg_match('/(^|_)password(_hash)?$/', $name) === 1 && ($c->length === null || $c->length >= 60)) {
            return new Rule("static::\$password ??= bcrypt('password')", members: ['password' => 'protected static ?string $password = null;']);
        }

        if (preg_match(self::SEARCH_KEY, $name) === 1) {
            return new Rule('{f}->sha256()', 64, 8, false, 'token');
        }

        foreach (self::TEXT as [$pattern, $template, $max, $min, $truncatable, $what]) {
            if (preg_match($pattern, $name) === 1) {
                return new Rule($template, $max, $min, $truncatable, $what);
            }
        }

        if ($base === 'text' || $base === 'tinytext') {
            return new Rule('{f}->paragraph()');
        }

        if ($base === 'mediumtext' || $base === 'longtext') {
            return new Rule('{f}->paragraphs(3, true)');
        }

        if (preg_match(self::BODY, $name) === 1) {
            return new Rule('{f}->sentence()', 90, 10, true, 'sentence');
        }

        return new Rule('{f}->words(3, true)', 45, 3, true);
    }

    private function integer(ColumnProfile $c): Rule
    {
        [$min, $max] = $c->range();

        if (preg_match('/(^|_)year$/', $c->name) === 1) {
            return new Rule("{f}->numberBetween(1990, (int) date('Y'))");
        }

        $low = 0;
        $high = 10000;

        foreach (self::NUMBERS as [$pattern, $lo, $hi]) {
            if (preg_match($pattern, $c->name) === 1) {
                [$low, $high] = [$lo, $hi];

                break;
            }
        }

        $high = min($high, $max);
        $low = max(min($low, $high), $min);

        return new Rule("{f}->numberBetween({$low}, {$high})", pool: $high - $low + 1);
    }

    private function decimal(ColumnProfile $c): Rule
    {
        $scale = $c->scale ?? 2;
        $precision = $c->precision ?? 8;
        $ceiling = 10 ** max($precision - $scale, 0) - 10 ** -$scale;

        if (preg_match('/(^|_)(lat|latitude)$/', $c->name) === 1 || preg_match('/(^|_)(lng|lon|long|longitude)$/', $c->name) === 1) {
            $limit = preg_match('/lat/', $c->name) === 1 ? 90 : 180;
            $limit = min($limit, (int) floor($ceiling));

            return new Rule("round({f}->" . (preg_match('/lat/', $c->name) === 1 ? 'latitude' : 'longitude') . "(-{$limit}, {$limit}), {$scale})");
        }

        [$low, $high] = match (true) {
            preg_match('/(^|_)(percent(age)?|progress|completion)$/', $c->name) === 1 => [0, 100],
            preg_match('/(^|_)(rating|stars|score|grade)$/', $c->name) === 1 => [1, 5],
            preg_match('/(^|_)(price|cost|amount|total|subtotal|fee|rate|salary|balance|budget|revenue|tax|discount|payment)$/', $c->name) === 1 => [1, 1000],
            default => [0, 1000],
        };

        $high = min($high, $ceiling);
        $low = min($low, $high);

        return new Rule('{f}->randomFloat(' . $scale . ', ' . self::number($low, $scale) . ', ' . self::number($high, $scale) . ')');
    }

    private static function number(float|int $value, int $scale): string
    {
        $text = rtrim(rtrim(sprintf('%.' . max($scale, 0) . 'F', $value), '0'), '.');

        return $text === '' ? '0' : $text;
    }

    private function temporal(ColumnProfile $c): Rule
    {
        $base = $c->base();
        $name = $c->name;

        if ($base === 'year') {
            return new Rule("{f}->numberBetween(1990, (int) date('Y'))");
        }

        if ($base === 'time' || $base === 'timetz') {
            return new Rule('{f}->time()');
        }

        $range = match (true) {
            preg_match(self::BIRTH, $name) === 1 => 'now()->subYears(80), now()->subYears(18)',
            preg_match(self::FUTURE, $name) === 1 => 'now(), now()->addYear()',
            default => 'now()->subYear(), now()',
        };

        $call = '{f}->dateTimeBetween(' . $range . ')';

        return new Rule($base === 'date' ? $call . "->format('Y-m-d')" : $call);
    }

    private static function short(string $model): string
    {
        $model = str_replace('/', '\\', $model);

        return str_contains($model, '\\') ? substr($model, (int) strrpos($model, '\\') + 1) : $model;
    }
}
