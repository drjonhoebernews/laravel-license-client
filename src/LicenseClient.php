<?php

namespace Cmapps\LaravelLicenseClient;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class LicenseClient
{
    protected string $e;
    protected array $p;
    protected int $t = 5;

    public function __construct(array $d = [])
    {
        if (!function_exists('app')) {
            throw new \RuntimeException('Environment Error');
        }

        $this->e = $this->x();

        $k = $d['license_key'] ?? env('LICENSE_KEY');

        if (!$k) {
            $k = $this->z();
            if ($k) {
                $this->s('LICENSE_KEY', $k);
            }
        }

        $d['license_key'] = $k;
        $this->p = $this->q($d);
    }

    protected function q(array $d): array
    {
        $k = $d['license_key'] ?? '';
        $h = $d['domain'] ?? request()->getHost();
        $i = $d['ip'] ?? request()->ip();
        $a = config('license-client.app_id');

        $c = implode('|', [
            strval($k), strval($h), strval($i), strval($a),
        ]);

        Log::info("SD: " . $c);

        $sig = hash_hmac('sha256', $c, $this->g());

        Log::info("SIG: " . $sig);

        return [
            'license_key' => $k,
            'domain' => $h,
            'ip' => $i,
            'app_id' => $a,
            'signature' => $sig,
        ];
    }

    protected function g(): string
    {
        return 'XxM3t4S3cr3tK3yB3lirt!';
    }

    protected function x(): string
    {
        return base64_decode('aHR0cHM6Ly9wcm9kYXBpdjIuY21hcHBzLmV1L2FwaS92MS9saWNlbnNlL3ZlcmlmeQ==');
    }

    protected function z(): ?string
    {
        try {
            $h = request()->getHost();
            $i = request()->ip();
            $a = config('license-client.app_id');

            $c = implode('|', ['', $h, $i, $a]);
            $sig = hash_hmac('sha256', $c, $this->g());

            $r = Http::timeout($this->t)
                ->acceptJson()
                ->post($this->e, [
                    'license_key' => '',
                    'domain' => $h,
                    'ip' => $i,
                    'app_id' => $a,
                    'signature' => $sig,
                ]);

            return $r->successful() && $r->json('data.license_key')
                ? $r->json('data.license_key')
                : null;
        } catch (\Throwable $ex) {
            return null;
        }
    }

    protected function s(string $k, string $v): void
    {
        $f = base_path('.env');
        if (!File::exists($f) || !File::isWritable($f)) {
            return;
        }

        $c = File::get($f);

        $c = preg_match("/^{$k}=.*$/m", $c)
            ? preg_replace("/^{$k}=.*$/m", "{$k}=\"{$v}\"", $c)
            : $c . "\n{$k}=\"{$v}\"";

        File::put($f, $c);
    }

    public function v(): array
    {
        try {
            $r = Http::timeout($this->t)
                ->acceptJson()
                ->post($this->e, $this->p);

            return [
                'valid' => $r->successful(),
                'data' => $r->json('data'),
                'message' => $r->json('message'),
            ];
        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'message' => 'Lisans sunucusuna ulaşılamadı.',
                'exception' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }
}
