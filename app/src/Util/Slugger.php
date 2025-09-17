<?php
declare(strict_types=1);

namespace App\Util;

use Symfony\Component\String\Slugger\AsciiSlugger;

final class Slugger
{
    public function __construct(private AsciiSlugger $slugger = new AsciiSlugger()){}

    public function base(string $text, ?string $locale = null): string
    {
        // "Ligue 1 Française" -> "ligue-1-francaise"
        return $this->slugger->slug($text, '-', $locale)->lower()->toString();
    }
}
