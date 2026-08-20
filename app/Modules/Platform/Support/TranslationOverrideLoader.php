<?php

namespace App\Modules\Platform\Support;

use App\Modules\Platform\Models\TranslationOverride;
use Illuminate\Support\Arr;
use Illuminate\Translation\FileLoader;
use Throwable;

final class TranslationOverrideLoader extends FileLoader
{
    public function load($locale, $group, $namespace = null): array
    {
        $lines = parent::load($locale, $group, $namespace);

        if (! in_array($locale, ['ar', 'en'], true) || ($namespace !== null && $namespace !== '*')) {
            return $lines;
        }

        try {
            foreach (TranslationOverride::query()->where('locale', $locale)->where('group', $group)->get() as $override) {
                if ($group === '*') {
                    $lines[$override->translation_key] = $override->value;
                } else {
                    Arr::set($lines, $override->translation_key, $override->value);
                }
            }
        } catch (Throwable $exception) {
            if (app()->runningUnitTests()) {
                throw $exception;
            }

            // Translation loading must remain available before migration and during DB outages.
        }

        return $lines;
    }
}
