<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;

trait CreatesTestImage
{
    protected function createTestImage(string $name = 'card.png', int $width = 300, int $height = 200): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }
}
