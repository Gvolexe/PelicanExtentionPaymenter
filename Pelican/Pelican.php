<?php

namespace Paymenter\Extensions\Servers\Pelican;

use App\Classes\Extension\Server;
use App\Models\Product;
use App\Models\Service;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Class Pelican
 */
class Pelican extends Server
{
    public function getConfig($values = []): array
    {
        return [
            [
                'name' => 'host',
                'label' => 'Pelican URL',
                'type' => 'text',
                'description' => 'Pelican URL',
                'required' => true,
                'validation' => 'url',
            ],
            [
                'name' => 'api_key',
                'label' => 'Pelican API Key',
                'type' => 'text',
                'description' => 'Pelican Application API key',
                'required' => true,
                'encrypted' => true,
            ],
        ];
    }

    public function testConfig(): bool|string
    {
        try {
            $this->request('/api/application/servers', 'GET');
        } catch (Exception $e) {
            return $e->getMessage();
        }

        return true;
    }

    public function request($url, $method = 'get', $data = []): array
    {
        $method = strtolower($method);
        $requestUrl = rtrim($this->config('host'), '/') . '/' . ltrim($url, '/');
        $response = Http::withToken($this->config('api_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->$method($requestUrl, $data);

        if (!$response->successful()) {
            $body = $response->json();
            $message = is_array($body)
                ? ($body['errors'][0]['detail'] ?? $body['errors'][0]['message'] ?? $body['message'] ?? null)
                : null;
            $message ??= $response->body() ?: 'Unknown Pelican API error';

            throw new Exception("Pelican API {$response->status()} {$method} {$url}: {$message}", $response->status());
        }

        return $response->json() ?? [];
    }

    public function getProductConfig($values = []): array
    {
        $nodes = $this->request('/api/application/nodes', 'get', ['per_page' => 500]);
        $nodeList = [];
        foreach ($nodes['data'] as $node) {
            $nodeList[$node['attributes']['id']] = $node['attributes']['name'];
        }

        $eggList = [];
        $eggs = $this->request('/api/application/eggs');
        foreach ($eggs['data'] as $egg) {
            $eggList[$egg['attributes']['id']] = $egg['attributes']['name'];
        }

        $using_port_array = isset($values['port_array']) && $values['port_array'] !== '';

        return [
            [
                'name' => 'node',
                'label' => 'Node',
                'type' => 'select',
                'description' => 'Leave empty to let Pelican auto deploy using tags and port ranges.',
                'options' => $nodeList,
            ],
            [
                'name' => 'deployment_tags',
                'label' => 'Deployment Tags',
                'type' => 'tags',
                'description' => 'Optional Pelican node tags to restrict automatic deployment.',
                'database_type' => 'array',
                'required' => false,
            ],
            [
                'name' => 'egg_id',
                'label' => 'Egg ID',
                'type' => 'select',
                'options' => $eggList,
                'required' => true,
            ],
            [
                'name' => 'memory',
                'label' => 'Memory',
                'type' => 'number',
                'suffix' => 'MiB',
                'required' => true,
                'validation' => 'numeric',
                'min_value' => 0,
                'description' => 'Set to 0 for unlimited',
            ],
            [
                'name' => 'swap',
                'label' => 'Swap',
                'type' => 'number',
                'min_value' => -1,
                'suffix' => 'MiB',
                'required' => true,
                'description' => 'Set to -1 for unlimited, or to 0 to disable swap',
            ],
            [
                'name' => 'disk',
                'label' => 'Disk',
                'type' => 'number',
                'suffix' => 'MiB',
                'required' => true,
                'min_value' => 0,
                'description' => 'Set to 0 for unlimited',
            ],
            [
                'name' => 'io',
                'label' => 'IO Weight',
                'type' => 'number',
                'required' => true,
                'default' => 500,
                'min_value' => 10,
                'max_value' => 1000,
                'description' => 'The IO Weight is the priority given to this server for disk access.',
                'hint' => new HtmlString('<a href="https://docs.docker.com/engine/reference/run/#block-io-bandwidth-blkio-constraint" target="_blank">Documentation</a>'),
            ],
            [
                'name' => 'cpu',
                'label' => 'CPU Limit',
                'type' => 'number',
                'required' => true,
                'min_value' => 0,
                'suffix' => '%',
                'description' => 'Set to 0 for unlimited',
            ],
            [
                'name' => 'cpu_pinning',
                'label' => 'CPU Pinning',
                'type' => 'text',
                'description' => 'Leave empty for no pinning. Example: 0,2-4,5,6',
                'validation' => 'nullable|regex:/^[0-9]+(?:-[0-9]+)?(?:,[0-9]+(?:-[0-9]+)?)*$/',
            ],
            [
                'name' => 'docker_image',
                'label' => 'Docker Image Override',
                'type' => 'text',
                'description' => 'Optional. Leave empty to use the selected egg default image.',
            ],
            [
                'name' => 'databases',
                'label' => 'Databases',
                'type' => 'number',
                'required' => true,
                'min_value' => 0,
            ],
            [
                'name' => 'backups',
                'label' => 'Backups',
                'type' => 'number',
                'required' => true,
                'min_value' => 0,
            ],
            [
                'name' => 'additional_allocations',
                'label' => 'Additional Allocations',
                'type' => 'number',
                'required' => true,
                'min_value' => 0,
            ],
            [
                'name' => 'port_array',
                'label' => 'Port Array',
                'type' => 'text',
                'description' => 'Used to assign ports to egg variables.',
                'hint' => new HtmlString('<a href="https://github.com/Gvolexe/PelicanExtentionPaymenter#port-array" target="_blank">Documentation</a>'),
                'live' => true,
                'validation' => 'json',
            ],
            [
                'name' => 'port_range',
                'label' => 'Port ranges',
                'type' => 'tags',
                'description' => '',
                'database_type' => 'array',
                'required' => false,
                'disabled' => $using_port_array,
            ],
            [
                'name' => 'skip_scripts',
                'label' => 'Skip Egg Install Script',
                'description' => 'If the selected Egg has an install script attached to it, the script will run during the install. If you would like to skip this step, check this box.',
                'type' => 'checkbox',
            ],
            [
                'name' => 'dedicated_ip',
                'label' => 'Dedicated IP',
                'description' => 'Assigns the server an allocation whose IP is not being used by any other server.',
                'type' => 'checkbox',
                'disabled' => $using_port_array,
            ],
            [
                'name' => 'start_on_completion',
                'label' => 'Start on completion',
                'description' => 'Start server automatically after installation.',
                'type' => 'checkbox',
            ],
            [
                'name' => 'oom_killer',
                'label' => 'Enable OOM Killer',
                'description' => 'Terminates the server if it breaches the memory limits. Enabling OOM killer may cause server processes to exit unexpectedly.',
                'type' => 'checkbox',
            ],
        ];
    }

    public function getCheckoutConfig(Product $product, $values = [], $settings = []): array
    {
        if (empty($settings['egg_id'])) {
            return [];
        }

        try {
            $eggData = $this->getEggWithVariables((int) $settings['egg_id']);
        } catch (Exception) {
            return [];
        }

        $fields = [];
        foreach ($this->eggVariables($eggData) as $variable) {
            $attributes = $variable['attributes'];
            if (!($attributes['user_editable'] ?? false)) {
                continue;
            }

            $rules = (array) ($attributes['rules'] ?? []);
            $field = [
                'name' => $attributes['env_variable'],
                'label' => $attributes['name'],
                'type' => $this->fieldTypeFromRules($rules),
                'default' => $attributes['default_value'] ?? '',
                'description' => $attributes['description'] ?? '',
                'required' => in_array('required', $rules, true),
            ];

            $validation = implode('|', array_filter($rules, fn ($rule) => $rule !== 'required'));
            if ($validation !== '') {
                $field['validation'] = $validation;
            }

            $fields[] = $field;
        }

        return $fields;
    }

    public function createServer(Service $service, $settings, $properties)
    {
        if ($this->getServer($service->id, failIfNotFound: false)) {
            throw new Exception('Server already exists');
        }

        $settings = array_merge($settings, $properties);

        $eggData = $this->getEggWithVariables((int) $settings['egg_id']);
        if (!isset($eggData['attributes'])) {
            throw new Exception('Could not fetch egg data');
        }
        $environment = [];
        foreach ($this->eggVariables($eggData) as $variable) {
            $environment[$variable['attributes']['env_variable']] = $settings[$variable['attributes']['env_variable']] ?? $variable['attributes']['default_value'];
        }

        $orderUser = $service->user ?? $service->order?->user;
        if (!$orderUser) {
            throw new Exception('Could not determine the Paymenter user for this service');
        }

        // Get the user id if one already exists...
        $user = $this->request('/api/application/users', 'get', ['filter' => ['email' => $orderUser->email]])['data'][0]['attributes']['id'] ?? null;

        // Otherwise create a new user
        if (!$user) {
            $user = $this->request('/api/application/users', 'post', [
                'email' => $orderUser->email,
                'username' => $this->makeUsername($orderUser->name ?? $orderUser->email),
                'is_managed_externally' => true,
            ])['attributes']['id'];
        }

        $deploymentData = $this->generateDeploymentData($settings, $environment);

        $serverCreationData = [
            'external_id' => (string) $service->id,
            'name' => isset($settings['servername']) ? $settings['servername'] : $service->product->name . ' #' . $service->id,
            'user' => (int) $user,
            'egg' => $settings['egg_id'],
            'docker_image' => ($settings['docker_image'] ?? null) ?: $eggData['attributes']['docker_image'],
            'startup' => $eggData['attributes']['startup'],
            'environment' => $deploymentData['environment'],
            'skip_scripts' => (bool) ($settings['skip_scripts'] ?? false),
            'oom_killer' => (bool) ($settings['oom_killer'] ?? false),
            'limits' => [
                'memory' => (int) $settings['memory'],
                'swap' => (int) $settings['swap'],
                'disk' => (int) $settings['disk'],
                'io' => (int) $settings['io'],
                'threads' => $settings['cpu_pinning'] ?? null,
                'cpu' => (int) $settings['cpu'],
            ],
            'feature_limits' => [
                'databases' => (int) $settings['databases'],
                'allocations' => $deploymentData['allocations_needed'] + (int) $settings['additional_allocations'],
                'backups' => (int) $settings['backups'],
            ],
            'start_on_completion' => (bool) ($settings['start_on_completion'] ?? false),
        ];
        if ($deploymentData['auto_deploy']) {
            $serverCreationData['deploy'] = [
                'tags' => $this->normalizeArray($settings['deployment_tags'] ?? []),
                'dedicated_ip' => (bool) ($settings['dedicated_ip'] ?? false),
                'port_range' => $settings['port_range'] ?? [],
            ];
        } else {
            $serverCreationData['allocation'] = $deploymentData['allocation'];
        }

        $server = $this->request('/api/application/servers', 'post', $serverCreationData);

        return [
            'server' => $server['attributes']['id'],
            'link' => $this->config('host') . '/server/' . $server['attributes']['identifier'],
        ];
    }

    private function generateDeploymentData($settings, $environment)
    {
        if (!isset($settings['port_array']) || $settings['port_array'] === '') {
            if (!empty($settings['node']) || empty($this->normalizeArray($settings['port_range'] ?? []))) {
                $allocation = $this->getFirstAvailableAllocation($settings);
                $environment['SERVER_PORT'] = $allocation['port'];

                return [
                    'auto_deploy' => false,
                    'environment' => $environment,
                    'allocations_needed' => 1,
                    'allocation' => [
                        'default' => $allocation['id'],
                        'additional' => [],
                    ],
                ];
            }

            return [
                'auto_deploy' => true,
                'environment' => $environment,
                'allocations_needed' => 1,
            ];
        }

        try {
            // Example: {"SERVER_PORT": 7777, "NONE": [7778, 7779], "QUERY_PORT": 2701, "RCON_PORT": 27020}
            $port_array = json_decode($settings['port_array'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON decode error: ' . json_last_error_msg());
            }
        } catch (Exception) {
            throw new Exception('Invalid JSON in port array');
        }

        if (!is_array($port_array)) {
            throw new Exception('Port array must be an object');
        }

        if (!array_key_exists('SERVER_PORT', $port_array)) {
            throw new Exception('Port array must include a SERVER_PORT entry');
        }

        $port_array = $this->normalizePortArray($port_array);
        $free_allocations_needed = $this->countRequiredAllocations($port_array);
        $nodes = $this->request('/api/application/nodes/deployable', 'get', [
            'memory' => $settings['memory'],
            'disk' => $settings['disk'],
            'cpu' => $settings['cpu'] ?? 0,
            'tags' => $this->normalizeArray($settings['deployment_tags'] ?? []),
            'include' => ['allocations'],
        ]);
        $nodes = collect($nodes['data']);
        $nodes_by_id = $nodes->mapWithKeys(fn ($node) => [$node['attributes']['id'] => $node['attributes']]);

        if (!empty($settings['node'])) {
            // If the product's node id is not in the deployable nodes array, throw error.
            if (!$nodes_by_id->has($settings['node'])) {
                throw new Exception('Node is not suitable for deployment.');
            }

            $node = $nodes_by_id->get($settings['node']);
            $availablePorts = $this->availableAllocationsForNode($node);

            if (count($availablePorts) < $free_allocations_needed) {
                throw new Exception("Not enough allocations found for deployment. Found: {$availablePorts->count()}, Required: {$free_allocations_needed}");
            }
        } else {
            if ($nodes->isEmpty()) {
                throw new Exception('No deployable nodes found for the configured limits and tags');
            }

            foreach ($nodes as $index => $node) {
                $availablePorts = $this->availableAllocationsForNode($node['attributes']);

                if (count($availablePorts) < $free_allocations_needed) {
                    // If this was last viable node, throw error
                    if ($index == $nodes->count() - 1) {
                        throw new Exception('No nodes with suitable allocations found for deployment');
                    }

                    // Else move onto next viable node
                    continue;
                }
                break;
            }
        }

        $allocations = [];
        foreach ($port_array as $key => $value) {
            if (is_array($value)) {
                if ($key !== 'NONE') {
                    throw new Exception('Only the NONE port array entry can contain multiple ports');
                }

                foreach ($value as $port) {
                    $allocation = $this->findClosestAllocation($availablePorts, $port);
                    $allocations[$key][] = $allocation;

                    // Remove the port from the available ports
                    $availablePorts = $availablePorts->reject(function ($port) use ($allocation) {
                        return $port['id'] == $allocation['id'];
                    });
                }
            } else {
                $allocation = $this->findClosestAllocation($availablePorts, $value);
                $allocations[$key] = $allocation;

                // Remove the port from the available ports
                $availablePorts = $availablePorts->reject(function ($port) use ($allocation) {
                    return $port['id'] == $allocation['id'];
                });
            }
        }

        $allocationIds = [];

        foreach ($allocations as $key => $value) {
            // Assign the allocations to the environment
            if ($key !== 'NONE') {
                if (isset($environment[$key])) {
                    $environment[$key] = $value['port'];
                }
            }

            // Set allocations to a array with only the ids
            if ($key !== 'SERVER_PORT') {
                if (is_array($value) && isset($value[0])) {
                    foreach ($value as $v) {
                        $allocationIds[] = $v['id'];
                    }
                } else {
                    $allocationIds[] = $value['id'];
                }
            }
        }

        return [
            'auto_deploy' => false,
            'allocations_needed' => $free_allocations_needed,
            'environment' => $environment,
            'allocation' => [
                'default' => $allocations['SERVER_PORT']['id'],
                'additional' => $allocationIds,
            ],
        ];
    }

    private function getServer($id, $failIfNotFound = true, $raw = false)
    {
        try {
            $response = $this->request('/api/application/servers/external/' . $id);
        } catch (Exception $e) {
            if (!$failIfNotFound && (int) $e->getCode() === 404) {
                return false;
            }

            if ($failIfNotFound && (int) $e->getCode() === 404) {
                throw new Exception('Server not found', 404, $e);
            }

            throw $e;
        }
        if ($raw) {
            return $response;
        }

        return $response['attributes']['id'] ?? false;
    }

    public function suspendServer(Service $service, $settings, $properties)
    {
        $server = $this->getServer($service->id);

        $this->request('/api/application/servers/' . $server . '/suspend', 'post');

        return true;
    }

    public function unsuspendServer(Service $service, $settings, $properties)
    {
        $server = $this->getServer($service->id);

        $this->request('/api/application/servers/' . $server . '/unsuspend', 'post');

        return true;
    }

    public function terminateServer(Service $service, $settings, $properties)
    {
        $server = $this->getServer($service->id);

        $this->request('/api/application/servers/' . $server, 'delete');

        return true;
    }

    public function upgradeServer(Service $service, $settings, $properties)
    {
        $server = $this->getServer($service->id, raw: true);

        $settings = array_merge($settings, $properties);
        $allocationLimit = $this->countRequiredAllocationsFromSettings($settings) + (int) $settings['additional_allocations'];

        $updateServerData = [
            'allocation' => $server['attributes']['allocation'],
            'oom_killer' => (bool) ($settings['oom_killer'] ?? false),
            'limits' => [
                'memory' => (int) $settings['memory'],
                'swap' => (int) $settings['swap'],
                'disk' => (int) $settings['disk'],
                'io' => (int) $settings['io'],
                'cpu' => (int) $settings['cpu'],
                'threads' => $settings['cpu_pinning'] ?? null,
            ],
            'feature_limits' => [
                'databases' => (int) $settings['databases'],
                'allocations' => max($allocationLimit, (int) ($server['attributes']['feature_limits']['allocations'] ?? 0)),
                'backups' => (int) $settings['backups'],
            ],
        ];

        $this->request('/api/application/servers/' . $server['attributes']['id'] . '/build', 'patch', $updateServerData);

        $eggData = $this->getEggWithVariables((int) $settings['egg_id']);

        if (!isset($eggData['attributes'])) {
            throw new Exception('Could not fetch egg data');
        }

        $environment = [];

        foreach ($this->eggVariables($eggData) as $variable) {
            // Check if variable has been set on server
            if (isset($server['attributes']['container']['environment'][$variable['attributes']['env_variable']])) {
                $environment[$variable['attributes']['env_variable']] = $server['attributes']['container']['environment'][$variable['attributes']['env_variable']];
            } else {
                $environment[$variable['attributes']['env_variable']] = $settings[$variable['attributes']['env_variable']] ?? $variable['attributes']['default_value'];
            }
        }

        $updateServerData = [
            'environment' => $environment,
            'skip_scripts' => (bool) ($settings['skip_scripts'] ?? false),
            'egg' => $settings['egg_id'],
            'image' => ($settings['docker_image'] ?? null) ?: ($server['attributes']['container']['image'] ?? $eggData['attributes']['docker_image']),
            'startup' => $server['attributes']['container']['startup_command'] ?? $settings['startup'] ?? $eggData['attributes']['startup'],
        ];

        $this->request('/api/application/servers/' . $server['attributes']['id'] . '/startup', 'patch', $updateServerData);

        return true;
    }

    public function getActions(Service $service)
    {
        $server = $this->getServer($service->id, raw: true);

        return [
            [
                'type' => 'button',
                'label' => 'Go to server',
                'url' => $this->config('host') . '/server/' . $server['attributes']['identifier'],
            ],
        ];
    }

    public function migrateOption(string $key, ?string $value)
    {
        return match ($key) {
            'egg' => ['key' => 'egg_id', 'value' => $value],
            'allocation' => ['key' => 'additional_allocations', 'value' => $value],
            'location', 'node_id' => ['key' => 'node', 'value' => $value],
            default => ['key' => $key, 'value' => $value]
        };
    }

    private function getEggWithVariables(int $eggId): array
    {
        return $this->request('/api/application/eggs/' . $eggId, data: ['include' => 'variables']);
    }

    private function eggVariables(array $eggData): array
    {
        return $eggData['attributes']['relationships']['variables']['data'] ?? [];
    }

    private function makeUsername(?string $name): string
    {
        $base = preg_replace('/[^a-z0-9]/', '', strtolower(Str::transliterate($name ?: 'user'))) ?: 'user';

        return substr($base, 0, 24) . '_' . strtolower(Str::random(6));
    }

    private function fieldTypeFromRules(array $rules): string
    {
        if (array_intersect($rules, ['integer', 'numeric'])) {
            return 'number';
        }

        if (array_intersect($rules, ['boolean', 'bool'])) {
            return 'checkbox';
        }

        return 'text';
    }

    private function normalizeArray($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($item) => $item !== null && $item !== ''));
        }

        if ($value === null || $value === '') {
            return [];
        }

        return [$value];
    }

