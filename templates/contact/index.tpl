@extends('layouts.app')
@use('components.form', 'form')
@use('components.message', 'message')

@section('content')
    <div class="page-heading">
        <div class="eyebrow">{{ 'eyebrow' | trans }}</div>
        <h1>{{ 'title' | trans }}</h1>
        <p class="lead">{{ 'subtitle' | trans }}</p>
    </div>
    <section class="form-card">
        @message.flash($flash)
        @form.csrf_error($form)
        <form data-prevent-double-submit action="@route('contact.store')" method="post" novalidate>
            @form.csrf($form)
            @form.error_summary($form)
            @form.text_field($form->getField('name'), 'name', autocomplete: 'name', maxLength: 120)
            @form.email_field($form->getField('email'), 'email', autocomplete: 'email', maxLength: 254)
            @form.textarea_field($form->getField('message'), 'message', rows: 6, maxLength: 5000)
            <div class="form-actions">
                @form.submit_button()
                <span class="form-note">{{ 'hint' | trans }}</span>
            </div>
        </form>
    </section>
@endsection
