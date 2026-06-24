<?php

namespace App\Services;

class LogoLookupResolver
{
    private $lookupPath;
    private $baseDirectory;
    private $loaded = false;
    private $logosByName = [];

    public function __construct($lookupPath = null, $baseDirectory = null)
    {
        $this->lookupPath = $lookupPath ?: storage_path('app/private/lookups/logo_lookup.json');
        $this->baseDirectory = $baseDirectory ?: storage_path('app');
    }

    public function resolve($name, $group = 'teamlogo')
    {
        $this->load();

        $key = $this->normalizeText($name);
        $group = $this->normalizeText($group);

        if ($key === '' || !isset($this->logosByName[$key])) {
            return '';
        }

        $logo = $this->logosByName[$key];
        if ($group !== '' && $this->normalizeText($logo['group'] ?? '') !== $group) {
            return '';
        }

        $imagePath = trim((string) ($logo['image_path'] ?? ''));
        if ($imagePath === '') {
            return '';
        }

        $absolutePath = $this->absolutePath($imagePath);

        return $absolutePath !== '' && file_exists($absolutePath) ? $absolutePath : '';
    }

    private function load()
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        if (!is_string($this->lookupPath) || !file_exists($this->lookupPath)) {
            return;
        }

        $data = json_decode(file_get_contents($this->lookupPath), true);
        if (!is_array($data)) {
            return;
        }

        foreach ($data as $key => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            foreach ([$key, $entry['name'] ?? '', $entry['chinese_name'] ?? ''] as $name) {
                $normalizedName = $this->normalizeText($name);

                if ($normalizedName !== '') {
                    $this->logosByName[$normalizedName] = $entry;
                }
            }
        }
    }

    private function absolutePath($imagePath)
    {
        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $imagePath) || strpos($imagePath, DIRECTORY_SEPARATOR) === 0) {
            return $imagePath;
        }

        return rtrim($this->baseDirectory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imagePath), DIRECTORY_SEPARATOR);
    }

    private function normalizeText($value)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/\s+/', ' ', $value);

        return $value;
    }
}
