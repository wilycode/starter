@extends('layouts.app')

@section('content')
    <div class="error-page">
        <div class="error-code">404</div>
        <h1>{{ 'title' | trans }}</h1>
        <p>{{ 'description' | trans }}</p>
        <a class="button button-primary" href="@route('home.index')">{{ 'back' | trans }}</a>
    </div>
@endsection
