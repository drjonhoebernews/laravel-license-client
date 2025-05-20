<?php

namespace Cmapps\LaravelLicenseClient;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class LicenseClient
{
    protected string $endpoint;
    protected array $payload;
    protected int $timeout = 5;
    protected string $cacheKey;

    public function __construct(array $payload = [])
    {
        if (!function_exists('app')) {
            throw new \RuntimeException('Laravel ortamı dışında kullanılamaz.');
        }

        $this->endpoint = $this->hiddenEndpoint();

        $license_key = $payload['license_key'] ?? env('LICENSE_KEY');

        if (!$license_key) {
            $license_key = $this->autoFetchLicenseKey();
            if ($license_key) {
                $this->updateEnvFile('LICENSE_KEY', $license_key);
            }
        }

        $payload['license_key'] = $license_key;
        $this->payload = $this->preparePayload($payload);
        $this->cacheKey = 'license_valid_' . md5($this->payload['license_key']);
    }

    protected function preparePayload(array $payload): array
    {
        $license_key = $payload['license_key'];

        $rawDomain = $payload['domain'] ?? request()->getHost();
        $rawIp = $payload['ip'] ?? request()->ip();
        $app_id = config('license-client.app_id');

        $signature = hash_hmac('sha256', $license_key . $rawDomain . $rawIp . $app_id, $this->sdkSecret());

        return [
            'license_key' => $license_key,
            'domain' => hash('sha256', $rawDomain),
            'ip' => $rawIp ? hash('sha256', $rawIp) : null,
            'app_id' => $app_id,
            'signature' => $signature,
        ];
    }


    protected function sdkSecret(): string
    {
        return 'XxM3t4S3cr3tK3yB3lirt!';
    }

    protected function hiddenEndpoint(): string
    {
        return base64_decode('aHR0cHM6Ly9wcm9kYXBpdjIuY21hcHBzLmV1L2FwaS92MS9saWNlbnNlL3ZlcmlmeQ==');
    }

    protected function autoFetchLicenseKey(): ?string
    {
        try {
            $domain = request()->getHost();
            $ip = request()->ip();
            $app_id = config('license-client.app_id');
            $signature = hash_hmac('sha256', $domain . $ip . $app_id, $this->sdkSecret());

            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post($this->endpoint, [
                    'license_key' => '',
                    'domain' => hash('sha256', $domain),
                    'ip' => hash('sha256', $ip),
                    'app_id' => $app_id,
                    'signature' => $signature,
                ]);

            if ($response->successful() && $response->json('data.license_key')) {
                return $response->json('data.license_key');
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function updateEnvFile(string $key, string $value): void
    {
        $envPath = base_path('.env');
        if (!File::exists($envPath) || !File::isWritable($envPath)) {
            return;
        }

        $envContent = File::get($envPath);

        if (preg_match("/^{$key}=.*$/m", $envContent)) {
            $envContent = preg_replace("/^{$key}=.*$/m", "{$key}=\"{$value}\"", $envContent);
        } else {
            $envContent .= "\n{$key}=\"{$value}\"";
        }

        File::put($envPath, $envContent);
    }

    public function verify(bool $force = false): array
    {
        if (!$force && Cache::has($this->cacheKey)) {
            return Cache::get($this->cacheKey);
        }

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post($this->endpoint, $this->payload);

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
