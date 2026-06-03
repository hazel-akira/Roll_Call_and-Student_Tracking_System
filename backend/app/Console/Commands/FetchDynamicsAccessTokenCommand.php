<?php

namespace App\Console\Commands;

use App\Services\DynamicsService;
use Illuminate\Console\Command;

class FetchDynamicsAccessTokenCommand extends Command
{
    protected $signature = 'dynamics:fetch-token
                            {--fresh : Bypass the cache and request a new token}
                            {--json : Output the token as JSON}';

    protected $description = 'Fetch the Microsoft Dynamics OAuth access token';

    public function handle(DynamicsService $dynamics): int
    {
        if (! $dynamics->isEnabled()) {
            $this->components->error('Dynamics integration is not enabled or credentials are missing.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $result = $dynamics->requestAccessToken(fresh: true);
        } else {
            $token = $dynamics->getAccessToken();
            $result = $token
                ? ['success' => true, 'access_token' => $token]
                : $dynamics->requestAccessToken();
        }

        if (! $result['success']) {
            $this->components->error('Failed to obtain access token.');
            if (! empty($result['error'])) {
                $this->line($result['error']);
            }

            return self::FAILURE;
        }

        $token = $result['access_token'];

        if ($this->option('json')) {
            $payload = ['access_token' => $token];
            if (isset($result['expires_in'])) {
                $payload['expires_in'] = $result['expires_in'];
            }

            $this->line(json_encode($payload, JSON_PRETTY_PRINT));
        } else {
            $this->components->info('Access token retrieved successfully.');
            if (isset($result['expires_in'])) {
                $this->line("Expires in: {$result['expires_in']} seconds");
            }
            $this->line($token);
        }

        return self::SUCCESS;
    }
}
