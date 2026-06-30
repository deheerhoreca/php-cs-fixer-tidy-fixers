<?php

declare(strict_types=1);

namespace Chefstore\Util\Fixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * Indents blank lines to match the indentation level of their surrounding context.
 *
 * This fixer applies appropriate indentation to blank lines (whitespace-only
 * lines) to match the indentation of the surrounding code block.
 *
 * ### Examples
 *
 * Before fix:
 *
 * ```php
 * <?php
 *
 * class Foo {
 *
 *   public function bar() {
 *     if (true) {
 *
 *       echo "test";
 *     }
 *   }
 * }
 * ```
 *
 * After fix:
 *
 * ```php
 * <?php
 *
 * class Foo {
 *   
 *   public function bar() {
 *     if (true) {
 *       
 *       echo "test";
 *     }
 *   }
 * }
 * ```
 */
final class BlankLineIndentationFixer extends AbstractFixer {
  
  public function getDefinition(): FixerDefinitionInterface {
    $beforeCode = "<?php
class Foo {

  public function bar() {

    echo \"test\";
  }
}
";

    $afterCode = "<?php
class Foo {
  
  public function bar() {
    
    echo \"test\";
  }
}
";

    return new FixerDefinition(
      "Indents blank lines to match the indentation level of their surrounding context.",
      [new CodeSample($beforeCode)],
      null,
      null
    );
  }

  public function getName(): string {
    return "Chefstore/blank_line_indentation";
  }

  public function getPriority(): int {
    // Run after blank-line-count fixers but before final formatting
    return 15;
  }

  public function isRisky(): bool {
    return false;
  }

  public function isCandidate(Tokens $tokens): bool {
    return true;
  }

  protected function applyFix(\SplFileInfo $file, Tokens $tokens): void {
    $indentUnit = $this->detectIndentUnit($tokens);
    
    for ($i = 0; $i < count($tokens); $i++) {
      $token = $tokens[$i];
      
      if ($token->getId() === T_WHITESPACE) {
        $lines = explode("\n", $token->getContent());
        
        // Only process if this whitespace spans multiple lines
        if (count($lines) > 1) {
          $newContent = "";
          $blankLineIndent = $this->getBlankLineIndent($tokens, $i, $lines, $indentUnit);
          
          foreach ($lines as $lineIndex => $line) {
            // Add the newline (except after the last line)
            if ($lineIndex > 0) {
              $newContent .= "\n";
            }
            
            // Last line in the token — keep as-is (may have partial indentation)
            if ($lineIndex === count($lines) - 1) {
              $newContent .= $line;
            } else {
              // The first segment is trailing whitespace on the previous code
              // line. Only the middle segments are actual blank lines.
              if ($lineIndex === 0) {
                $newContent .= $line;
              } else {
                $newContent .= $blankLineIndent;
              }
            }
          }
          
          $tokens[$i] = new Token([T_WHITESPACE, $newContent]);
        }
      } elseif ($token->getId() === T_INLINE_HTML) {
        $newContent = $this->fixInlineHtmlBlankLineIndentation($token->getContent());

        if ($newContent !== $token->getContent()) {
          $tokens[$i] = new Token([T_INLINE_HTML, $newContent]);
        }
      }
    }
  }

  /**
   * Indent blank lines in inline HTML/JS/CSS segments that sit outside PHP tags.
   *
   * PHP-CS-Fixer tokenizes those segments as T_INLINE_HTML rather than
   * T_WHITESPACE, so the token-based indentation logic above never sees blank
   * lines inside a <script> or markup block.
   */
  private function fixInlineHtmlBlankLineIndentation(string $content): string {
    if (strpos($content, "\n") === false) {
      return $content;
    }

    $lines = explode("\n", $content);
    $lastIndex = count($lines) - 1;

    for ($lineIndex = 1; $lineIndex < $lastIndex; $lineIndex++) {
      if (!$this->isBlankLine($lines[$lineIndex])) {
        continue;
      }

      $indent = $this->getNextInlineHtmlLineIndent($lines, $lineIndex);

      if ($indent === null) {
        continue;
      }

      $lines[$lineIndex] = $indent . (str_ends_with($lines[$lineIndex], "\r") ? "\r" : "");
    }

    return implode("\n", $lines);
  }

  private function isBlankLine(string $line): bool {
    return preg_match('/^[ \t]*\r?$/', $line) === 1;
  }

