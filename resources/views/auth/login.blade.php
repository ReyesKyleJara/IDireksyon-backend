<x-guest-layout>
    <div class="mb-7 text-center"><h1 class="text-2xl font-bold tracking-tight text-[#012877]">IDireksyon</h1><p class="mt-1 text-sm text-slate-500">Content Management System</p></div>
    <form method="POST" action="{{ route('login') }}" class="space-y-5" x-data="{ showPassword: false }">
        @csrf
        <div><label for="username" class="mb-2 block text-sm font-medium text-slate-800">Username</label><input id="username" name="username" value="{{ is_string(old('username')) ? old('username') : '' }}" required autofocus autocomplete="username" maxlength="80" class="admin-input">@error('username')<p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror</div>
        <div>
            <label for="password" class="mb-2 block text-sm font-medium text-slate-800">Password</label>
            <div class="relative">
                <input id="password" name="password" type="password" :type="showPassword ? 'text' : 'password'" required autocomplete="current-password" class="admin-input pr-12">
                <button type="button" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'" :aria-pressed="showPassword.toString()" aria-label="Show password" class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-500 hover:text-[#012877]">
                    <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/><path x-show="showPassword" d="m3 3 18 18"/></svg>
                </button>
            </div>
            @error('password')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="admin-primary w-full">Sign In</button>
    </form>
    <p class="mt-6 text-center text-sm leading-6 text-slate-500">Need access or forgot your password?<br>Contact the System Administrator.</p>
</x-guest-layout>
