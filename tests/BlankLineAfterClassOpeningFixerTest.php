<?php

declare(strict_types=1);

use Chefstore\Util\Fixer\BlankLineAfterClassOpeningFixer;
use PhpCsFixer\Tokenizer\Tokens;

require dirname(__DIR__)."/vendor/autoload.php";

$fix = static function(string $code): string {
  $tokens = Tokens::fromCode($code);
  (new BlankLineAfterClassOpeningFixer())->fix(new SplFileInfo(__FILE__), $tokens);

  return $tokens->generateCode();
};

$assertSame = static function(string $expected, string $actual, string $label): void {
  if ($actual === $expected) {
    return;
  }

  fwrite(STDERR, "Failed asserting {$label}.\n");
  fwrite(STDERR, "Expected:\n{$expected}\nActual:\n{$actual}\n");
  exit(1);
};

$assertSame(
  "<?php
class Foo {

  private \$x;
}
",
  $fix("<?php
class Foo {
  private \$x;
}
"),
  "blank line is added to a plain class"
);

$assertSame(
  "<?php
final class Foo extends \\Bar\\Baz implements JsonSerializable, \\Countable {

  private \$x;
}
",
  $fix("<?php
final class Foo extends \\Bar\\Baz implements JsonSerializable, \\Countable {
  private \$x;
}
"),
  "blank line is added to a class with extends and implements"
);

$assertSame(
  "<?php
interface Foo extends Bar, Baz {

  public function x(): void;
}
",
  $fix("<?php
interface Foo extends Bar, Baz {
  public function x(): void;
}
"),
  "blank line is added to an interface extending several interfaces"
);

$assertSame(
  "<?php
enum Suit: string implements HasLabel {

  case Hearts = \"H\";
}
",
  $fix("<?php
enum Suit: string implements HasLabel {
  case Hearts = \"H\";
}
"),
  "blank line is added to a backed enum"
);

$assertSame(
  "<?php
\$x = new class(\$a, [1, 2]) extends Foo {

  public int \$y = 1;
};
",
  $fix("<?php
\$x = new class(\$a, [1, 2]) extends Foo {
  public int \$y = 1;
};
"),
  "blank line is added to an anonymous class with constructor arguments"
);

$assertSame(
  "<?php
class Foo {

  private \$x;
}
",
  $fix("<?php
class Foo {



  private \$x;
}
"),
  "excess blank lines are reduced"
);

$assertSame(
  "<?php
class Foo {}
\$name = Foo::class;
\$x = new class {};
",
  $fix("<?php
class Foo {}
\$name = Foo::class;
\$x = new class {};
"),
  "empty bodies and ::class are untouched"
);

echo "OK\n";
