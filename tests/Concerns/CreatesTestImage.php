<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;

trait CreatesTestImage
{
    protected function createTestImage(string $name = 'card.png', int $width = 300, int $height = 200): UploadedFile
    {
        // Use a minimal PNG payload instead of GD-generated images so the test suite
        // can run without the GD extension installed in the environment.
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAA' .
            'AAC0lEQVR42mP8/x8AAwMBAAZqkSUAAAAASUVORK5CYII='
        );

        $path = tempnam(sys_get_temp_dir(), 'card_') ?: sys_get_temp_dir().'/card_'.uniqid();
        file_put_contents($path, $png);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }
}
