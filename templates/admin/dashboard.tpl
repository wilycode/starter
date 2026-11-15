@extends('layouts.app')
@use('components.pagination', 'pagination')

@section('content')
    <div class="page-heading admin-heading">
        <div class="eyebrow">{{ 'eyebrow' | trans }}</div>
        <h1>{{ 'title' | trans }}</h1>
        <p class="lead">{{ 'subtitle' | trans }}</p>
    </div>

    <section class="admin-card" aria-label="{{ 'title' | trans }}">
        <div class="admin-card-heading">
            <h2>{{ 'heading' | trans }}</h2>
            <span class="admin-total">{{ 'total' | trans }}: {{ $messages->total }}</span>
        </div>

        @if($messages->items === [])
            <p class="admin-empty">{{ 'empty' | trans }}</p>
        @else
            <div class="admin-table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ 'id' | trans }}</th>
                            <th scope="col">{{ 'sender' | trans }}</th>
                            <th scope="col">{{ 'date' | trans }}</th>
                            <th scope="col">{{ 'message' | trans }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($messages->items as $message)
                            <tr>
                                <td class="admin-id">{{ $message->getId() }}</td>
                                <td>
                                    <strong>{{ $message->getName() }}</strong>
                                    <span class="admin-email">{{ $message->getEmail() }}</span>
                                </td>
                                <td class="admin-date">{{ $message->getCreatedAt() | ldatetime }}</td>
                                <td class="admin-message">{{ $message->getMessage() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @pagination.nav($messages, 'admin.dashboard')
    </section>
@endsection
