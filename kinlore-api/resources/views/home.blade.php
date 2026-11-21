@extends('layouts.page')

@section('title', 'Kinlore is running')

@section('content')
    <h1>{{ $instanceName }}</h1>
    <p>Kinlore is running on this server.</p>
    <h2>Use it on your phone</h2>
    @include('partials.app-steps')
    <p>New family members need an invitation from the admin. The admin creates invitations in the app.</p>
@endsection
