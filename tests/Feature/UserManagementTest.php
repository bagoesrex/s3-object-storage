<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
});

test('home menampilkan daftar user', function () {
    User::factory()->create(['name' => 'Sinta Pratama']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Sinta Pratama');
});

test('home tetap tampil saat user punya avatar', function () {
    $user = User::factory()->create(['name' => 'Rina Avatar']);
    $user->forceFill(['avatar_path' => UploadedFile::fake()->create('rina.jpg', 400, 'image/jpeg')->store('avatars', 's3')])->saveQuietly();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Rina Avatar');
});

test('user bisa dibuat dengan avatar maksimal 1MB', function () {
    $response = $this->post(route('users.store'), [
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'password' => 'password123',
        'avatar' => UploadedFile::fake()->create('avatar.jpg', 500, 'image/jpeg'),
    ]);

    $response->assertRedirect(route('home'));

    $user = User::where('email', 'budi@example.com')->firstOrFail();

    expect($user->avatar_path)->not->toBeNull();
    Storage::disk('s3')->assertExists($user->avatar_path);
});

test('avatar di atas 1MB ditolak', function () {
    $response = $this->post(route('users.store'), [
        'name' => 'Gagal Upload',
        'email' => 'gagal@example.com',
        'password' => 'password123',
        'avatar' => UploadedFile::fake()->create('besar.jpg', 1500, 'image/jpeg'),
    ]);

    $response->assertSessionHasErrors('avatar');
    expect(User::where('email', 'gagal@example.com')->exists())->toBeFalse();
});

test('update mengganti avatar dan menghapus file lama', function () {
    $user = User::factory()->create();
    $user->forceFill(['avatar_path' => UploadedFile::fake()->create('lama.jpg', 400, 'image/jpeg')->store('avatars', 's3')])->saveQuietly();
    $oldPath = $user->avatar_path;

    $response = $this->put(route('users.update', $user), [
        'name' => 'Nama Baru',
        'email' => $user->email,
        'avatar' => UploadedFile::fake()->create('baru.jpg', 400, 'image/jpeg'),
    ]);

    $response->assertRedirect(route('home'));
    Storage::disk('s3')->assertMissing($oldPath);
    Storage::disk('s3')->assertExists($user->fresh()->avatar_path);
});

test('hapus user juga menghapus avatar di s3', function () {
    $user = User::factory()->create();
    $user->forceFill(['avatar_path' => UploadedFile::fake()->create('hapus.jpg', 400, 'image/jpeg')->store('avatars', 's3')])->saveQuietly();
    $path = $user->avatar_path;

    $this->delete(route('users.destroy', $user))->assertRedirect(route('home'));

    Storage::disk('s3')->assertMissing($path);
    expect(User::find($user->id))->toBeNull();
});
