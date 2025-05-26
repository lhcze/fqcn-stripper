<?php

declare(strict_types=1);

namespace LHcze\FqcnStripper;

use InvalidArgumentException;
use LogicException;

/**
 * Utility for stripping and formatting Fully Qualified Class Names (FQCN) to their base name.
 *
 * Extracts the short class name from an FQCN and optionally applies string transformations
 * such as lowercasing, uppercasing, or capitalizing the first letter.
 *
 * Supports multibyte string operations conditionally based on the availability of the mbstring extension.
 *
 * @author Lukas Hudecek <hudecek.lukas@gmail.com>
 * @link https://github.com/lhcze/fqcn-stripper
 * @license MIT
 */
final class FqcnStripper
{
    /**
     * No modification to the base name, default behavior
     */
    public const int NONE = 0;

    /**
     * Output gets converted to lowercase
     */
    public const int LOWER = 1 << 0;

    /**
     * The First letter of the output will be in uppercase
     */
    public const int UC = 1 << 1;

    /**
     * Output gets converted to uppercase
     */
    public const int UPPER = 1 << 2;

    /**
     * Output gets converted to lowercase and then the first letter gets capitalized
     */
    public const int LOW_UC = self::LOWER | self::UC;

    /**
     * Use multibyte-safe string operations for class name transformations
     */
    public const int MULTIBYTE = 1 << 3;

    /**
     * Auto-removal of all common class name suffixes from the class basename. @see self::POSTFIX_LIST
     */
    public const int TRIM_POSTFIX = 1 << 4;

    /**
     * All valid bitmask combinations
     */
    private const int VALID_MODIFIERS =
        self::NONE |
        self::LOWER |
        self::UC |
        self::UPPER |
        self::MULTIBYTE |
        self::TRIM_POSTFIX;

    /**
     * Sane list of common suffixes
     */
    private const array POSTFIX_LIST = [
        'interface',
        'trait',
        'abstract',
        'class',
        'impl', // implementation
        'entity',
        'dto',
        'vo', // value object
        'model',
        'service',
        'controller',
        'factory',
        'repository',
        'event',
        'listener',
        'subscriber',
        'command',
        'query',
        'enum',
        'handler',
    ];

    /**
     * Cached result of extension_loaded('mbstring')
     */
    private static bool $mbStringLoaded;

    /**
     * Internal static array cache
     * @var array<string, string>
     */
    private static array $basenameCache = [];

    /**
     * Strips the namespace from a fully-qualified class name (FQCN) and applies optional string transformations
     * using bitwise flags.
     *
     * @param string|object $objectFQCN Object or its fully-qualified class name (e.g., "App\\Entity\\User")
     * @param int $modifier Bitmask of transformation modifiers (e.g., LOWER | UC)
     *
     * @return string The transformed short class name (e.g., "user", "User", "USER")
     *
     * @throws InvalidArgumentException If an invalid modifier value is provided
     * @throws LogicException If incompatible modifiers are combined (e.g., UPPER with LOWER or UC)
     */
    public static function strip(string|object $objectFQCN, int $modifier = self::NONE): string
    {
        if ($objectFQCN === '') {
            throw new InvalidArgumentException('First argument cannot be empty string');
        }

        if (is_object($objectFQCN)) {
            $objectFQCN = $objectFQCN::class;
        }

        self::validateModifier($modifier);

        $cacheKey = $objectFQCN . '|' . $modifier;

        if (isset(self::$basenameCache[$cacheKey])) {
            return self::$basenameCache[$cacheKey];
        }

        // Extract base class name
        $baseName = str_contains($objectFQCN, '\\')
            ? basename(str_replace('\\', '/', $objectFQCN))
            : $objectFQCN;

        return self::$basenameCache[$cacheKey] = self::applyModifiers($baseName, $modifier);
    }

    /**
     * Group-stripping! Why do just one if you can see them all at once!
     *
     * @param array<object|string> $list List of either objects or object FQCN strings
     * @param int $modifier Bitmask of transformation modifiers to be applied on all (e.g., LOWER | UC)
     *
     * @return array<string> Fully dressed on the input, stripped on the output
     */
    public static function stripThemAll(array $list, int $modifier = self::NONE): array
    {
        return array_map(static fn($item) => self::strip($item, $modifier), $list);
    }

    /**
     * Clears the internal strip() result cache.
     *
     * @internal For testing or long-running processes only.
     */
    public static function clearCache(): void
    {
        self::$basenameCache = [];
    }

    /**
     * Returns all available modifier constants in a name => value array. Useful for introspection,
     * debugging, UI display, or whatnot.
     *
     * @return array<string, int> Array of available modifier names and their integer values
     */
    public static function getAvailableModifiers(): array
    {
        return [
            'NONE' => self::NONE,
            'LOWER' => self::LOWER,
            'UC' => self::UC,
            'UPPER' => self::UPPER,
            'LOW_UC' => self::LOW_UC,
            'MULTIBYTE' => self::MULTIBYTE,
            'TRIM_POSTFIX' => self::TRIM_POSTFIX,
        ];
    }

