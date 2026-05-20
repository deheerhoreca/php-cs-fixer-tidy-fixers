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
 * This fixer tracks brace nesting depth and applies appropriate indentation
 * to blank lines (whitespace-only lines) to match the indentation of the code
 * block they appear within.
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
    
    // First pass: extract indentation from actual code lines
    $indentMap = $this->buildIndentMap($tokens, $indentUnit);
    
    // Second pass: apply indentation to blank lines
    for ($i = 0; $i < count($tokens); $i++) {
      $token = $tokens[$i];
      
      if ($token->getId() === T_WHITESPACE) {
        $lines = explode("\n", $token->getContent());
        
        // Only process if this whitespace spans multiple lines
        if (count($lines) > 1) {
          $newContent = "";
          
          foreach ($lines as $lineIndex => $line) {
            // Add the newline (except after the last line)
            if ($lineIndex > 0) {
              $newContent .= "\n";
            }
            
            // Last line in the token — keep as-is (may have partial indentation)
            if ($lineIndex === count($lines) - 1) {
              $newContent .= $line;
            } else {
              // This is a blank line (lines between are blank in the output).
              // Figure out what the next non-blank line's indentation should be.
              $nextNonWhitespaceIndex = $this->getNextNonWhitespaceIndex($tokens, $i);
              
              if ($nextNonWhitespaceIndex !== null) {
                $nextToken = $tokens[$nextNonWhitespaceIndex];
                
                // If the next token is a closing brace, don't indent this blank line
                if ($nextToken->getContent() === "}") {
                  // Leave blank
                } else {
                  // Get the indentation of the next line from the indentMap
                  $nextLineIndent = $this->getNextLineIndent($tokens, $i, $indentMap, $indentUnit);
                  $newContent .= $nextLineIndent;
                }
              }
            }
          }
          
          $tokens[$i] = new Token([T_WHITESPACE, $newContent]);
        }
      }
    }
  }

  /**
   * Build a map of line numbers to their indentation level.
   * This lets us know what indentation each line should have.
   */
  private function buildIndentMap(Tokens $tokens, string $indentUnit): array {
    $indentMap = [];
    $currentLine = 1;
    $indentLevel = 0;
    
    for ($i = 0; $i < count($tokens); $i++) {
      $token = $tokens[$i];
      
      // Track brace depth
      if ($token->getContent() === "{") {
        $indentLevel++;
      } elseif ($token->getContent() === "}") {
        $indentLevel = max(0, $indentLevel - 1);
      }
      
      // Track newlines and record indentation for each line
      if ($token->getId() === T_WHITESPACE && strpos($token->getContent(), "\n") !== false) {
        $currentLine += substr_count($token->getContent(), "\n");
      } elseif ($token->getId() !== T_WHITESPACE && trim($token->getContent()) !== "") {
        // This is actual code on a line
        $indentMap[$currentLine] = str_repeat($indentUnit, $indentLevel);
      }
    }
    
    return $indentMap;
  }

  /**
   * Get the indentation that should be used for the line following the next non-whitespace token.
   */
  private function getNextLineIndent(Tokens $tokens, int $currentIndex, array $indentMap, string $indentUnit): string {
    $nextNonWhitespaceIndex = $this->getNextNonWhitespaceIndex($tokens, $currentIndex);
    
    if ($nextNonWhitespaceIndex === null) {
      return "";
    }
    
    $nextToken = $tokens[$nextNonWhitespaceIndex];
    
    // Count braces to determine indentation level from current position
    $indentLevel = 0;
    
    for ($i = 0; $i < $nextNonWhitespaceIndex; $i++) {
      if ($tokens[$i]->getContent() === "{") {
        $indentLevel++;
      } elseif ($tokens[$i]->getContent() === "}") {
        $indentLevel = max(0, $indentLevel - 1);
      }
    }
    
    // If next token is a closing brace, back up one level
    if ($nextToken->getContent() === "}") {
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