  private function getNextInlineHtmlLineIndent(array $lines, int $currentLineIndex): ?string {
    for ($lineIndex = $currentLineIndex + 1; $lineIndex < count($lines); $lineIndex++) {
      $line = rtrim($lines[$lineIndex], "\r");

      if (trim($line) === "") {
        continue;
      }

      $trimmedLine = ltrim($line, " \t");

      // Preserve the old token-based behavior for blank lines immediately
      // before closing delimiters: leave them truly blank.
      if ($trimmedLine[0] === "}" || $trimmedLine[0] === "]") {
        return null;
      }

      preg_match('/^[ \t]*/', $line, $matches);

      return $matches[0] ?? "";
    }

    return null;
  }

  /**
   * Get the indentation to use for blank lines inside the whitespace token.
   *
   * Prefer the indentation that already exists before the next non-whitespace
   * token. This keeps the fixer compatible with files where PHP-CS-Fixer has
   * already established correct statement/array indentation. If there is no
   * next-line indentation yet, fall back to delimiter nesting so missing blank
   * line indentation can still be added.
   */
  private function getBlankLineIndent(Tokens $tokens, int $currentIndex, array $lines, string $indentUnit): string {
    $nextNonWhitespaceIndex = $this->getNextNonWhitespaceIndex($tokens, $currentIndex);

    if ($nextNonWhitespaceIndex === null) {
      return "";
    }

    $nextToken = $tokens[$nextNonWhitespaceIndex];

    // Preserve the old behavior for blank lines immediately before closing
    // braces: leave them truly blank.
    if ($nextToken->getContent() === "}") {
      return "";
    }

    $nextLineIndent = end($lines);

    if (is_string($nextLineIndent) && preg_match('/^[ \t]+$/', $nextLineIndent) === 1) {
      return $nextLineIndent;
    }
    
    return $this->getDelimiterIndent($tokens, $nextNonWhitespaceIndex, $indentUnit);
  }

  /**
   * Count code-block and array delimiters to infer indentation when the next
   * non-whitespace line is not indented yet.
   */
  private function getDelimiterIndent(Tokens $tokens, int $nextNonWhitespaceIndex, string $indentUnit): string {
    $indentLevel = 0;
    
    for ($i = 0; $i < $nextNonWhitespaceIndex; $i++) {
      $content = $tokens[$i]->getContent();

      if ($content === "{" || $content === "[") {
        $indentLevel++;
      } elseif ($content === "}" || $content === "]") {
        $indentLevel = max(0, $indentLevel - 1);
      }
    }
    
    if ($tokens[$nextNonWhitespaceIndex]->getContent() === "]") {
      $indentLevel = max(0, $indentLevel - 1);
    }
    
    return str_repeat($indentUnit, $indentLevel);
  }

  /**
   * Detect the indentation unit used in the file (e.g., "  " or "\t").
   */
  private function detectIndentUnit(Tokens $tokens): string {
    for ($i = 0; $i < count($tokens); $i++) {
      if ($tokens[$i]->getId() === T_WHITESPACE) {
        $content = $tokens[$i]->getContent();
        
        // Look for indentation at the start of a line
        if (strpos($content, "\n") !== false) {
          $lines = explode("\n", $content);
          $lastLine = end($lines);
          
          // If last line has leading whitespace (indentation)
          if ($lastLine && ($lastLine[0] === " " || $lastLine[0] === "\t")) {
            // Count consecutive spaces or tabs
            $match = preg_match('/^([ \t]+)/', $lastLine, $matches);
            if ($match && !empty($matches[1])) {
              return $matches[1];
            }
          }
        }
      }
    }
    
    // Default to 2 spaces
    return "  ";
  }

  /**
   * Get the index of the previous non-whitespace token.
   */
  private function getPrevNonWhitespaceIndex(Tokens $tokens, int $currentIndex): ?int {
    for ($i = $currentIndex - 1; $i >= 0; $i--) {
      if ($tokens[$i]->getId() !== T_WHITESPACE) {
        return $i;
      }
    }
    return null;
  }

  /**
   * Get the index of the next non-whitespace token.
   */
  private function getNextNonWhitespaceIndex(Tokens $tokens, int $currentIndex): ?int {
    for ($i = $currentIndex + 1; $i < count($tokens); $i++) {
      if ($tokens[$i]->getId() !== T_WHITESPACE) {
        return $i;
      }
    }
    return null;
  }
}