    /**
     * Normalizes a raw modifier bitmask to its canonical equivalent. What is it good for, you ask?
     *
     * If both LOWER and UC are set, this method replaces them with the predefined LOW_UC constant.
     * Still wondering? Here are some examples.
     *
     * Examples:
     * - Logging:
     *   $mod = FqcnStripper::normalizeModifier($userInputFlags);
     *   $log->info("Using modifier: ". array_search($mod, FqcnStripper::getAvailableModifiers(), true));
     *
     * - Test assertions:
     *   $this->assertSame(
     *      FqcnStripper::LOW_UC,
     *      FqcnStripper::normalizeModifier(FqcnStripper::LOWER | FqcnStripper::UC));
     *
     * - Modifier comparison logic:
     *   if (FqcnStripper::normalizeModifier($a) === FqcnStripper::normalizeModifier($b)) {
     *       // Logically equivalent modifiers
     *   }
     *
     * @param int $modifier The original modifier bitmask
     * @return int Normalized modifier value
     */
    public static function normalizeModifier(int $modifier): int
    {
        $normalized = $modifier;

        if (($modifier & self::LOWER) && ($modifier & self::UC)) {
            // Remove LOWER and UC bits
            $normalized &= ~(self::LOWER | self::UC);
            // Add LOW_UC bit instead
            $normalized |= self::LOW_UC;
        }

        return $normalized;
    }

    /**
     * Validates that the given modifier is valid before calling in the stripper.
     *
     * @param int $modifier The modifier value to validate
     */
    public static function isValidModifier(int $modifier): bool
    {
        $isValidBitmask = ($modifier | self::VALID_MODIFIERS) === self::VALID_MODIFIERS;
        $hasConflict = ($modifier & self::UPPER) && ($modifier & (self::LOWER | self::UC));
        $mbRequired = ($modifier & self::MULTIBYTE) !== 0;

        return $isValidBitmask
            && !$hasConflict
            && (!$mbRequired || self::isMbStringLoaded());
    }

    /**
     * Validates that the given modifier is supported and that there are no conflicting flags.
     *
     * @param int $modifier The modifier value to validate
     *
     * @throws InvalidArgumentException If the modifier contains unsupported bits
     * @throws LogicException If the modifier includes conflicting combinations (e.g., UPPER with LOWER or UC)
     */
    private static function validateModifier(int $modifier): void
    {
        if (($modifier & self::MULTIBYTE) && !self::isMbStringLoaded()) {
            throw new LogicException('MULTIBYTE modifier requires the mbstring extension to be available.');
        }

        if (($modifier | self::VALID_MODIFIERS) !== self::VALID_MODIFIERS) {
            throw new InvalidArgumentException(sprintf(
                'Invalid modifier value provided: %d Invalid bits provided: %s',
                $modifier,
                $modifier & ~self::VALID_MODIFIERS,
            ));
        }

        $hasUpper = ($modifier & self::UPPER) !== 0;
        $hasLowerOrUc = ($modifier & (self::LOWER | self::UC)) !== 0;

        if ($hasUpper && $hasLowerOrUc) {
            throw new LogicException('UPPER modifier cannot be combined with LOWER or UC modifiers');
        }
    }

    /**
     * Applies string transformations based on the given modifier
     *
     * @param string $classBaseName The already stripped class name in whatever casing
     * @param int $modifier The transformation modifier to be applied
     *
     * @return string The transformed class name
     */
    private static function applyModifiers(string $classBaseName, int $modifier): string
    {
        if ($modifier === self::NONE) {
            return $classBaseName;
        }

        $useMb = self::useMb($modifier);

        if ($modifier & self::UPPER) {
            return (string) self::strOp($classBaseName, StringOperation::UPPER, $useMb);
        }

        if ($modifier & self::LOWER) {
            $classBaseName = (string) self::strOp($classBaseName, StringOperation::LOWER, $useMb);
        }

        if ($modifier & self::UC) {
            $classBaseName = (string) self::strOp($classBaseName, StringOperation::UC, $useMb);
        }

        if ($modifier & self::TRIM_POSTFIX) {
            $classBaseName = self::trimPostfix($classBaseName, $useMb);
        }

        return $classBaseName;
    }

    private static function useMb(int $modifier): bool
    {
        return ($modifier & self::MULTIBYTE) !== 0;
    }

    /**
     * Perform either normal or multibyte string operations
     * @param array<int> $arg
     */
    private static function strOp(string $string, StringOperation $operation, bool $mb, ?array $arg = null): string|int
    {
        return match ($operation) {
            StringOperation::LOWER => $mb ? mb_strtolower($string) : strtolower($string),
            StringOperation::UPPER => $mb ? mb_strtoupper($string) : strtoupper($string),
            StringOperation::UC => $mb
                ? mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1)
                : ucfirst($string),

            StringOperation::LEN => $mb ? mb_strlen($string) : strlen($string),

            StringOperation::SUB => match (true) {
                is_array($arg) => $mb
                    ? mb_substr($string, ...$arg)
                    : substr($string, ...$arg),
                default => throw new InvalidArgumentException('SUB requires params'),
            },
        };
    }

    /**
     * Checks whether the php's mbstring extension is loaded
     */
    private static function isMbStringLoaded(): bool
    {
        return self::$mbStringLoaded ??= extension_loaded('mbstring');
    }

    /**
     * Just a quick and dirty solution to trimming well-known pre-defined postfix names from the fqcn name.
     * The idea is to optimize it using a trie-like approach (reversing words)
     */
    private static function trimPostfix(string $classBaseName, bool $useMb): string
    {
        $workingLower = (string) self::strOp($classBaseName, StringOperation::LOWER, $useMb);

        do {
            $trimmed = false;

            foreach (self::POSTFIX_LIST as $postfix) {
                $len = strlen($postfix);
                $suffix = self::strOp($workingLower, StringOperation::SUB, $useMb, [-$len]);

                if ($suffix === $postfix) {
                    $classBaseName = (string) self::strOp($classBaseName, StringOperation::SUB, $useMb, [0, -$len]);
                    $workingLower = (string) self::strOp($classBaseName, StringOperation::LOWER, $useMb);
                    $trimmed = true;
                    break; // Restart the loop
                }
            }
        } while ($trimmed);

        return $classBaseName;
    }
}
