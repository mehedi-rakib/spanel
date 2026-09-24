<?php

namespace App\Utils;

use Milon\Barcode\Facades\DNS1DFacade as DNS1D;

/**
 * Scannable Code128 barcodes for printed product labels.
 *
 * The old labels used DNS1D::getBarcodeHTML() at 2px per module inside a ~210px
 * label with overflow:hidden. Almost every code (even "SKU-001", 224px) was wider
 * than the label, so the start/stop patterns were clipped and nothing could scan.
 *
 * This renders an SVG sized in *modules* via viewBox, adds the 10-module white
 * quiet zone Code128 needs on each side, and stretches it to 100% of the label
 * width: it always fits, and every bar keeps its relative width.
 */
class BarcodeLabel
{
    /** Quiet zone on each side, in modules (Code128 minimum is 10). */
    private const QUIET_ZONE = 10;

    /** Narrowest bar most handheld scanners read reliably, in mm. */
    public const MIN_MODULE_MM = 0.19;

    public static function svg(string $code, float $heightMm = 12): string
    {
        // w=1 gives coordinates in modules; showCode=false (text is printed separately), inline=true (no XML prolog).
        $svg = DNS1D::getBarcodeSVG($code, 'C128', 1, 60, 'black', false, true);
        $modules = self::modules($svg);
        $total = $modules + 2 * self::QUIET_ZONE;

        return preg_replace(
            '/<svg width="[^"]*" height="[^"]*"/',
            sprintf(
                '<svg class="barcode-svg" viewBox="%d 0 %d 60" width="100%%" height="%smm" preserveAspectRatio="none" role="img" aria-label="%s"',
                -self::QUIET_ZONE,
                $total,
                $heightMm,
                e($code)
            ),
            $svg,
            1
        );
    }

    /**
     * Width of one bar module when the barcode is printed across $printWidthMm.
     * Below MIN_MODULE_MM the code is too long for the label to scan reliably.
     */
    public static function moduleWidthMm(string $code, float $printWidthMm): float
    {
        $modules = self::modules(DNS1D::getBarcodeSVG($code, 'C128', 1, 60, 'black', false, true));
        return $printWidthMm / ($modules + 2 * self::QUIET_ZONE);
    }

    private static function modules(string $svg): int
    {
        return preg_match('/<svg width="([\d.]+)"/', $svg, $m) ? (int)ceil((float)$m[1]) : 0;
    }
}
