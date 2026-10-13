<?php

declare(strict_types=1);

use Chefstore\Util\Fixer\DoubleQuoteExceptRegexFixer;
use PhpCsFixer\Tokenizer\Tokens;

require dirname(__DIR__)."/vendor/autoload.php";

$fix = static function(string $code): string {
  $tokens = Tokens::fromCode($code);
  (new DoubleQuoteExceptRegexFixer())->fix(new SplFileInfo(__FILE__), $tokens);

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
  "<?php\n\$name = \"product\";\n",
  $fix("<?php\n\$name = 'product';\n"),
  "single-quoted string is double-quoted"
);

$assertSame(
  "<?php\n\$pattern = '/foo\\/bar\$/i';\n",
  $fix("<?php\n\$pattern = '/foo\\/bar\$/i';\n"),
  "single-quoted slash-delimited regex with flags is left untouched"
);

$assertSame(
  "<?php\n\$pattern = '/^sku-\d+\$/';\n",
  $fix("<?php\n\$pattern = '/^sku-\d+\$/';\n"),
  "single-quoted regex without flags is left untouched"
);

$assertSame(
  "<?php\n\$pattern = '/foo/bar/';\n\$name = \"baz\";\n",
  $fix("<?php\n\$pattern = '/foo/bar/';\n\$name = 'baz';\n"),
  "double-quoted regex is left alone and neighbouring string is still converted"
);

$assertSame(
  "<?php\n\$a = \"already\";\n",
  $fix("<?php\n\$a = \"already\";\n"),
  "double-quoted string is untouched"
);

echo "All DoubleQuoteExceptRegexFixer tests passed.\n";
