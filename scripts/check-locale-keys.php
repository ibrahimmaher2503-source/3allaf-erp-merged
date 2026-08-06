<?php

declare(strict_types=1);

$root = realpath(__DIR__.'/..');
$locales = ['ar' => $root.'/lang/ar.json', 'en' => $root.'/lang/en.json'];
$keysByLocale = [];

foreach ($locales as $locale => $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "Missing locale file: {$path}\n");
        exit(1);
    }

    try {
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fwrite(STDERR, "Invalid {$locale}.json: {$exception->getMessage()}\n");
        exit(1);
    }

    $keysByLocale[$locale] = [];
    collectKeys($decoded, '', $keysByLocale[$locale]);
    sort($keysByLocale[$locale]);
}

$arOnly = array_values(array_diff($keysByLocale['ar'], $keysByLocale['en']));
$enOnly = array_values(array_diff($keysByLocale['en'], $keysByLocale['ar']));

if ($arOnly !== [] || $enOnly !== []) {
    if ($arOnly !== []) {
        fwrite(STDERR, "Keys only in ar.json:\n- ".implode("\n- ", $arOnly)."\n");
    }
    if ($enOnly !== []) {
        fwrite(STDERR, "Keys only in en.json:\n- ".implode("\n- ", $enOnly)."\n");
    }
    exit(1);
}

printf("Locale key parity: PASS (%d keys in ar.json and en.json)\n", count($keysByLocale['ar']));

function collectKeys(mixed $value, string $prefix, array &$keys): void
{
    if (! is_array($value)) {
        if ($prefix !== '') {
            $keys[] = $prefix;
        }

        return;
    }

    foreach ($value as $key => $child) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
        collectKeys($child, $path, $keys);
    }
}
