@extends('layouts.page')

@section('title', 'Set up Kinlore')

@php
    $fields = [
        'first_name' => ['label' => 'First name', 'type' => 'text', 'autocomplete' => 'given-name'],
        'last_name' => ['label' => 'Last name', 'type' => 'text', 'autocomplete' => 'family-name'],
        'friendly_name' => ['label' => 'Friendly name', 'type' => 'text', 'autocomplete' => 'off', 'optional' => true, 'hint' => 'Your family sees this name in the app and on invitations. It fills in from your name, for example "Anna Example Family". Change it to anything you like; you can also change it later in the app.'],
        'email' => ['label' => 'Email', 'type' => 'email', 'autocomplete' => 'email'],
        'password' => ['label' => 'Password', 'type' => 'password', 'autocomplete' => 'new-password', 'hint' => 'At least 8 characters.'],
        'password_confirmation' => ['label' => 'Repeat the password', 'type' => 'password', 'autocomplete' => 'new-password'],
        'setup_token' => ['label' => 'Setup token', 'type' => 'password', 'autocomplete' => 'off', 'hint' => 'The server log shows it at start. You can also run "php artisan kinlore:setup-token" on the server. If you set SETUP_TOKEN in the server settings, enter that value.'],
    ];
@endphp

@section('content')
    <h1>Set up {{ $instanceName }}</h1>
    <p>Create the admin account. You will use this email and password to sign in to the Kinlore app.</p>

    @if ($errors->any())
        <div class="alert" role="alert">
            <p>Correct the fields marked below and try again.</p>
        </div>
    @endif

    <form method="post" action="{{ url('/setup') }}" novalidate>
        @csrf
        @foreach ($fields as $field => $spec)
            @php($describedBy = trim((isset($spec['hint']) ? "$field-hint " : '') . ($errors->has($field) ? "$field-error" : '')))
            <label for="{{ $field }}">{{ $spec['label'] }}</label>
            <input id="{{ $field }}" name="{{ $field }}" type="{{ $spec['type'] }}" autocomplete="{{ $spec['autocomplete'] }}" @empty($spec['optional']) required @endempty
                @if ($spec['type'] !== 'password') value="{{ old($field) }}" @endif
                @error($field) aria-invalid="true" @enderror
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>
            @isset($spec['hint'])<p class="hint" id="{{ $field }}-hint">{{ $spec['hint'] }}</p>@endisset
            @error($field)<p class="error" id="{{ $field }}-error">{{ $message }}</p>@enderror
        @endforeach
        <button type="submit">Create admin account</button>
    </form>
    <script>
        // Fill "Friendly name" from the first and last name until the admin types in it. Without script, the
        // field stays as typed, and the server uses the same suggestion when it is empty.
        (() => {
            const first = document.getElementById('first_name');
            const last = document.getElementById('last_name');
            const friendly = document.getElementById('friendly_name');
            const suggestion = () => {
                const full = `${first.value} ${last.value}`.trim().replace(/\s+/g, ' ');
                return full ? `${full.slice(0, {{ \App\Services\FamilyService::NAME_MAX_LENGTH - 7 }}).trim()} Family` : '';
            };
            // A value from a failed submit counts as the admin's own when it differs from the suggestion.
            let own = friendly.value !== '' && friendly.value !== suggestion();
            const fill = () => { if (!own) friendly.value = suggestion(); };
            first.addEventListener('input', fill);
            last.addEventListener('input', fill);
            friendly.addEventListener('input', () => { own = friendly.value.trim() !== ''; if (!own) fill(); });
        })();
    </script>
@endsection
