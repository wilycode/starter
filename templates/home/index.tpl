@extends('layouts.app')

@section('content')
    <section class="hero">
        <div class="eyebrow">{{ 'badge' | trans }}</div>
        <h1>{{ 'title' | trans }}</h1>
        <p class="lead">{{ 'description' | trans }}</p>
        <div class="hero-actions">
            <a class="button button-primary" href="@route('contact.index')">{{ 'cta' | trans }}</a>
            <a class="button button-secondary" href="https://github.com/wilycode" rel="noopener noreferrer" target="_blank">{{ 'github' | trans }} ↗</a>
        </div>
        <div class="example-filter">
            <span>{{ 'filter_label' | trans }}</span>
            <code>{{ 'filter_example' | trans | truncate(48) }}</code>
        </div>
    </section>
    <section class="feature-grid" aria-label="{{ 'features_label' | trans }}">
        <article class="feature-card"><span class="feature-icon">↗</span><h2>{{ 'router_title' | trans }}</h2><p>{{ 'router' | trans }}</p></article>
        <article class="feature-card"><span class="feature-icon">{ }</span><h2>{{ 'template_title' | trans }}</h2><p>{{ 'template' | trans }}</p></article>
        <article class="feature-card"><span class="feature-icon">▤</span><h2>{{ 'database_title' | trans }}</h2><p>{{ 'database' | trans }}</p></article>
    </section>
@endsection
