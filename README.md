# Laravel + S3 Object Storage (AWS)

Panduan memakai Amazon S3 sebagai penyimpanan file di Laravel via facade `Storage`.

> Status proyek ini: Laravel 13, PHP 8.5, Livewire 4. Driver `league/flysystem-aws-s3-v3` **sudah terinstall** (`composer.json`), `.env` **sudah dikonfigurasi** (`FILESYSTEM_DISK=s3`). Konfigurasi disk ada di `config/filesystems.php`.

Tujuan belajar:

1. Paham konsep disk (`local`, `public`, `s3`).
2. Paham konfigurasi `.env` untuk S3.
3. Bisa operasi dasar: simpan, baca, cek, URL, hapus, list file.
4. Paham bedanya URL publik vs signed URL (bucket private).

## 1. Prasyarat (sudah terpenuhi di proyek ini)

- Akun AWS + bucket S3 + IAM user dengan access key yang boleh akses bucket tersebut.
- Package `league/flysystem-aws-s3-v3` — cek dengan `composer show league/flysystem-aws-s3-v3`.
- Kalau mulai dari nol di proyek lain:

```shell
composer require league/flysystem-aws-s3-v3 "^3.0" --with-all-dependencies
```

Konsep singkat: S3 adalah object storage. Di Laravel kamu tidak panggil API AWS langsung, cukup pakai `Storage::disk('s3')->...`. Laravel (via Flysystem) yang menerjemahkannya ke API S3.

## 2. Konfigurasi `.env`

`config/filesystems.php:50-61` membaca env berikut. Contoh yang sudah jalan di proyek ini:

```ini
FILESYSTEM_DISK=s3

AWS_ACCESS_KEY_ID=isi-access-key-id-kamu
AWS_SECRET_ACCESS_KEY=isi-secret-access-key-kamu
AWS_DEFAULT_REGION=ap-southeast-2
AWS_BUCKET=nama-bucket-kamu
AWS_USE_PATH_STYLE_ENDPOINT=false
```

Catatan:

- `AWS_URL` dan `AWS_ENDPOINT` dikosongkan untuk S3 asli AWS. Keduanya hanya diisi kalau memakai S3-compatible lokal (MinIO/RustFS).
- `FILESYSTEM_DISK=s3` berarti `Storage::` tanpa `disk()` memakai S3. Di kode proyek ini avatar ditulis eksplisit via `Storage::disk('s3')`, jadi aman walau default diganti.
- Jangan commit `.env` berisi credential asli.

Setelah ubah `.env`, bersihkan cache config kalau sebelumnya di-cache:

```shell
php artisan config:clear
```

## 3. Penjelasan `config/filesystems.php`

```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
],
```

- `local`: simpan di `storage/app/private`, tidak bisa diakses publik langsung.
- `public`: simpan di `storage/app/public`, bisa diakses via `/storage` setelah `php artisan storage:link`.
- `s3`: simpan di bucket AWS. Path seperti `avatars/abc.jpg` menjadi object key di bucket.

## 4. Operasi dasar

```php
use Illuminate\Support\Facades\Storage;

// Simpan teks / isi file
Storage::disk('s3')->put('contoh/hello.txt', 'Halo S3!');

// Cek file ada
Storage::disk('s3')->exists('contoh/hello.txt');

// Baca isi file
$content = Storage::disk('s3')->get('contoh/hello.txt');

// Hapus file
Storage::disk('s3')->delete('contoh/hello.txt');

// List file dalam folder
$files = Storage::disk('s3')->files('contoh');
$allFiles = Storage::disk('s3')->allFiles('contoh');
```

Upload dari form (validasi 1MB, contoh dari fitur user di proyek ini):

```php
$request->validate([
    'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
]);

$path = $request->file('avatar')->store('avatars', 's3');
```

Cek cepat via tinker (PowerShell):

