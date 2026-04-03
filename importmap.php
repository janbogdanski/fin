<?php

/**
 * Importmap configuration — maps bare module specifiers to local vendor files.
 *
 * @see https://symfony.com/doc/current/frontend/asset_mapper.html
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/turbo' => [
        'path' => './assets/vendor/@hotwired/turbo/turbo.index.js',
    ],
    '@hotwired/stimulus' => [
        'path' => './assets/vendor/@hotwired/stimulus/stimulus.index.js',
    ],
];
