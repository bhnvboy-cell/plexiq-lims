<?php

namespace App\Jobs;

abstract class Job
{
    public string $queue = 'default';

    public int $maxAttempts = 3;

    abstract public function handle(array $payload): void;
}
