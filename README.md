# PHP CS Fixer Tidy Fixers

Custom PHP CS Fixer rules for tidying code formatting.

## Fixers

### BlankLineIndentationFixer

Indents blank lines to match the indentation level of their surrounding context.

This fixer tracks brace nesting depth and applies appropriate indentation to blank lines (whitespace-only lines) to match the indentation of the code block they appear within.

**Name**: `Chefstore/blank_line_indentation`
**Priority**: 15 (runs after blank-line-count fixers but before final formatting)

#### Example (`.` for indentation for clarity)

Before:
```php
<?php

class Foo {

..public function bar() {
....echo "foo";

....echo "bar";
..}
}
```

After:
```php
<?php

class Foo {
..
..public function bar() {
....echo "test";
....
....echo "bar";
..}
}
```

### BlankLineAfterClassOpeningFixer

Ensures a configurable number of blank lines appear between the opening brace `{` of a class, interface, trait, or enum and the first content token in its body.

Standard `PhpCsFixer\Fixer\ClassNotation\NoBlankLinesAfterClassOpeningFixer` is one-directional — it can only *remove* blank lines. This custom fixer fills the missing direction: missing blank lines are *added*; excess blank lines are *stripped*.

**Name**: `Chefstore/blank_line_after_class_opening`
**Priority**: 20
**Configuration**: `blankLineCount` (int, default: 1) — number of blank lines to enforce after the class opening brace

#### Example

Before:
```php
<?php

class Foo{
protected $x;
}
```

After:
```php
<?php

class Foo{

protected $x;
}
```

## Installation

```bash
composer require deheerhoreca/php-cs-fixer-tidy-fixers
```

## Usage

Add to your `.php-cs-fixer.dist.php`:

```php
<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__);

$config = new PhpCsFixer\Config();
$config
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setRules([
        'Chefstore/blank_line_indentation' => true,
        'Chefstore/blank_line_after_class_opening' => [
            'blankLineCount' => 1,
        ],
    ]);

return $config;
```

## License

Proprietary
