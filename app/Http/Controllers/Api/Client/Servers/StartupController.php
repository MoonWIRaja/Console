<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerVariable;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Services\Servers\StartupCommandService;
use Pterodactyl\Repositories\Eloquent\ServerVariableRepository;
use Pterodactyl\Transformers\Api\Client\EggVariableTransformer;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Pterodactyl\Http\Requests\Api\Client\Servers\Startup\GetStartupRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Startup\ResetStartupCommandRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Startup\UpdateStartupCommandRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Startup\UpdateStartupEggRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Startup\UpdateStartupVariableRequest;

class StartupController extends ClientApiController
{
    /**
     * StartupController constructor.
     */
    public function __construct(
        private StartupCommandService $startupCommandService,
        private ServerVariableRepository $repository,
    ) {
        parent::__construct();
    }

    /**
     * Returns the startup information for the server including all the variables.
     */
    public function index(GetStartupRequest $request, Server $server): array
    {
        $startup = $this->startupCommandService->handle($server);

        return $this->fractal->collection(
            $server->variables()->where('user_viewable', true)->get()
        )
            ->transformWith($this->getTransformer(EggVariableTransformer::class))
            ->addMeta([
                ...$this->buildStartupMeta($server, $startup),
            ])
            ->toArray();
    }

    /**
     * Updates a single variable for a server.
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(UpdateStartupVariableRequest $request, Server $server): array
    {
        $variable = $server->variables()->where('env_variable', $request->input('key'))->first();

        if (is_null($variable) || !$variable->user_viewable) {
            throw new BadRequestHttpException('The environment variable you are trying to edit does not exist.');
        } elseif (!$variable->user_editable) {
            throw new BadRequestHttpException('The environment variable you are trying to edit is read-only.');
        }

        $original = $variable->server_value;

        // Revalidate the variable value using the egg variable specific validation rules for it.
        $this->validate($request, ['value' => $variable->rules]);

        $this->repository->updateOrCreate([
            'server_id' => $server->id,
            'variable_id' => $variable->id,
        ], [
            'variable_value' => $request->input('value') ?? '',
        ]);

        $variable = $variable->refresh();
        $variable->server_value = $request->input('value');

        $startup = $this->startupCommandService->handle($server);

        if ($variable->env_variable !== $request->input('value')) {
            Activity::event('server:startup.edit')
                ->subject($variable)
                ->property([
                    'variable' => $variable->env_variable,
                    'old' => $original,
                    'new' => $request->input('value'),
                ])
                ->log();
        }

        return $this->fractal->item($variable)
            ->transformWith($this->getTransformer(EggVariableTransformer::class))
            ->addMeta([
                ...$this->buildStartupMeta($server, $startup),
            ])
            ->toArray();
    }

    public function updateCommand(UpdateStartupCommandRequest $request, Server $server): array
    {
        $original = $server->startup;
        $updated = trim((string) $request->input('startup'));

        $server->forceFill(['startup' => $updated])->save();
        $server->refresh();

        $startup = $this->startupCommandService->handle($server);

        if ($original !== $updated) {
            Activity::event('server:startup.command')
                ->subject($server)
                ->property('old', $original)
                ->property('new', $updated)
                ->log();
        }

        return $this->fractal->collection(
            $server->variables()->where('user_viewable', true)->get()
        )
            ->transformWith($this->getTransformer(EggVariableTransformer::class))
            ->addMeta([
                ...$this->buildStartupMeta($server, $startup),
            ])
            ->toArray();
    }

    public function resetCommand(ResetStartupCommandRequest $request, Server $server): array
    {
        $default = (string) ($server->egg->startup ?? '');
        $original = $server->startup;

        $server->forceFill(['startup' => $default])->save();
        $server->refresh();

        $startup = $this->startupCommandService->handle($server);

        if ($original !== $default) {
            Activity::event('server:startup.command.reset')
                ->subject($server)
                ->property('old', $original)
                ->property('new', $default)
                ->log();
        }

        return $this->fractal->collection(
            $server->variables()->where('user_viewable', true)->get()
        )
            ->transformWith($this->getTransformer(EggVariableTransformer::class))
            ->addMeta([
                ...$this->buildStartupMeta($server, $startup),
            ])
            ->toArray();
    }

    public function changeEgg(UpdateStartupEggRequest $request, Server $server): array
    {
        /** @var Egg $egg */
        $egg = Egg::query()
            ->where('nest_id', $server->nest_id)
            ->findOrFail((int) $request->input('egg_id'));

        $dockerImages = $this->normalizeDockerImageMap($egg->docker_images ?? []);
        $dockerImageValues = array_values($dockerImages);
        $requestedImage = (string) $request->input('docker_image', '');
        $nextImage = in_array($requestedImage, $dockerImageValues, true)
            ? $requestedImage
            : ($dockerImageValues[0] ?? $server->image);

        $previousEgg = $server->egg_id;

        $isEggChanging = $server->egg_id !== $egg->id;
        $server->forceFill([
            'egg_id' => $egg->id,
            'nest_id' => $egg->nest_id,
            'startup' => $isEggChanging ? ($egg->startup ?? $server->startup) : $server->startup,
            'image' => $nextImage,
        ])->save();

        // Drop all current server variable values only when egg is changed.
        if ($isEggChanging) {
            ServerVariable::query()->where('server_id', $server->id)->delete();
        }
        $server->refresh();

        $startup = $this->startupCommandService->handle($server);

        Activity::event('server:startup.egg.change')
            ->subject($server)
            ->property('old_egg_id', $previousEgg)
            ->property('new_egg_id', $egg->id)
            ->property('egg_changed', $isEggChanging)
            ->log();

        return $this->fractal->collection(
            $server->variables()->where('user_viewable', true)->get()
        )
            ->transformWith($this->getTransformer(EggVariableTransformer::class))
            ->addMeta([
                ...$this->buildStartupMeta($server, $startup),
            ])
            ->toArray();
    }

    private function buildStartupMeta(Server $server, string $startup): array
    {
        $server->loadMissing(['nest', 'egg']);
        $serverDockerImages = $this->normalizeDockerImageMap($server->egg->docker_images ?? []);

        return [
            'startup_command' => $startup,
            'docker_images' => $serverDockerImages,
            'current_docker_image' => $server->image,
            'raw_startup_command' => $server->startup,
            'default_startup_command' => $server->egg->startup,
            'nest' => [
                'id' => $server->nest_id,
                'name' => $server->nest->name ?? 'Unknown Nest',
            ],
            'current_egg' => [
                'id' => $server->egg_id,
                'name' => $server->egg->name ?? '',
            ],
            'eggs' => Egg::query()
                ->where('nest_id', $server->nest_id)
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'docker_images'])
                ->map(fn (Egg $egg) => [
                    'id' => $egg->id,
                    'name' => $egg->name,
                    'description' => $egg->description,
                    'docker_images' => collect($this->normalizeDockerImageMap($egg->docker_images ?? []))
                        ->map(fn (string $value, string $label) => ['label' => $label, 'value' => $value])
                        ->values()
                        ->toArray(),
                ])
                ->values()
                ->toArray(),
        ];
    }

    private function normalizeDockerImageMap(array $images): array
    {
        $normalized = [];
        foreach ($images as $label => $value) {
            if (is_int($label)) {
                $normalized[(string) $value] = (string) $value;
            } else {
                $normalized[(string) $label] = (string) $value;
            }
        }

        return $normalized;
    }
}
