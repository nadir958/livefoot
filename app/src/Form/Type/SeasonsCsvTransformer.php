<?php
declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * Transforms between a CSV string like "2023,2024"
 * and an array ["2023", "2024"] for the entity field seasonsActive (json).
 */
final class SeasonsCsvTransformer implements DataTransformerInterface
{
    /** @param array<string>|null $value */
    public function transform($value): string
    {
        if (empty($value) || !\is_array($value)) {
            return '';
        }
        // keep as strings (don't force ints), remove empties, keep order
        $value = array_values(array_filter($value, static fn($v) => $v !== null && $v !== ''));
        return implode(',', $value);
    }

    /** @return array<string> */
    public function reverseTransform($value): array
    {
        if ($value === null) {
            return [];
        }
        // split, trim, drop empties, deduplicate while preserving order
        $parts = array_map('trim', explode(',', (string) $value));
        $parts = array_values(array_filter($parts, static fn($v) => $v !== ''));
        $seen  = [];
        $out   = [];
        foreach ($parts as $p) {
            if (!isset($seen[$p])) {
                $seen[$p] = true;
                $out[] = $p;
            }
        }
        return $out;
    }
}
