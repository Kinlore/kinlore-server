@extends('layouts.page')

@section('title', 'Set up Kinlore')

@php
    $fields = [
        'name' => ['label' => 'Your name', 'type' => 'text', 'autocomplete' => 'name'],
        'email' => ['label' => 'Email', 'type' => 'email', 'autocomplete' => 'email'],
        'family_name' => ['label' => 'Family name (optional)', 'type' => 'text', 'autocomplete' => 'off', 'optional' => true, 'hint' => 'Your family sees this name in the app and on invitations. Leave it empty to use your name, for example "Anna Example Family". You can change it later in the app.'],
        'password' => ['label' => 'Password', 'type' => 'password', 'autocomplete' => 'new-password', 'hint' => 'At least 8 characters.'],
        'password_confirmation' => ['label' => 'Repeat the password', 'type' => 'password', 'autocomplete' => 'new-password'],
    ];
    if ($setupTokenRequired) {
        $fields['setup_token'] = ['label' => 'Setup token', 'type' => 'password', 'autocomplete' => 'off', 'hint' => 'The value of SETUP_TOKEN in the server configuration.'];
    }
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
@endsection