    private function normalizePortArray(array $portArray): array
    {
        foreach ($portArray as $key => $value) {
            if (is_array($value)) {
                $portArray[$key] = array_map(fn ($port) => $this->normalizePort($port), $value);
            } else {
                $portArray[$key] = $this->normalizePort($value);
            }
        }

        return $portArray;
    }

    private function normalizePort($port): int
    {
        if (!is_numeric($port) || (int) $port < 1 || (int) $port > 65535) {
            throw new Exception('Port array entries must be valid TCP/UDP ports between 1 and 65535');
        }

        return (int) $port;
    }

    private function countRequiredAllocationsFromSettings(array $settings): int
    {
        if (empty($settings['port_array'])) {
            return 1;
        }

        $portArray = json_decode($settings['port_array'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($portArray) || !array_key_exists('SERVER_PORT', $portArray)) {
            throw new Exception('Invalid port array');
        }

        return $this->countRequiredAllocations($this->normalizePortArray($portArray));
    }

    private function countRequiredAllocations(array $portArray): int
    {
        return collect($portArray)->sum(fn ($value) => is_array($value) ? count($value) : 1);
    }

    private function getFirstAvailableAllocation(array $settings): array
    {
        $nodes = $this->request('/api/application/nodes/deployable', 'get', [
            'memory' => $settings['memory'],
            'disk' => $settings['disk'],
            'cpu' => $settings['cpu'] ?? 0,
            'tags' => $this->normalizeArray($settings['deployment_tags'] ?? []),
            'include' => ['allocations'],
        ]);
        $nodes = collect($nodes['data'])->pluck('attributes');

        if (!empty($settings['node'])) {
            $nodes = $nodes->where('id', $settings['node']);
        }

        if ($nodes->isEmpty()) {
            throw new Exception('Node is not suitable for deployment.');
        }

        $portRanges = $this->normalizeArray($settings['port_range'] ?? []);
        foreach ($nodes as $node) {
            $availablePorts = $this->availableAllocationsForNode($node, (bool) ($settings['dedicated_ip'] ?? false))
                ->filter(fn ($allocation) => $this->portMatchesRanges($allocation['port'], $portRanges))
                ->values();

            if ($availablePorts->isNotEmpty()) {
                return $availablePorts->first();
            }
        }

        throw new Exception('No available allocations found for deployment.');
    }

    private function availableAllocationsForNode(array $node, bool $dedicated = false)
    {
        $allocations = collect($node['relationships']['allocations']['data'] ?? []);
        $assignedIps = $allocations
            ->filter(fn ($allocation) => $allocation['attributes']['assigned'] ?? false)
            ->pluck('attributes.ip')
            ->unique();

        return $allocations
            ->filter(fn ($allocation) => !($allocation['attributes']['assigned'] ?? true))
            ->filter(fn ($allocation) => !$dedicated || !$assignedIps->contains($allocation['attributes']['ip'] ?? null))
            ->map(fn ($allocation) => [
                'port' => (int) $allocation['attributes']['port'],
                'id' => (int) $allocation['attributes']['id'],
                'ip' => $allocation['attributes']['ip'] ?? null,
            ])
            ->sortBy('port')
            ->values();
    }

    private function portMatchesRanges(int $port, array $ranges): bool
    {
        if (empty($ranges)) {
            return true;
        }

        foreach ($ranges as $range) {
            if (is_numeric($range) && $port === (int) $range) {
                return true;
            }

            if (is_string($range) && preg_match('/^(\d+)-(\d+)$/', $range, $matches)) {
                if ($port >= (int) $matches[1] && $port <= (int) $matches[2]) {
                    return true;
                }
            }
        }

        return false;
    }

    private function findClosestAllocation($availablePorts, int $requestedPort): array
    {
        $allocation = $availablePorts->where('port', $requestedPort)->first()
            ?? $availablePorts->first(fn ($allocation) => $allocation['port'] > $requestedPort)
            ?? $availablePorts->first();

        if (!$allocation) {
            throw new Exception('Could not find a port to assign');
        }

        return $allocation;
    }
}
