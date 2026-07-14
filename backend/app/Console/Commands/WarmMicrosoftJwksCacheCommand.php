<?php

namespace App\Console\Commands;

use App\Services\Auth\MicrosoftTokenValidator;
use Illuminate\Console\Command;

class WarmMicrosoftJwksCacheCommand extends Command
{
    protected $signature = 'microsoft:warm-jwks-cache';

    protected $description = 'Prefetch Microsoft signing keys (JWKS) for SSO token validation';

    public function handle(MicrosoftTokenValidator $validator): int
    {
        try {
            $meta = $validator->warmCache();

            $this->info('Microsoft JWKS cache warmed successfully.');
            $this->line('Source URL: '.$meta['url']);
            $this->line('Keys cached: '.$meta['key_count']);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Unable to warm Microsoft JWKS cache: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
