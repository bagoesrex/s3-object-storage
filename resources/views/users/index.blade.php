<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Users — {{ config('app.name', 'Laravel') }}</title>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
<div class="mx-auto min-h-screen max-w-5xl px-6 py-12" x-data="{ createOpen: false, editId: null }">

    <header class="flex flex-wrap items-end justify-between gap-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">User management</p>
            <h1 class="mt-2 text-4xl font-semibold tracking-tight">Users</h1>
            <p class="mt-2 max-w-md text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">
                {{ $users->total() }} user terdaftar. Avatar disimpan di S3, maksimal 1MB.
            </p>
        </div>
        <button
            type="button"
            @click="createOpen = true"
            class="rounded-full bg-zinc-900 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white">
            + Tambah user
        </button>
    </header>

    @if (session('success'))
        <p class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
            {{ session('success') }}
        </p>
    @endif

    @if ($errors->any())
        <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
            <p class="font-medium">Periksa kembali input kamu:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <main class="mt-8 overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        @forelse ($users as $user)
            @if ($loop->first)
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @endif
                <li class="flex items-center gap-4 px-5 py-4">
                    @if ($user->avatarUrl())
                        <img src="{{ $user->avatarUrl() }}" alt="Avatar {{ $user->name }}" class="h-11 w-11 rounded-full object-cover ring-1 ring-zinc-200 dark:ring-zinc-700">
                    @else
                        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-zinc-900 text-sm font-semibold text-white dark:bg-zinc-100 dark:text-zinc-900">
                            {{ $user->initials() }}
                        </span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[15px] font-medium tracking-tight">{{ $user->name }}</p>
                        <p class="truncate text-sm text-zinc-500 dark:text-zinc-400">{{ $user->email }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="editId = {{ $user->id }}" class="rounded-full border border-zinc-200 px-4 py-1.5 text-sm transition hover:border-zinc-900 dark:border-zinc-700 dark:hover:border-zinc-100">
                            Edit
                        </button>
                        <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Hapus {{ $user->name }}?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-full border border-transparent px-4 py-1.5 text-sm text-red-600 transition hover:border-red-200 hover:bg-red-50 dark:hover:bg-red-950">
                                Hapus
                            </button>
                        </form>
                    </div>
                </li>

                <!-- Edit modal -->
                <div x-show="editId === {{ $user->id }}" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 p-4">
                    <div class="w-full max-w-md rounded-2xl bg-white p-6 dark:bg-zinc-900" @click.away="editId = null">
                        <h2 class="text-xl font-semibold tracking-tight">Edit {{ $user->name }}</h2>
                        <form method="POST" action="{{ route('users.update', $user) }}" enctype="multipart/form-data" class="mt-4 flex flex-col gap-4">
                            @csrf
                            @method('PUT')
                            <label class="flex flex-col gap-1 text-sm">
                                Nama
                                <input name="name" value="{{ old('name', $user->name) }}" required maxlength="255" class="rounded-xl border border-zinc-200 bg-transparent px-3 py-2 outline-none focus:border-zinc-900 dark:border-zinc-700">
                            </label>
                            <label class="flex flex-col gap-1 text-sm">
                                Email
                                <input type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="255" class="rounded-xl border border-zinc-200 bg-transparent px-3 py-2 outline-none focus:border-zinc-900 dark:border-zinc-700">
                            </label>
                            <label class="flex flex-col gap-1 text-sm">
                                Password baru <span class="text-zinc-400">(kosongkan jika tidak diganti)</span>
                                <input type="password" name="password" minlength="8" class="rounded-xl border border-zinc-200 bg-transparent px-3 py-2 outline-none focus:border-zinc-900 dark:border-zinc-700">
                            </label>
                            <label class="flex flex-col gap-1 text-sm">
                                Avatar <span class="text-zinc-400">(jpg/png/webp, maks 1MB)</span>
                                <input type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp" class="text-sm">
                            </label>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="editId = null" class="rounded-full px-4 py-2 text-sm text-zinc-500">Batal</button>
                                <button type="submit" class="rounded-full bg-zinc-900 px-5 py-2 text-sm font-medium text-white dark:bg-zinc-100 dark:text-zinc-900">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
            @if ($loop->last)
                </ul>
            @endif
        @empty
            <p class="px-5 py-16 text-center text-sm text-zinc-500">Belum ada user. Klik “Tambah user” untuk membuat yang pertama.</p>
        @endforelse
    </main>

    <div class="mt-6">
        {{ $users->links() }}
    </div>

    <!-- Create modal -->
    <div x-show="createOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 dark:bg-zinc-900" @click.away="createOpen = false">
            <h2 class="text-xl font-semibold tracking-tight">Tambah user</h2>
            <form method="POST" action="{{ route('users.store') }}" enctype="multipart/form-data" class="mt-4 flex flex-col gap-4">
                @csrf
                <label class="flex flex-col gap-1 text-sm">
                    Nama
                    <input name="name" value="{{ old('name') }}" required maxlength="255" class="rounded-xl border border-zinc-200 bg-transparent px-3 py-2 outline-none focus:border-zinc-900 dark:border-zinc-700">
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Email
                    <input type="email" name="email" value="{{ old('email') }}" required maxlength="255" class="rounded-xl border border-zinc-200 bg-transparent px-3 py-2 outline-none focus:border-zinc-900 dark:border-zinc-700">
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Password
                    <input type="password" name="password" required minlength="8" class="rounded-xl border border-zinc-200 bg-transparent px-3 py-2 outline-none focus:border-zinc-900 dark:border-zinc-700">
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Avatar <span class="text-zinc-400">(jpg/png/webp, maks 1MB)</span>
                    <input type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp" class="text-sm">
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="createOpen = false" class="rounded-full px-4 py-2 text-sm text-zinc-500">Batal</button>
                    <button type="submit" class="rounded-full bg-zinc-900 px-5 py-2 text-sm font-medium text-white dark:bg-zinc-100 dark:text-zinc-900">Simpan</button>
                </div>
            </form>
        </div>
    </div>

</div>
<style>[x-cloak] { display: none !important; }</style>
</body>
</html>
