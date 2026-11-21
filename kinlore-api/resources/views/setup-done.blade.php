@extends('layouts.page')

@section('title', 'Setup finished')

@section('content')
    <h1>Your admin account is ready</h1>
    <p>{{ $instanceName }} is set up. You are its admin.</p>
    <h2>Next steps</h2>
    @include('partials.app-steps')
    <p>You can close this page.</p>
@endsection
