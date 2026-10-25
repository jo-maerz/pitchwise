<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @can('change-account')
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>

                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>

                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            @else
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <section class="max-w-xl">
                        <h2 class="text-lg font-medium text-gray-900">{{ __('Shared demo account') }}</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ $user->name }} · {{ $user->email }} · {{ __('Organization') }}: <span class="font-medium">{{ $user->organization?->name ?? __('None') }}</span>
                            · {{ __('Role') }}: <span class="font-medium">{{ $user->role->label() }}</span>
                        </p>
                        <p class="mt-2 text-sm text-gray-600">
                            {{ __('Everyone trying the demo logs in with this account, so its name, email address and password cannot be changed and it cannot be deleted.') }}
                            @if (filled(config('practice.demo_password')))
                                {{ __('Everything else you change is put back every night.') }}
                            @endif
                        </p>
                    </section>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
