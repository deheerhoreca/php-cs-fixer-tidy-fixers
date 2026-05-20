<?php

declare(strict_types=1);

namespace Chefstore\Util\Fixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * Ensures exactly `blankLineCount` (default 1) blank line appears between
 * the opening brace `{` of a class, interface, or trait and the first
 * content token in its body.
 *
 * Standard `PhpCsFixer\Fixer\ClassNotation\NoBlankLinesAfterClassOpeningFixer`
 * is one-directional — it can only *remove* blank lines.  This custom fixer
 * fills the missing direction: missing blank lines are *added*; excess blank
 * lines are *stripped*.
 *
 * ### Examples
 *
 * Before fix:
 *
 * ```php
 * <?php
 *
 * class Foo{
 * protected $x;
 * }
 * ```
 *
 * After fix:
 *
 * ```php
 * <?php
 *
 * class Foo{\n\nprotected $x;\n}
 * ```
 *
 * Empty class bodies (`class Foo {}`) are left untouched.
 */
final class BlankLineAfterClassOpeningFixer extends AbstractFixer implements ConfigurableFixerInterface {
  
  /**
   * @var int
   */
  private int $blankLineCount = 1;

  // -------------------------------------------------------------------------
  // ConfigurableFixerInterface
  // -------------------------------------------------------------------------

  public function configure(array $configuration = null): void {
    if ($configuration !== null && \array_key_exists("blankLineCount", $configuration)) {
      $this->blankLineCount = (int) $configuration["blankLineCount"];
      if ($this->blankLineCount < 1) {
        $this->blankLineCount = 1;
      }
    }
  }

  public function getConfigurationDefinition(): FixerConfigurationResolver {
    return new FixerConfigurationResolver(
      [
        (new FixerOptionBuilder("blankLineCount", "Number of blank lines to enforce after the class opening brace."))
          ->setAllowedTypes(["int"])
          ->setDefault(1)
          ->getOption(),
      ]
    );
  }

  // -------------------------------------------------------------------------
  // FixerInterface
  // -------------------------------------------------------------------------

  public function getDefinition(): FixerDefinitionInterface {
    $beforeCode = "<?php

class Foo{"."\n"."protected \$x;"."\n"."}
";

    $afterCode = "<?php

class Foo{
protected \$x;
}
";

    return new FixerDefinition(
      "Ensures a single blank line after the opening brace of a class, interface, or trait.",
      [new CodeSample($beforeCode)],
      null,
      null
    );
  }

  public function getName(): string {
    return "Chefstore/blank_line_after_class_opening";
  }

  public function getPriority(): int {
    // Body blank-line fixers should run after brace-positioning and
    // before body-nesting indent fixers.
    return 20;
  }

  public function isRisky(): bool {
    return false;
  }

  public function isCandidate(Tokens $tokens): bool {
    return true;
  }

  protected function applyFix(\SplFileInfo $file, Tokens $tokens): void {
    foreach ($tokens->findGivenKind([T_CLASS, T_INTERFACE, T_TRAIT]) as $tokenList) {
      foreach ($tokenList as $index => $token) {
        $this->maybeFixConstruct($tokens, $token, $index);
      }
    }
  }

  // -------------------------------------------------------------------------
  // Internal logic
  // -------------------------------------------------------------------------

  /**
   * If the token at `$index` starts a class-like construct, try to enforce
   * the blank-line rule on its body.
   */
  private function maybeFixConstruct(Tokens $tokens, Token $token, int $index): void {
    if (!\in_array($token->getId(), [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
      return;
    }

    $braceIndex = $this->findOpeningBrace($tokens, $index);
    if ($braceIndex === null) {
      return;
    }

    $this->enforceBlankLines($tokens, $braceIndex);
  }

  /**
   * From `$classIndex` (T_CLASS / T_INTERFACE / T_TRAIT), scan forward
   * to find the opening brace `{`.
   *
   * PHP-CS-Fixer tokenises a class as:
   *   T_CLASS  T_WHITESPACE  T_STRING(name)  [T_WHITESPACE
   *   [T_EXTENDS|T_IMPLEMENTS …]]  LITERAL  '{'
   *
   * Literal-brace tokens do not have a numeric token-ID (id=null), so they
   * must be matched by content, not by `=== '{'`.
   */
  private function findOpeningBrace(Tokens $tokens, int $classIndex): ?int {
    $hasSkippedClassToken = false;

    for ($i = $classIndex + 1, $n = count($tokens); $i < $n; $i++) {
      $id = $tokens[$i]->getId();

      if ($id === T_WHITESPACE) {
        continue;
      }

      if ($id === T_STRING && !$hasSkippedClassToken) {
        $hasSkippedClassToken = true;
        continue;
      }

      if (\in_array($id, [T_ABSTRACT, T_FINAL, T_READONLY, T_EXTENDS, T_IMPLEMENTS], true)) {
        continue;
      }

      if ($id === T_USE) {
        continue;
      }

      if ($id === T_ATTRIBUTE) {
        continue;
      }

      if ($tokens[$i]->getContent() === "{") {
        return $i;
      }

      return null;
    }

    return null;
  }

  /**
   * Normalise blank lines between `{` at `$braceIndex` and the first
   * content token in the body to exactly $this->blankLineCount blank lines.
   *
   * Empty class bodies (`{}`) are handled above in findOpeningBrace /
   * here as an early exit.
   */
  private function enforceBlankLines(Tokens $tokens, int $braceIndex): void {
    $count      = count($tokens);
    $afterBrace = $braceIndex + 1;

    if ($afterBrace >= $count) {
      return;
    }

    // Guard: empty body `{}` — nothing to do.
    if ($tokens[$afterBrace]->getContent() === "}") {
      return;
    }

    // The token immediately after `{` should be whitespace containing
    // the newline(s) and possibly indentation for the first body line.
    if ($tokens[$afterBrace]->getId() !== T_WHITESPACE) {
      // No whitespace at all after `{` — insert the desired blank lines
      // plus a newline for the brace line itself.
      $tokens->insertAt($afterBrace, new Token([T_WHITESPACE, str_repeat("\n", 1 + $this->blankLineCount)]));
      return;
    }

    $ws              = $tokens[$afterBrace]->getContent();
    $existingNewlines = substr_count($ws, "\n");

    // desired = 1 (line-ending after `{`) + blankLineCount
    $desiredNewlines = 1 + $this->blankLineCount;

    if ($existingNewlines === $desiredNewlines) {
      return;
    }

    // Preserve the indentation that follows the last newline (leading
    // whitespace of the first body line).
    $lastNewline = strrpos($ws, "\n");
    $indent      = $lastNewline !== false ? substr($ws, $lastNewline + 1) : "";

    $tokens[$afterBrace] = new Token([T_WHITESPACE, str_repeat("\n", $desiredNewlines) . $indent]);
  }
}
