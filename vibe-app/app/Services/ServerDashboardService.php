<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Hosting (Railway) and domain figures for the Server Dashboard, so the provider sites need not be opened. */
class ServerDashboardService
{
    /** Railway's published usage rates (USD): per vCPU-minute, GB-minute of memory, GB of network, GB-minute of disk. */
    private const RATES = ['CPU_USAGE' => 0.000463, 'MEMORY_USAGE_GB' => 0.000231, 'NETWORK_TX_GB' => 0.05, 'DISK_USAGE_GB' => 0.000006];

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        // Dates are cached as Unix timestamps: the cache store will not unserialize Carbon objects.
        $raw = Cache::remember('server-dashboard', 300, fn () => [
            'hosting' => $this->hosting(),
            'domain' => $this->domain(),
            'checked_at' => now()->timestamp,
        ]);

        $at = fn (?int $time) => $time ? Carbon::createFromTimestamp($time) : null;
        if ($raw['hosting']['connected']) {
            $raw['hosting']['last_deploy'] = $at($raw['hosting']['last_deploy']);
            $raw['hosting']['deployments'] = array_map(fn ($d) => ['status' => $d['status'], 'at' => $at($d['at'])], $raw['hosting']['deployments']);
        }
        $raw['domain']['ssl_expires'] = $at($raw['domain']['ssl_expires']);
        $raw['domain']['expires_at'] = $at($raw['domain']['expires_at']);
        $raw['checked_at'] = $at($raw['checked_at']);

        return $raw;
    }

    /** @return array<string, mixed> */
    private function hosting(): array
    {
        $token = config('services.railway.token');
        $project = config('services.railway.project_id');
        if (! $token || ! $project) {
            return ['connected' => false, 'note' => 'Set RAILWAY_API_TOKEN and RAILWAY_PROJECT_ID to show hosting figures here.'];
        }

        try {
            $query = 'query($id:String!){project(id:$id){name services{edges{node{name}}} environments{edges{node{name}}}}'
                .' deployments(first:5,input:{projectId:$id}){edges{node{status createdAt}}}'
                .' estimatedUsage(projectId:$id,measurements:[CPU_USAGE,MEMORY_USAGE_GB,NETWORK_TX_GB,DISK_USAGE_GB]){measurement estimatedValue}}';
            $response = Http::withToken($token)->timeout(8)->post('https://backboard.railway.com/graphql/v2', ['query' => $query, 'variables' => ['id' => $project]]);
            $data = $response->json('data');
            if (! $response->ok() || ! $data) {
                return ['connected' => false, 'note' => 'Railway did not accept the request: '.($response->json('errors.0.message') ?? 'HTTP '.$response->status())];
            }
        } catch (Throwable $e) {
            return ['connected' => false, 'note' => 'Railway could not be reached: '.$e->getMessage()];
        }

        $usage = collect($data['estimatedUsage'] ?? [])->mapWithKeys(fn ($row) => [$row['measurement'] => (float) $row['estimatedValue']]);
        $cost = $usage->sum(fn ($value, $key) => $value * (self::RATES[$key] ?? 0));
        $deployments = collect($data['deployments']['edges'] ?? [])->pluck('node');

        return [
            'connected' => true,
            'project' => $data['project']['name'] ?? '—',
            'services' => count($data['project']['services']['edges'] ?? []),
            'environments' => collect($data['project']['environments']['edges'] ?? [])->pluck('node.name')->all(),
            'usage' => $usage->all(),
            'estimated_cost' => round($cost, 2),
            'last_status' => $deployments->first()['status'] ?? '—',
            'last_deploy' => ($deployments->first()['createdAt'] ?? null) ? Carbon::parse($deployments->first()['createdAt'])->timestamp : null,
            'deployments' => $deployments->map(fn ($d) => ['status' => $d['status'], 'at' => Carbon::parse($d['createdAt'])->timestamp])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function domain(): array
    {
        $url = config('services.server_dashboard.url') ?: config('app.url');
        $host = parse_url($url, PHP_URL_HOST) ?: $url;
        $expires = config('services.server_dashboard.domain_expires_at');

        $started = microtime(true);
        try {
            $response = Http::timeout(8)->get($url);
            $up = $response->successful() || $response->redirect();
            $ms = (int) round((microtime(true) - $started) * 1000);
        } catch (Throwable) {
            $up = false;
            $ms = null;
        }

        return [
            'name' => config('services.server_dashboard.domain_name') ?: $host,
            'up' => $up,
            'response_ms' => $ms,
            'ssl_expires' => $this->sslExpiry($host),
            'expires_at' => $expires ? Carbon::parse($expires)->timestamp : null,
            'renewal_price' => config('services.server_dashboard.domain_renewal_price'),
        ];
    }

    private function sslExpiry(string $host): ?int
    {
        try {
            $context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false]]);
            $socket = @stream_socket_client("ssl://{$host}:443", $errno, $error, 5, STREAM_CLIENT_CONNECT, $context);
            if (! $socket) {
                return null;
            }
            $cert = openssl_x509_parse(stream_context_get_params($socket)['options']['ssl']['peer_certificate']);

            return isset($cert['validTo_time_t']) ? (int) $cert['validTo_time_t'] : null;
        } catch (Throwable) {
            return null;
        }
    }
}
