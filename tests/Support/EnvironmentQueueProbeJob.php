<?php

namespace Tests\Support;

use Illuminate\Contracts\Queue\ShouldQueue;

final class EnvironmentQueueProbeJob implements ShouldQueue
{
    public function __construct(public readonly string $markerPath, public readonly bool $fail = false) {}

    public function handle(): void
    {
        if ($this->fail) {
            throw new \RuntimeException('staging queue failure probe');
        }

        file_put_contents($this->markerPath, 'queue-ok');
    }
}
