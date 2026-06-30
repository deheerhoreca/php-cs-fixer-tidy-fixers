<?php

declare(strict_types=1);

use Chefstore\Util\Fixer\BlankLineIndentationFixer;
use PhpCsFixer\Tokenizer\Tokens;

require dirname(__DIR__)."/vendor/autoload.php";

$fix = static function(string $code): string {
  $tokens = Tokens::fromCode($code);
  (new BlankLineIndentationFixer())->fix(new SplFileInfo(__FILE__), $tokens);

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
  
  public function bar() {
    
    echo \"test\";
  }
}
",
  $fix("<?php
class Foo {

  public function bar() {

    echo \"test\";
  }
}
"),
  "blank lines inside brace-delimited blocks are still indented"
);

$assertSame(
  "<?php
\$items = [
  \"a\" => 1,
  
  \"b\" => 2,
];
",
  $fix("<?php
\$items = [
  \"a\" => 1,
  
  \"b\" => 2,
];
"),
  "existing blank-line indentation inside arrays is preserved"
);

$assertSame(
  "<?php
\$items = [
  \"a\" => 1,
  
  \"b\" => 2,
];
",
  $fix("<?php
\$items = [
  \"a\" => 1,

  \"b\" => 2,
];
"),
  "missing blank-line indentation inside arrays is added"
);

$assertSame(
  "<?php if (\$show): ?>
  <script>
    (function() {
      const initialValues = new Map();
      
      function getValue(el) {
        return el.value;
      }
      
      // Capture initial values and insert revert buttons
      getValue(document.body);
    })();
  </script>
<?php endif; ?>
",
  $fix("<?php if (\$show): ?>
  <script>
    (function() {
      const initialValues = new Map();

      function getValue(el) {
        return el.value;
      }

      // Capture initial values and insert revert buttons
      getValue(document.body);
    })();
  </script>
<?php endif; ?>
"),
  "blank lines inside inline HTML script blocks are indented"
);

echo "OK\n";
