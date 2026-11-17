<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\Browsershot\Browsershot;

class BrowserPdf
{
    /**
     * Browsershot instance wired to the binaries configured for this host.
     */
    public static function fromHtml(string $html): Browsershot
    {
        $shot = Browsershot::html($html)
            ->setNodeBinary((string) config('browsershot.node_binary'));

        $chromePath = config('browsershot.chrome_path');

        if (is_string($chromePath) && $chromePath !== '') {
            $shot->setChromePath($chromePath);
        }

        return $shot;
    }
}