```shell
php artisan tinker --execute "Storage::disk('s3')->put('tes/koneksi.txt', 'ok');"
php artisan tinker --execute "var_dump(Storage::disk('s3')->exists('tes/koneksi.txt'));"
```

## 5. Penting: bucket private → pakai signed URL

Bucket di proyek ini **private**, jadi `Storage::disk('s3')->url()` menghasilkan URL polos yang dibalas S3 dengan `AccessDenied` saat dibuka di browser. Itu normal, bukan bug.

Solusi yang dipakai di `app/Models/User.php` (`avatarUrl()`): generate **signed temporary URL** yang berlaku 1 jam dan dibuat ulang setiap halaman di-load:

```php
$disk = Storage::disk('s3');

if ($disk->providesTemporaryUrls()) {
    return $disk->temporaryUrl($this->avatar_path, now()->addHour());
}

return $disk->url($this->avatar_path);
```

Implikasinya:

- Di console AWS, tombol **Open** pada object juga akan `AccessDenied` — pakai **Download** atau **Share with a presigned URL** untuk melihat file dari console.
- Jangan simpan URL avatar untuk jangka panjang (misal di email) karena kedaluwarsa 1 jam — selalu panggil `avatarUrl()` saat render.

## 6. Fitur di proyek ini: User Management + avatar S3

Halaman tunggal `/` (`UserController@index`, view `resources/views/users/index.blade.php`):

- List user + tambah/edit/hapus via modal (Controller + Blade + Alpine.js).
- Avatar wajib gambar (`jpg/jpeg/png/webp`), maksimal 1MB, disimpan di `avatars/` pada disk `s3`.
- File lama dihapus dari S3 saat avatar diganti atau user dihapus.
- Test: `tests/Feature/UserManagementTest.php` (pakai `Storage::fake('s3')`), jalankan dengan `php artisan test --filter=UserManagementTest`.

## 7. Troubleshooting

| Gejala | Kemungkinan penyebab |
| --- | --- |
| `InvalidAccessKeyId` / `SignatureDoesNotMatch` | `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` salah atau ada spasi. |
| `NoSuchBucket` | `AWS_BUCKET` salah ketik atau beda region dengan `AWS_DEFAULT_REGION`. |
| `Unable to resolve endpoint` / timeout | `AWS_DEFAULT_REGION` salah, atau butuh `AWS_ENDPOINT` kalau pakai S3-compatible lokal. |
| `403 Forbidden` saat `put`/`delete` | IAM user tidak punya izin (`s3:PutObject` / `s3:DeleteObject`) ke bucket tersebut. |
| `AccessDenied` saat membuka URL gambar / tombol Open di console | Normal untuk bucket private — pakai signed URL (`temporaryUrl()`), atau Download dari console. |
| Config tidak berubah setelah edit `.env` | Jalankan `php artisan config:clear`. |

## 8. Referensi

- [Laravel 13 — Filesystem: S3 Driver Configuration](https://laravel.com/docs/13.x/filesystem#s3-driver-configuration)
- [Laravel 13 — Filesystem: Retrieving Files & Temporary URLs](https://laravel.com/docs/13.x/filesystem#retrieving-files)
- [Laravel 13 — File Storage Testing (`Storage::fake`)](https://laravel.com/docs/13.x/filesystem#testing)
- [Flysystem — AWS S3 Adapter](https://flysystem.thephpleague.com/docs/adapter/aws-s3-v3/)
- [AWS S3 — Overview & Buckets](https://docs.aws.amazon.com/s3/)
- [AWS S3 — Sharing Objects with Presigned URLs](https://docs.aws.amazon.com/AmazonS3/latest/userguide/ShareObjectPreSignedURL.html)
- [AWS IAM — Actions for S3](https://docs.aws.amazon.com/service-authorization/latest/reference/list_amazons3.html)
- [AWS CLI — `s3 presign` (buat presigned URL dari terminal)](https://awscli.amazonaws.com/v2/documentation/api/latest/reference/s3/presign.html)
