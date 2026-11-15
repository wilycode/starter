@extends('layouts.app')

@section('content')
    <div class="page-heading guides-heading">
        <div class="eyebrow">{{ 'eyebrow' | trans }}</div>
        <h1>{{ 'title' | trans }}</h1>
        <p class="lead">{{ 'subtitle' | trans }}</p>
    </div>

    <section class="guides-layout" aria-label="{{ 'title' | trans }}">
        <div class="guides-list">
            <h2>{{ 'questions' | trans }}</h2>
            @foreach(['getting_started', 'forms', 'messages', 'admin', 'caching'] as $topic)
                <details class="guide-card">
                    <summary>{{ ('articles.' . $topic . '.title') | trans }}</summary>
                    <p>{{ ('articles.' . $topic . '.body') | trans }}</p>
                </details>
            @endforeach
        </div>
        <aside class="cache-explainer">
            <span class="feature-icon" aria-hidden="true">↺</span>
            <h2>{{ 'cache_title' | trans }}</h2>
            <p>{{ 'cache_description' | trans }}</p>
            <div class="cache-generated">
                <span>{{ 'generated_at' | trans }}</span>
                <time datetime="{{ $generatedAt->format(DATE_ATOM) }}">{{ $generatedAt | ldatetime('long') }}</time>
            </div>
            <p class="cache-hint">{{ 'cache_hint' | trans }}</p>
            <a class="button button-secondary" href="@route('contact.index')">{{ 'contact_link' | trans }}</a>
        </aside>
    </section>
@endsection
