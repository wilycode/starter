<?php

declare(strict_types=1);

return [
    'eyebrow' => 'BAZA WIEDZY',
    'title' => 'Pomoc i najczęstsze pytania',
    'subtitle' => 'Krótki przewodnik po przykładowej aplikacji Wily Starter. Ta publiczna strona pokazuje również, jak działa cache gotowego HTML.',
    'questions' => 'Najczęstsze pytania',
    'articles' => [
        'getting_started' => [
            'title' => 'Jak uruchomić Wily Starter?',
            'body' => 'Zainstaluj zależności przez composer install, skopiuj .env.example do .env, ustaw połączenie z bazą i wykonaj composer migrate.',
        ],
        'forms' => [
            'title' => 'Jak działa formularz kontaktowy?',
            'body' => 'Formularz ma osobne akcje GET i POST. Dane przechodzą walidację, token CSRF chroni żądanie, a poprawna wiadomość trafia do bazy MySQL.',
        ],
        'messages' => [
            'title' => 'Gdzie przechowywane są wiadomości?',
            'body' => 'Wiadomości trafiają do tabeli contact_message. ContactMessageRepository odpowiada za dostęp do danych, a AdminController wyświetla listę z paginacją.',
        ],
        'admin' => [
            'title' => 'Jak otworzyć panel administracyjny?',
            'body' => 'Ustaw BASIC_AUTH_USERNAME i BASIC_AUTH_PASSWORD w lokalnym .env. Przeglądarka poprosi o dane logowania HTTP Basic Auth przed udostępnieniem prywatnych wiadomości.',
        ],
        'caching' => [
            'title' => 'Dlaczego ta strona ładuje się z cache?',
            'body' => 'Treść poradnika jest publiczna i taka sama dla odwiedzających w danym języku. Middleware zapisuje kompletny HTML, więc następne żądania pomijają kontroler i renderowanie szablonu.',
        ],
    ],
    'cache_title' => 'Jak działa cache tej strony?',
    'cache_description' => 'Pierwsze wejście generuje stronę i zapisuje HTML na dysku (MISS). Kolejne odczytują gotowy wynik (HIT), dopóki nie minie skonfigurowany czas życia cache.',
    'generated_at' => 'Wygenerowano na serwerze',
    'cache_hint' => 'Odśwież stronę i sprawdź w narzędziach deweloperskich nagłówek X-Page-Cache: HIT lub MISS. Data generowania nie zmienia się podczas HIT. Ctrl+Shift+R może ominąć cache.',
    'contact_link' => 'Wypróbuj formularz',
];
