<?php

namespace App\Concerns;

use App\Git;
use DOMDocument;
use Illuminate\Support\Collection;
use Imagick;

use function Illuminate\Filesystem\join_paths;

trait HandlesAvatars
{
    protected function getAvatarCandidatesFromRepo(): Collection
    {
        $root = app(Git::class)->getRoot();

        $possiblePaths = collect([
            'favicon.png',
            'favicon.svg',
            'apple-touch-icon.png',
            'favicon.ico',
            'favicon.jpg',
            'favicon.jpeg',
        ])
            ->map(fn ($path) => join_paths($root, 'public', $path))
            ->filter(fn ($path) => file_exists($path))
            ->filter(function ($path) {
                $filename = basename($path);
                $resourcesPath = resource_path('images/'.$filename);

                if (file_exists($resourcesPath)) {
                    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                    if ($extension === 'svg') {
                        return $this->normalizeSvg(file_get_contents($path)) !== $this->normalizeSvg(file_get_contents($resourcesPath));
                    }

                    return md5_file($path) !== md5_file($resourcesPath);
                }

                return true;
            })
            ->values();

        if (class_exists('Imagick')) {
            // We can convert non-supported images to PNG, we're good
            return $possiblePaths;
        }

        return $possiblePaths
            ->filter(fn ($path) => pathinfo($path, PATHINFO_EXTENSION) === 'png')
            ->values();
    }

    protected function normalizeSvg(string $content): string
    {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        @$dom->loadXML($content);

        $dom->normalizeDocument();

        return $dom->saveXML();
    }

    /**
     * @return list<string>
     */
    protected function getAvatarFromPath(string $path): array
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp'])) {
            return [file_get_contents($path), $extension];
        }

        $imagick = new Imagick;
        $imagick->readImage($path);
        $imagick->setImageFormat('png');

        $blob = $imagick->getImageBlob();
        $imagick->clear();

        return [$blob, 'png'];
    }
}
