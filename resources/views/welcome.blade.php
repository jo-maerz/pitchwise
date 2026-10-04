<x-guest-layout>
    <div class="space-y-4 text-center">
        <h1 class="text-2xl font-semibold text-gray-900">Pitchwise</h1>
        <p class="text-gray-600">Play along with sheet music and see, note by note, whether you were in tune, too high or too low. A tuner and a score follower in one.</p>
        <div class="flex justify-center gap-3 pt-2">
            <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">Log in</a>
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">Register</a>
            @endif
        </div>
        <p class="text-xs text-gray-400">Demo login after seeding: demo@example.com / password</p>
    </div>
</x-guest-layout>
