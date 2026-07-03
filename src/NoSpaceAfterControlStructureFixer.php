<?php

declare(strict_types=1);

namespace Chefstore\Util\Fixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * Removes the whitespace between a control-structure keyword and its opening
 * parenthesis, turning `if (` into `if(`, `while (` into `while(`, etc.
 *
 * The native `single_space_around_construct` / `single_space_after_construct`
 * fixers can only *enforce a single space*; they have no option to enforce
 * *zero* space. Omitting a construct from their list makes PHP-CS-Fixer
 * *tolerate* `if(` but it will not actively rewrite `if (` into `if(`. This
 * fixer fills that gap.
 *
 * Only inline whitespace (containing no newline) is removed, so constructs
 * broken across multiple lines are left untouched.
 *
 * ### Example
 *
 * Before fix:
 *
 * ```php
 * <?php
 * if ($a) {
 * }
 * ```
 *
 * After fix:
 *
 * ```php
 * <?php
 * if($a) {
 * }
 * ```
 */
final class NoSpaceAfterControlStructureFixer extends AbstractFixer {

  /**
   * Control-structure keywords that are directly followed by `(`.
   *
   * @var list<int>
   */
  private const KEYWORDS = [
    T_IF,
    T_ELSEIF,
    T_WHILE,
    T_FOR,
    T_FOREACH,
    T_SWITCH,
    T_CATCH,
    T_MATCH,
  ];

  public function getDefinition(): FixerDefinitionInterface {
    return new FixerDefinition(
      "Removes whitespace between a control-structure keyword and its opening parenthesis (e.g. `if (` becomes `if(`).",
      [new CodeSample("<?php\nif (\$a) {\n}\n")],
      null,
      null
    );
  }

  public function getName(): string {
    return "Chefstore/no_space_after_control_structure";
  }

  public function getPriority(): int {
    // Independent of other whitespace fixers; a neutral priority is fine.
    return 0;
  }

  public function isRisky(): bool {
    return false;
  }

  public function isCandidate(Tokens $tokens): bool {
    return $tokens->isAnyTokenKindsFound(self::KEYWORDS);
  }

  protected function applyFix(\SplFileInfo $file, Tokens $tokens): void {
    // Iterate backwards so removals do not shift indexes we have yet to visit.
    for ($index = count($tokens) - 1; $index >= 0; $index--) {
      if (!$tokens[$index]->isGivenKind(self::KEYWORDS)) {
        continue;
      }

      $whitespaceIndex = $index + 1;
      if (!isset($tokens[$whitespaceIndex]) || !$tokens[$whitespaceIndex]->isWhitespace()) {
        continue;
      }

      // Only collapse inline whitespace; leave multi-line constructs alone.
      if (str_contains($tokens[$whitespaceIndex]->getContent(), "\n")) {
        continue;
      }

      $parenIndex = $whitespaceIndex + 1;
      if (!isset($tokens[$parenIndex]) || $tokens[$parenIndex]->getContent() !== "(") {
        continue;
      }

      $tokens->clearAt($whitespaceIndex);
    }

    $tokens->clearEmptyTokens();
  }
}
