# Laravel + S3 Object Storage (AWS)

Panduan dasar memakai Amazon S3 sebagai penyimpanan file di Laravel via facade `Storage`.

Tujuan belajar:

1. Paham konsep disk (`local`, `public`, `s3`).
2. Bisa konfigurasi `.env` untuk S3.
3. Bisa melakukan operasi dasar: simpan, baca, cek, URL, hapus, list file.

> Proyek ini: Laravel 13, PHP 8.5, Livewire 4. Konfigurasi disk ada di `config/filesystems.php`.

## 1. Prasyarat

- Akun AWS + satu bucket S3 (misal `nama-bucket-belajar`, region `ap-southeast-1`).
- IAM user dengan access key yang boleh akses bucket tersebut.
- Composer + PHP sudah jalan (`composer install` sudah pernah dijalankan).
- Paham dasar routing/controller Laravel.

Konsep singkat: S3 adalah object storage. Di Laravel kamu tidak panggil API AWS langsung, cukup pakai `Storage::disk('s3')->...`. Laravel (via Flysystem) yang menerjemahkannya ke API S3.

## 2. Instalasi driver S3

Disk `s3` di `config/filesystems.php` butuh package tambahan. Di proyek ini package-nya **belum terinstall**.

```shell
composer require league/flysystem-aws-s3-v3 "^3.0" --with-all-dependencies
```

## 3. Konfigurasi `.env`

Contoh konfigurasi di `config/filesystems.php:50-61` membaca env berikut:

```ini
FILESYSTEM_DISK=s3

AWS_ACCESS_KEY_ID=isi-access-key-id-kamu
AWS_SECRET_ACCESS_KEY=isi-secret-access-key-kamu
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=nama-bucket-kamu
AWS_URL=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

Catatan:

- `AWS_URL` dan `AWS_ENDPOINT` boleh dikosongkan untuk S3 asli AWS. Keduanya hanya diisi kalau memakai S3-compatible lokal (MinIO/RustFS).
- `FILESYSTEM_DISK` menentukan disk default saat kamu memanggil `Storage::` tanpa `disk()`. Saat belajar, biarkan `local` dulu, lalu panggil eksplisit `Storage::disk('s3')`.
- Jangan commit `.env` berisi credential asli. File contoh credential ada di `.env.example:59-63`.

Setelah ubah `.env`, bersihkan cache config kalau sebelumnya di-cache:

```shell
php artisan config:clear
```

## 4. Penjelasan `config/filesystems.php`

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
- `s3`: simpan di bucket AWS. Path seperti `foto/kucing.jpg` menjadi object key di bucket.

## 5. Operasi dasar

```php
use Illuminate\Support\Facades\Storage;

// Simpan teks / isi file
Storage::disk('s3')->put('contoh/hello.txt', 'Halo S3!');

// Cek file ada
Storage::disk('s3')->exists('contoh/hello.txt');

// Baca isi file
$content = Storage::disk('s3')->get('contoh/hello.txt');

// URL publik (hanya berguna jika object/bucket memang publik)
$url = Storage::disk('s3')->url('contoh/hello.txt');

// Hapus file
Storage::disk('s3')->delete('contoh/hello.txt');

// List file dalam folder
$files = Storage::disk('s3')->files('contoh');
$allFiles = Storage::disk('s3')->allFiles('contoh');
```

Upload dari form:

```php
use Illuminate\Http\Request;

public function store(Request $request)
{
    $request->validate([
        'foto' => ['required', 'file', 'max:2048'],
    ]);

    $path = $request->file('foto')->store('uploads', 's3');

    return $path;
}
```

Cek cepat via tinker:

```shell
php artisan tinker --execute 'Storage::disk("s3")->put("tes/koneksi.txt", "ok");'
php artisan tinker --execute 'var_dump(Storage::disk("s3")->exists("tes/koneksi.txt"));'
```

> Pakai single quote di PowerShell/CMD agar `$` dan `"` tidak di-expand shell. Contoh di atas memakai double quote di dalam single quote sesuai konvensi proyek ini.

## 6. Alur belajar yang disarankan

1. Coba semua operasi di atas dengan disk `local` dulu.
2. Ganti `disk('local')` menjadi `disk('s3')` tanpa mengubah kode lain.
3. Upload satu file kecil via tinker, pastikan muncul di console AWS S3.
4. Baru lanjut ke form upload sungguhan.

## 7. Troubleshooting dasar

| Gejala | Kemungkinan penyebab |
| --- | --- |
| `InvalidAccessKeyId` / `SignatureDoesNotMatch` | `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` salah atau ada spasi. |
| `NoSuchBucket` | `AWS_BUCKET` salah ketik atau beda region. |
| `Unable to resolve endpoint` / timeout | `AWS_DEFAULT_REGION` salah, atau butuh `AWS_ENDPOINT` kalau pakai S3-compatible lokal. |
| `403 Forbidden` saat `put` | IAM user tidak punya izin `s3:PutObject` ke bucket tersebut. |
| `url()` bisa dibuka tapi error AccessDenied | Normal jika object private. Itu bukan URL publik. Pembahasan signed/temporary URL di luar scope dasar ini. |
| Config tidak berubah setelah edit `.env` | Jalankan `php artisan config:clear`. |

## 8. Referensi

- Docs Laravel 13: Filesystem — S3 Driver Configuration
- File proyek: `config/filesystems.php`, `.env.example`
