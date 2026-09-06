<?php

/**
 * The themes the FQHC design system ships.
 *
 * The value is what appears in the `data-fqhc-theme` attribute on the root
 * element and in the persisted preference, so it is a backed enum.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\DesignSystem;

enum Theme: string
{
    case Light = 'light';
    case Dark = 'dark';

    public function label(): string
    {
        return match ($this) {
            self::Light => 'Light',
            self::Dark => 'Dark',
        };
    }

    /**
     * The CSS selector whose block carries this theme's token values.
     *
     * Light is the base `:root` declaration, so it has no override selector —
     * `resolvePalette()` returns the base values unchanged for it.
     */
    public function overrideSelector(): ?string
    {
        return match ($this) {
            self::Light => null,
            self::Dark => ':root[data-fqhc-theme="dark"]',
        };
    }

    /**
     * The token values in force under this theme: the base palette, with this
     * theme's overrides applied on top — exactly what the cascade produces.
     *
     * @param array<string, string> $base      Values from the base `:root` block.
     * @param array<string, string> $overrides Values from this theme's override block.
     * @return array<string, string>
     */
    public function resolvePalette(array $base, array $overrides): array
    {
        return $this === self::Light ? $base : [...$base, ...$overrides];
    }
}
