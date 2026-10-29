<?php

namespace App\Jobs;

use App\Services\Idiario\IdiarioSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RequestIdiarioSimpleSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public string $requestedAt)
    {
    }

    public function handle(): void
    {
        IdiarioSync::send($this->requestedAt);
    }
}
