<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function slugify(string $value): string
{
    $value = trim($value);

    $value = strtolower($value);

    $value = preg_replace(
        '/[^a-z0-9]+/',
        '-',
        $value
    );

    $value = trim($value, '-');

    return $value !== '' ? $value : 'event';
}
