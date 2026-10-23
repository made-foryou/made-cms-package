<?php

declare(strict_types=1);

namespace Made\CmsPackage\Support;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Draws a QR code as inline svg: nachtgroen modules on krijt, always dark on light so every scanner reads it.
 */
final class QrCode
{
    public static function svg(string $text): string
    {
        $style = new RendererStyle(184, 1, null, null, Fill::uniformColor(new Rgb(243, 241, 232), new Rgb(14, 43, 38)));
        $svg = (new Writer(new ImageRenderer($style, new SvgImageBackEnd)))->writeString($text);

        return trim((string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg));
    }
}
