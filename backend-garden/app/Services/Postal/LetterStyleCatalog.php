<?php

declare(strict_types=1);

namespace App\Services\Postal;

/**
 * Reads the letter-style catalogue (config/letter_styles.php) and validates a
 * submitted `style` object against it.
 */
class LetterStyleCatalog
{
    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function all(): array
    {
        /** @var array<string, array<int, array<string, mixed>>> $catalog */
        $catalog = config('letter_styles', []);

        return $catalog;
    }

    /**
     * Valid keys for one dimension (e.g. "papers").
     *
     * @return list<string>
     */
    public function keysFor(string $dimension): array
    {
        return array_values(array_filter(array_map(
            static fn (array $entry): ?string => is_string($entry['key'] ?? null) ? $entry['key'] : null,
            $this->all()[$dimension] ?? [],
        )));
    }

    /**
     * Keep only recognised keys from a client-supplied style object.
     *
     * @param  array<string, mixed>  $style
     * @return array<string, mixed>
     */
    public function sanitize(array $style): array
    {
        $map = [
            'paper' => 'papers',
            'font' => 'fonts',
            'ink' => 'inks',
            'stamp' => 'stamps',
            'border' => 'borders',
        ];

        $clean = [];
        foreach ($map as $field => $dimension) {
            $value = $style[$field] ?? null;
            if (is_string($value) && in_array($value, $this->keysFor($dimension), true)) {
                $clean[$field] = $value;
            }
        }

        if (isset($style['texture']) && is_string($style['texture'])) {
            $clean['texture'] = $style['texture'];
        }

        if (isset($style['flourish'])) {
            $clean['flourish'] = (bool) $style['flourish'];
        }

        $seal = $style['seal'] ?? null;
        if (is_array($seal)) {
            $cleanSeal = ['type' => 'wax'];
            if (is_string($seal['color'] ?? null) && in_array($seal['color'], $this->keysFor('seals'), true)) {
                $cleanSeal['color'] = $seal['color'];
            }
            if (is_string($seal['sigil'] ?? null) && in_array($seal['sigil'], $this->keysFor('sigils'), true)) {
                $cleanSeal['sigil'] = $seal['sigil'];
            }
            $clean['seal'] = $cleanSeal;
        }

        return $clean;
    }
}
