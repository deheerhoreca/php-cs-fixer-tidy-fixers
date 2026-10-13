<?php

declare(strict_types=1);

namespace Chefstore\Util\Fixer;

use GD75\DoubleQuoteFixer\DoubleQuoteFixer;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * Converts single-quoted strings to double quotes, like the upstream
 * `GD75/double_quote_fixer`, but leaves explicitly single-quoted
 * slash-delimited regular expressions (`/.../flags`) untouched.
 *
 * Single-quoted regex literals are a common, intentional style (e.g. in
 * mapping definitions that double as PCRE patterns); double-quoting them
 * forces escaping of `$` and `\` characters and hurts readability. This
 * fixer runs the upstream conversion, then restores the regex strings
 * recorded before the pass.
 *
 * ### Example
 *
 * Before fix:
 *
 * ```php
 * <?php
 * $pattern = '/foo\/bar$/i';
 * $name = 'product';
 * ```
 *
 * After fix:
 *
 * ```php
 * <?php
 * $pattern = '/foo\/bar$/i';
 * $name = "product";
 * ```
 */
final class DoubleQuoteExceptRegexFixer extends AbstractFixer {

  public function getDefinition(): FixerDefinitionInterface {
    return new FixerDefinition(
      "Use double quotes, except for explicitly single-quoted slash-delimited regular expressions.",
      [new CodeSample("<?php\n\$pattern = '/foo\\\\/\$/i';\n\$name = 'product';\n")],
      null,
      null
    );
  }

  public function getName(): string {
    return "Chefstore/double_quote_except_regex";
  }

  public function getPriority(): int {
    return 1;
  }

  public function isRisky(): bool {
    return false;
  }

  public function isCandidate(Tokens $tokens): bool {
    return $tokens->isTokenKindFound(T_CONSTANT_ENCAPSED_STRING);
  }

  public function supports(\SplFileInfo $file): bool {
    return $file->getExtension() === "php";
  }

  protected function applyFix(\SplFileInfo $file, Tokens $tokens): void {
    $regexes     = [];
    $stringIndex = 0;
    foreach($tokens as $token) {
      if(!$token->isGivenKind(T_CONSTANT_ENCAPSED_STRING)) {
        continue;
      }
      $content = $token->getContent();
      // Same slash-delimited detection as ProductMapping's explode/source_attribute options.
      if($content[0] === "'" && preg_match('/^\/.+\/[a-z]*$/i', substr($content, 1, -1)) === 1) {
        $regexes[$stringIndex] = $content;
      }
      $stringIndex++;
    }
    // Upstream inserts tokens and leaves empty slots: retain string order, not token indices.
    (new DoubleQuoteFixer())->fix($file, $tokens);
    $stringIndex = 0;
    foreach($tokens as $index => $token) {
      if(!$token->isGivenKind(T_CONSTANT_ENCAPSED_STRING)) {
        continue;
      }
      if(isset($regexes[$stringIndex])) {
        $tokens[$index] = new Token([T_CONSTANT_ENCAPSED_STRING, $regexes[$stringIndex]]);
      }
      $stringIndex++;
    }
  }
}
