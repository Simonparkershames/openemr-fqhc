<?php

/**
 * FQHC module — design-system style guide (issue #59).
 *
 * A living style guide: the tokens are parsed out of the real `tokens.css` and
 * the components are the real custom elements, so the page cannot drift from
 * the system it documents. Nothing here reads patient data or writes anything.
 *
 * Gated on admin/super rather than the patients/demo check the workspace pages
 * use — this is a development and design-review surface, not a clinical one.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../globals.php';

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\FQHC\DesignSystem\ContrastAudit;
use OpenEMR\FQHC\DesignSystem\ContrastPair;
use OpenEMR\FQHC\DesignSystem\DesignSystemAssets;
use OpenEMR\FQHC\DesignSystem\DesignToken;
use OpenEMR\FQHC\DesignSystem\Icon;
use OpenEMR\FQHC\DesignSystem\TokenGroup;
use OpenEMR\FQHC\DesignSystem\TokenSheetParser;

if (!AclMain::aclCheckCore('admin', 'super')) {
    echo xlt('Access denied');
    exit;
}

$globals = OEGlobalsBag::getInstance();
$webroot = $globals->getString('webroot');
$publicBaseUrl = $webroot . '/interface/modules/custom_modules/oe-module-fqhc/public';

// The style guide's own scaffolding CSS is page-scoped: it is the furniture the
// specimens sit on, and no workspace page should pay to download it.
$assets = new DesignSystemAssets(__DIR__, $publicBaseUrl, ['assets/css/showcase.css']);

$sheet = (new TokenSheetParser())->parseFile(__DIR__ . '/assets/css/tokens.css');

$tokenGroups = array_map(
    static fn(TokenGroup $group): array => [
        'label' => $group->label,
        'kind' => $group->kind()->value,
        'tokens' => array_map(
            static fn(DesignToken $token): array => [
                'name' => $token->name,
                'shortName' => $token->shortName(),
                'value' => $token->value,
                'comment' => $token->comment,
                'kind' => $token->kind->value,
            ],
            $group->tokens,
        ),
    ],
    $sheet->groups,
);

$audit = new ContrastAudit();
$pairs = $audit->measure($sheet->values());
$contrast = [
    'total' => count($pairs),
    'failing' => count($audit->failures($pairs)),
    'pairs' => array_map(
        static fn(ContrastPair $pair): array => [
            'usage' => $pair->usage,
            'foregroundName' => $pair->foregroundName,
            'foregroundValue' => $pair->foregroundValue,
            'backgroundName' => $pair->backgroundName,
            'backgroundValue' => $pair->backgroundValue,
            'ratio' => $pair->ratio,
            'rating' => $pair->rating->value,
            'ratingVariant' => $pair->rating->badgeVariant(),
            'largeText' => $pair->largeText,
        ],
        $pairs,
    ),
];

$content = (new TwigContainer(__DIR__ . '/../templates', $globals->getKernel()))
    ->getTwig()
    ->render('fqhc/showcase.html.twig', [
        'tokenGroups' => $tokenGroups,
        'tokenCount' => $sheet->count(),
        'contrast' => $contrast,
        // The vocabulary, straight from the enum, so the guide lists exactly
        // the names server-side code is allowed to ask for.
        'icons' => array_map(static fn(Icon $icon): string => $icon->value, Icon::cases()),
    ]);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('FQHC Design System'); ?></title>
    <?php Header::setupHeader(['common']); ?>
    <?php foreach ($assets->styleUrls() as $styleUrl) { ?>
        <link rel="stylesheet" href="<?php echo attr($styleUrl); ?>">
    <?php } ?>
</head>
<body class="body_top">
    <?php echo $content; ?>
    <?php foreach ($assets->scriptUrls() as $scriptUrl) { ?>
        <script type="module" src="<?php echo attr($scriptUrl); ?>"></script>
    <?php } ?>
</body>
</html>
