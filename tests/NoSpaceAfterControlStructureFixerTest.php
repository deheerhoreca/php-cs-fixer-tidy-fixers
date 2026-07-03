<?php

declare(strict_types=1);

use Chefstore\Util\Fixer\NoSpaceAfterControlStructureFixer;
use PhpCsFixer\Tokenizer\Tokens;

require dirname(__DIR__)."/vendor/autoload.php";

$fix = static function(string $code): string {
  $tokens = Tokens::fromCode($code);
  (new NoSpaceAfterControlStructureFixer())->fix(new SplFileInfo(__FILE__), $tokens);

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
if(\$a) {
}
",
  $fix("<?php
if (\$a) {
}
"),
  "space after if is removed"
);

$assertSame(
  "<?php
if(\$a) {
} elseif(\$b) {
}
while(\$c) {
}
for(\$i = 0; \$i < 3; \$i++) {
}
foreach(\$x as \$y) {
}
switch(\$z) {
}
try {
} catch(Exception \$e) {
}
",
  $fix("<?php
if (\$a) {
} elseif (\$b) {
}
while (\$c) {
}
for (\$i = 0; \$i < 3; \$i++) {
}
foreach (\$x as \$y) {
}
switch (\$z) {
}
try {
} catch (Exception \$e) {
}
"),
  "all supported control-structure keywords are handled"
);

$assertSame(
  "<?php
\$r = match(\$v) {
  default => 1,
};
",
  $fix("<?php
\$r = match (\$v) {
  default => 1,
};
"),
  "match keyword is handled"
);

$assertSame(
  "<?php
if(\$a) {
}
",
  $fix("<?php
if(\$a) {
}
"),
  "already-tight if is left unchanged"
);

$assertSame(
  "<?php
if(
  \$multiline
) {
}
",
  $fix("<?php
if(
  \$multiline
) {
}
"),
  "multi-line construct is left untouched"
);

$assertSame(
  "<?php
\$fn = function () {};
\$cb = fn (\$x) => \$x;
",
  $fix("<?php
\$fn = function () {};
\$cb = fn (\$x) => \$x;
"),
  "function and fn are not control structures and are left alone"
);

echo "All NoSpaceAfterControlStructureFixer tests passed.\n";
