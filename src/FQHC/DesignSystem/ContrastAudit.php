<?php

/**
 * Measures every foreground/background pairing the FQHC palette actually uses.
 *
 * The pairings are enumerated here rather than derived from the token names
 * because "which colour sits on which" is a fact about the components, not
 * about the palette: `--fqhc-color-success` is only ever painted on
 * `--fqhc-color-success-soft` (badge) or on a card surface (inline text), and
 * measuring it against every other token would bury the real results in noise.
 *
 * Adding a component that introduces a new pairing means adding a row here —
 * which is the point: the audit is the list of combinations the design system
 * claims are safe, and the style guide prints the measurement beside each one.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\DesignSystem;

final readonly class ContrastAudit
{
    /**
     * Pairings under test: usage label, foreground token, background token,
     * and whether the WCAG large-text thresholds apply (18pt+, or 14pt bold).
     *
     * @var list<array{usage: string, fg: string, bg: string, large: bool}>
     */
    private const PAIRINGS = [
        // Body text on each surface.
        ['usage' => 'Body text on the page', 'fg' => '--fqhc-text', 'bg' => '--fqhc-surface-page', 'large' => false],
        ['usage' => 'Body text on a card', 'fg' => '--fqhc-text', 'bg' => '--fqhc-surface-card', 'large' => false],
        ['usage' => 'Body text on a sunken panel', 'fg' => '--fqhc-text', 'bg' => '--fqhc-surface-sunken', 'large' => false],

        // Secondary text — the pairings most at risk of failing.
        ['usage' => 'Muted label on a card', 'fg' => '--fqhc-text-muted', 'bg' => '--fqhc-surface-card', 'large' => false],
        ['usage' => 'Muted label on the page', 'fg' => '--fqhc-text-muted', 'bg' => '--fqhc-surface-page', 'large' => false],
        ['usage' => 'Empty-state text on a sunken panel', 'fg' => '--fqhc-text-muted', 'bg' => '--fqhc-surface-sunken', 'large' => false],

        // Brand.
        ['usage' => 'Primary button label', 'fg' => '--fqhc-text-on-primary', 'bg' => '--fqhc-color-primary', 'large' => false],
        ['usage' => 'Primary button label (hover)', 'fg' => '--fqhc-text-on-primary', 'bg' => '--fqhc-color-primary-strong', 'large' => false],
        ['usage' => 'Card heading on a card', 'fg' => '--fqhc-color-primary-strong', 'bg' => '--fqhc-surface-card', 'large' => false],
        ['usage' => 'Metric value on a card', 'fg' => '--fqhc-color-primary-strong', 'bg' => '--fqhc-surface-card', 'large' => true],
        ['usage' => 'Text on a selected/tinted row', 'fg' => '--fqhc-text', 'bg' => '--fqhc-color-primary-soft', 'large' => false],
        ['usage' => 'Link on a card', 'fg' => '--fqhc-color-accent', 'bg' => '--fqhc-surface-card', 'large' => false],
        ['usage' => 'Link on the page', 'fg' => '--fqhc-color-accent', 'bg' => '--fqhc-surface-page', 'large' => false],

        // Status badges: semantic colour on its own soft tint.
        ['usage' => 'Success badge', 'fg' => '--fqhc-color-success', 'bg' => '--fqhc-color-success-soft', 'large' => false],
        ['usage' => 'Warning badge', 'fg' => '--fqhc-color-warning', 'bg' => '--fqhc-color-warning-soft', 'large' => false],
        ['usage' => 'Danger badge', 'fg' => '--fqhc-color-danger', 'bg' => '--fqhc-color-danger-soft', 'large' => false],
        ['usage' => 'Info badge', 'fg' => '--fqhc-color-info', 'bg' => '--fqhc-color-info-soft', 'large' => false],
        ['usage' => 'Neutral badge', 'fg' => '--fqhc-color-neutral', 'bg' => '--fqhc-color-neutral-soft', 'large' => false],

        // The same semantic colours used as inline text on a card.
        ['usage' => 'Success text on a card', 'fg' => '--fqhc-color-success', 'bg' => '--fqhc-surface-card', 'large' => false],
        ['usage' => 'Warning note on a card', 'fg' => '--fqhc-color-warning', 'bg' => '--fqhc-surface-card', 'large' => false],
        ['usage' => 'Danger text on a card', 'fg' => '--fqhc-color-danger', 'bg' => '--fqhc-surface-card', 'large' => false],
        ['usage' => 'Info text on a card', 'fg' => '--fqhc-color-info', 'bg' => '--fqhc-surface-card', 'large' => false],
    ];

    public function __construct(private ColorContrast $contrast = new ColorContrast())
    {
    }

    /**
     * Measure every pairing whose two tokens are present and are parseable
     * colours. A pairing naming a token that no longer exists is skipped
     * rather than fatal — the audit reports on the palette as it is.
     *
     * @param array<string, string> $tokenValues Token name => declared value.
     * @return list<ContrastPair>
     */
    public function measure(array $tokenValues): array
    {
        $pairs = [];
        foreach (self::PAIRINGS as $pairing) {
            $foreground = $tokenValues[$pairing['fg']] ?? null;
            $background = $tokenValues[$pairing['bg']] ?? null;
            if ($foreground === null || $background === null) {
                continue;
            }

            try {
                $ratio = $this->contrast->reportedRatio($foreground, $background);
            } catch (\InvalidArgumentException) {
                continue;
            }

            $pairs[] = new ContrastPair(
                $pairing['usage'],
                $pairing['fg'],
                $foreground,
                $pairing['bg'],
                $background,
                $ratio,
                ContrastRating::forRatio($ratio, $pairing['large']),
                $pairing['large'],
            );
        }

        return $pairs;
    }

    /**
     * Pairings that do not meet WCAG 2.1 AA.
     *
     * @param list<ContrastPair> $pairs
     * @return list<ContrastPair>
     */
    public function failures(array $pairs): array
    {
        return array_values(
            array_filter($pairs, static fn(ContrastPair $pair): bool => !$pair->rating->passes())
        );
    }
}
