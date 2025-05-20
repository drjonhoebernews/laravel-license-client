<?php

namespace Cmapps\LaravelLicenseClient;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LicenseClient
{
    protected string $endpoint;
    protected array $payload;
    protected int $timeout = 5;
    protected string $cacheKey;

    public function __construct(array $payload)
    {
        if (!function_exists('app')) {
            throw new \RuntimeException('Laravel ortamı dışında kullanılamaz.');
        }

        $this->endpoint = $this->hiddenEndpoint();
        $this->payload = $this->preparePayload($payload);
        $this->cacheKey = 'license_valid_' . md5($this->payload['license_key']);
    }

    protected function preparePayload(array $payload): array
    {
        $license_key = $payload['license_key'];
        $domain = $payload['domain'] ?? request()->getHost();
        $ip = $payload['ip'] ?? request()->ip();
        $app_id = config('license-client.app_id');

        $signature = hash_hmac('sha256', $license_key . $domain . $ip . $app_id, $this->sdkSecret());

        return [
            'license_key' => $license_key,
            'domain' => hash('sha256', $domain),
            'ip' => $ip ? hash('sha256', $ip) : null,
            'app_id' => $app_id,
            'signature' => $signature,
        ];
    }

    protected function sdkSecret(): string
    {
        // Bu key sadece senin SDK içinde olmalı, sunucu da bunu bilmek zorunda
        return 'XxM3t4S3cr3tK3yB3lirt!';
    }

    protected function hiddenEndpoint(): string
    {
        $encoded = [
            114, 103, 122, 122, 106, 104, 116, 58, 47, 47,
            113, 115, 110, 117, 119, 99, 120, 107, 49, 56,
            46, 100, 112, 98, 117, 114, 114, 46, 102, 120,
            106, 122, 110, 111, 46, 102, 115, 47, 100, 113,
            109, 112, 47, 119, 48, 49, 47, 110, 107, 106,
            97, 114, 99, 102
        ];

        return collect($encoded)
            ->map(fn($c) => chr($c - 3))
            ->implode('');
    }

    public function verify(bool $force = false): array
    {
        if (!$force && Cache::has($this->cacheKey)) {
            return Cache::get($this->cacheKey);
        }

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post("{$this->endpoint}/verify", $this->payload);

            $data = [
                'valid' => $response->successful(),
                'data' => $response->json('data'),
                'message' => $response->json('message'),
            ];

            if ($data['valid']) {
                Cache::put($this->cacheKey, $data, now()->addMinutes(30));
            }

            return $data;
        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'message' => 'Lisans sunucusuna ulaşılamadı.',
                'exception' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }
}
