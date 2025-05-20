# Laravel License Client SDK

Laravel uygulamalarınızda lisans doğrulama işlemlerini hızlı, güvenli ve bypass edilemez şekilde gerçekleştirmek için geliştirilmiş resmi CMapps SDK'sıdır.

## 🚀 Özellikler

- 🔒 Şifreli lisans anahtarı doğrulama (AES + Base64)
- 🔐 SDK içinden gizlenmiş endpoint (obfuscation)
- 🧠 Signature ile sahte API engelleme (HMAC SHA256)
- ☁️ Laravel cache desteği
- 🛡️ Domain, IP, App ID kontrolü
- ❌ Laravel dışı ortamlarda çalışmayı engeller

---

## ⚙️ Kurulum

```bash

composer require cmapps/laravel-license-client

php artisan vendor:publish --tag=license-client-config

```

.env dosyanıza aşağıdaki satırı ekleyin:
```
LICENSE_APP_ID=your-app-id
```
Bu app_id, lisans üretimi sırasında sunucu tarafında tanımlanmış ID ile eşleşmelidir.


```
use Cmapps\LaravelLicenseClient\LicenseClient;

$client = new LicenseClient([
    'license_key' => 'ZXlKcGRpSTZJbmszWmxSTGFEaH...',
    'domain' => request()->getHost(),
    'ip' => request()->ip(),
]);

$response = $client->verify();

if ($response['valid']) {
    $expiresAt = $response['data']['expires_at'];
    $templateId = $response['data']['product_template_id'];
} else {
    // Hata: $response['message']
}

```