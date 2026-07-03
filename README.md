# PHP CS Fixer Tidy Fixers

Custom PHP CS Fixer rules for tidying code formatting.

## Fixers

### BlankLineIndentationFixer

Indents blank lines to match the indentation level of their surrounding context.

This fixer applies appropriate indentation to blank lines (whitespace-only lines) to match the indentation of the surrounding code block. It preserves indentation already established by PHP-CS-Fixer and falls back to delimiter nesting when blank-line indentation is missing.

It also applies line-based blank-line indentation inside inline HTML segments outside PHP tags, such as embedded `<script>` and markup blocks.

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

### NoSpaceAfterControlStructureFixer

Removes the whitespace between a control-structure keyword and its opening parenthesis, turning `if (` into `if(`, `while (` into `while(`, etc.

The native `single_space_around_construct` / `single_space_after_construct` fixers can only *enforce a single space* — they have no option to enforce *zero* space. Omitting a construct from their list makes PHP-CS-Fixer *tolerate* `if(`, but it will not actively rewrite `if (` into `if(`. This fixer fills that gap.

Handled keywords: `if`, `elseif`, `while`, `for`, `foreach`, `switch`, `catch`, `match`. Only inline whitespace (no newline) is removed, so multi-line constructs are left untouched. `function`/`fn` are intentionally not affected.

**Name**: `Chefstore/no_space_after_control_structure`
**Priority**: 0

#### Example

Before:
```php
<?php

if ($a) {
}
```

After:
```php
<?php

if($a) {
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
        'Chefstore/no_space_after_control_structure' => true,
    ]);

return $config;
```

## License

Proprietary
