<?php

namespace Tests\Unit;

use App\Services\LogoLookupResolver;
use Tests\TestCase;

class LogoLookupResolverTest extends TestCase
{
    private $tempDirectory;

    protected function tearDown(): void
    {
        if ($this->tempDirectory !== null) {
            $this->deleteDirectory($this->tempDirectory);
        }

        parent::tearDown();
    }

    public function test_resolves_team_logo_image_path_by_team_name()
    {
        $directory = $this->makeTempDirectory();
        $imagePath = $directory . '/logo/air-force-falcons.png';
        mkdir(dirname($imagePath), 0755, true);
        file_put_contents($imagePath, 'image-bytes');

        $jsonPath = $directory . '/logo_lookup.json';
        file_put_contents($jsonPath, json_encode([
            'Air Force Falcons' => [
                'group' => 'teamlogo',
                'name' => 'Air Force Falcons',
                'chinese_name' => '美国空军学院猎鹰队',
                'image_path' => 'logo/air-force-falcons.png',
            ],
        ], JSON_UNESCAPED_UNICODE));

        $resolver = new LogoLookupResolver($jsonPath, $directory);

        $this->assertSame(
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imagePath),
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolver->resolve('air force falcons'))
        );
        $this->assertSame('', $resolver->resolve('Unknown Team'));
    }

    public function test_resolves_real_team_logo_to_local_image_path()
    {
        $resolver = new LogoLookupResolver();

        $path = $resolver->resolve('Air Force Falcons');

        $this->assertNotSame('', $path);
        $this->assertFileExists($path);
    }

    private function makeTempDirectory()
    {
        if ($this->tempDirectory !== null) {
            return $this->tempDirectory;
        }

        $this->tempDirectory = storage_path('app/testing/logo-lookup-' . uniqid());
        mkdir($this->tempDirectory, 0755, true);

        return $this->tempDirectory;
    }

    private function deleteDirectory($directory)
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
