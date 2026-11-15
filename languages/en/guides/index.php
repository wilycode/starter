<?php

declare(strict_types=1);

return [
    'eyebrow' => 'KNOWLEDGE BASE',
    'title' => 'Help and frequently asked questions',
    'subtitle' => 'A short guide to the Wily Starter example application. This public page also demonstrates full-page HTML caching.',
    'questions' => 'Frequently asked questions',
    'articles' => [
        'getting_started' => [
            'title' => 'How do I run Wily Starter?',
            'body' => 'Install dependencies with composer install, copy .env.example to .env, configure the database and run composer migrate.',
        ],
        'forms' => [
            'title' => 'How does the contact form work?',
            'body' => 'The form has separate GET and POST actions. Submitted data is validated, protected by a CSRF token and stored in MySQL when valid.',
        ],
        'messages' => [
            'title' => 'Where are messages stored?',
            'body' => 'Messages are stored in the contact_message table. ContactMessageRepository reads the data and AdminController displays a paginated inbox.',
        ],
        'admin' => [
            'title' => 'How do I open the admin dashboard?',
            'body' => 'Configure BASIC_AUTH_USERNAME and BASIC_AUTH_PASSWORD in your local .env. The browser prompts for HTTP Basic Auth credentials before showing private messages.',
        ],
        'caching' => [
            'title' => 'Why is this page cached?',
            'body' => 'Knowledge-base content is public and identical for visitors using the same language. Middleware saves the complete HTML, so following requests skip controller execution and template rendering.',
        ],
    ],
    'cache_title' => 'How is this page cached?',
    'cache_description' => 'The first visit builds the page and stores the HTML on disk (MISS). Later visits reuse the saved response (HIT) until the configured TTL expires.',
    'generated_at' => 'Generated on the server',
    'cache_hint' => 'Reload and inspect the X-Page-Cache response header in DevTools: HIT or MISS. The generation time remains unchanged for HIT. Ctrl+Shift+R may bypass the cache.',
    'contact_link' => 'Try the contact form',
];
