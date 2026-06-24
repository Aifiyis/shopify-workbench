<?php

namespace App\Services;

class ColorTranslationResolver
{
    private $cachePath;
    private $options;
    private $httpFetcher;
    private $cacheLoaded = false;
    private $cache = [];

    public function __construct($cachePath = null, array $options = [], callable $httpFetcher = null)
    {
        $this->cachePath = $cachePath ?: storage_path('app/private/lookups/translation-cache.json');
        $this->options = array_merge([
            'enabled' => true,
            'endpoint' => 'https://api.mymemory.translated.net/get',
            'timeout' => 5,
            'email' => '',
        ], $options);
        $this->httpFetcher = $httpFetcher;
    }

    public static function fromConfig()
    {
        return new self(
            config('services.color_translation.cache_path', storage_path('app/private/lookups/translation-cache.json')),
            config('services.color_translation', [])
        );
    }

    public function translate($value)
    {
        $value = trim((string) $value);

        if ($value === '' || $this->containsChinese($value) || !$this->isEnabled()) {
            return $value;
        }

        $this->loadCache();
        $cacheKey = $this->cacheKey($value);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            $translated = $this->fetchTranslation($value);

            if ($translated === '') {
                return $value;
            }

            $result = $translated . '（翻译原值：' . $value . '）';
            $this->cache[$cacheKey] = $result;
            $this->writeCache();

            return $result;
        } catch (\Exception $e) {
            \Log::warning('Color translation fallback failed: ' . $e->getMessage());
            return $value;
        }
    }

    private function isEnabled()
    {
        return (bool) ($this->options['enabled'] ?? true);
    }

    private function fetchTranslation($value)
    {
        $url = $this->buildRequestUrl($value);
        $timeout = (int) ($this->options['timeout'] ?? 5);
        $body = $this->httpFetcher
            ? call_user_func($this->httpFetcher, $url, $timeout)
            : $this->fetchUrl($url, $timeout);
        $data = json_decode((string) $body, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Invalid translation response JSON.');
        }

        $translated = trim((string) ($data['responseData']['translatedText'] ?? ''));

        if ($translated === '') {
            throw new \RuntimeException('Translation response missing translated text.');
        }

        return $translated;
    }

    private function buildRequestUrl($value)
    {
        $query = [
            'q' => $value,
            'langpair' => 'en|zh-CN',
        ];

        $email = trim((string) ($this->options['email'] ?? ''));

        if ($email !== '') {
            $query['de'] = $email;
        }

        return rtrim((string) ($this->options['endpoint'] ?? ''), '?') . '?' . http_build_query($query);
    }

    private function fetchUrl($url, $timeout)
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0',
            ]);

            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($body === false || $status >= 400) {
                throw new \RuntimeException($error ?: "HTTP {$status} while translating color.");
            }

            return $body;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'header' => "User-Agent: Mozilla/5.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            throw new \RuntimeException('Unable to fetch translation response.');
        }

        return $body;
    }

    private function loadCache()
    {
        if ($this->cacheLoaded) {
            return;
        }

        $this->cacheLoaded = true;

        if (!is_string($this->cachePath) || !file_exists($this->cachePath)) {
            $this->cache = [];
            return;
        }

        $data = json_decode(file_get_contents($this->cachePath), true);
        $this->cache = is_array($data) ? $data : [];
    }

    private function writeCache()
    {
        $directory = dirname($this->cachePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($this->cachePath, json_encode($this->cache, JSON_PRETTY_PRINT));
    }

    private function cacheKey($value)
    {
        return strtolower(trim((string) $value));
    }

    private function containsChinese($value)
    {
        return preg_match('/\p{Han}/u', (string) $value) === 1;
    }
}
