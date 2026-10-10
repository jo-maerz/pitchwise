<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4" x-data="{ accountType: @js(old('account_type')) }">
            <fieldset>
                <legend class="block font-medium text-sm text-gray-700">{{ __('Are you a private user?') }}</legend>
                <div class="mt-2 space-y-2 text-sm text-gray-700">
                    <label class="flex items-start gap-2">
                        <input type="radio" name="account_type" value="private" x-model="accountType" class="mt-0.5 text-indigo-600" @checked(old('account_type') === 'private') required>
                        <span>{{ __('Yes, I practise on my own') }} <span class="block text-gray-500">{{ __('You see the shared library and keep your own annotations.') }}</span></span>
                    </label>
                    <label class="flex items-start gap-2">
                        <input type="radio" name="account_type" value="organization" x-model="accountType" class="mt-0.5 text-indigo-600" @checked(old('account_type') === 'organization')>
                        <span>{{ __('No, I belong to a school or orchestra') }} <span class="block text-gray-500">{{ __('You also see its library and its shared annotations.') }}</span></span>
                    </label>
                </div>
            </fieldset>
            <x-input-error :messages="$errors->get('account_type')" class="mt-2" />

            <div class="mt-4" x-show="accountType === 'organization'" x-cloak>
                <x-input-label for="organization" :value="__('Your institution')" />
                <x-text-input id="organization" class="block mt-1 w-full" type="search" name="organization" list="organizations" :value="old('organization')" x-bind:required="accountType === 'organization'" autocomplete="off" placeholder="{{ __('Start typing its name') }}" />
                <datalist id="organizations">
                    @foreach ($organizations as $organization)
                        <option value="{{ $organization->name }}"></option>
                    @endforeach
                </datalist>
                <x-input-error :messages="$errors->get('organization')" class="mt-2" />
            </div>
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
