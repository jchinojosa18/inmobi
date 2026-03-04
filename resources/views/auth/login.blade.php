<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
</head>
<body class="bg-slate-100 text-slate-900">
    <main class="mx-auto flex min-h-screen max-w-md items-center p-6">
        <div class="w-full rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="mb-4 text-xl font-semibold">Login</h1>

            <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        required
                        autofocus
                        value="{{ old('email') }}"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                    >
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                >
                    Entrar
                </button>
            </form>
        </div>
    </main>
</body>
</html>
