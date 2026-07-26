<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Http\Controllers;

use AndreAgroFerreira\AiSdkToolbox\Http\Requests\InstallPluginRequest;
use AndreAgroFerreira\AiSdkToolbox\Plugins\Exceptions\InvalidPluginManifestException;
use AndreAgroFerreira\AiSdkToolbox\Plugins\Exceptions\PluginInstallException;
use AndreAgroFerreira\AiSdkToolbox\Plugins\Exceptions\PluginNotFoundException;
use AndreAgroFerreira\AiSdkToolbox\Plugins\Plugin;
use AndreAgroFerreira\AiSdkToolbox\Plugins\PluginManager;
use AndreAgroFerreira\AiSdkToolbox\Plugins\PluginRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class PluginController extends Controller
{
    public function index(PluginRegistry $registry): JsonResponse
    {
        return new JsonResponse([
            'data' => collect($registry->entries())->map(fn (array $entry, string $name): array => [
                'name' => $name,
                'version' => $entry['version'],
                'enabled' => $entry['enabled'],
                'source' => $entry['source'],
                'installed_at' => $entry['installed_at'],
            ])->values()->all(),
        ]);
    }

    public function show(PluginManager $manager, PluginRegistry $registry, string $name): JsonResponse
    {
        $entry = $registry->get($name);

        if ($entry === null) {
            return new JsonResponse(['message' => sprintf('Plugin [%s] not found.', $name)], 404);
        }

        try {
            $plugin = $manager->find($name);
        } catch (PluginNotFoundException) {
            return new JsonResponse(['message' => sprintf('Plugin [%s] not found.', $name)], 404);
        }

        return new JsonResponse([
            'data' => $this->serializePlugin($plugin, $entry['enabled']),
        ]);
    }

    public function install(InstallPluginRequest $request, PluginManager $manager): JsonResponse
    {
        try {
            $plugin = $manager->install(
                source: $request->source(),
                path: $request->subpath(),
                enabled: ! $request->disabled(),
            );
        } catch (PluginInstallException|InvalidPluginManifestException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        }

        return new JsonResponse(['data' => $this->serializePlugin($plugin, ! $request->disabled())], 201);
    }

    public function destroy(PluginManager $manager, string $name): JsonResponse
    {
        try {
            $manager->remove($name);
        } catch (PluginNotFoundException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 404);
        }

        return new JsonResponse(['name' => $name, 'removed' => true]);
    }

    public function enable(PluginManager $manager, string $name): JsonResponse
    {
        try {
            $manager->enable($name);
        } catch (PluginNotFoundException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 404);
        }

        return new JsonResponse(['name' => $name, 'enabled' => true]);
    }

    public function disable(PluginManager $manager, string $name): JsonResponse
    {
        try {
            $manager->disable($name);
        } catch (PluginNotFoundException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 404);
        }

        return new JsonResponse(['name' => $name, 'enabled' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePlugin(Plugin $plugin, bool $enabled): array
    {
        return [
            'name' => $plugin->name,
            'version' => $plugin->version,
            'description' => $plugin->description,
            'enabled' => $enabled,
            'skills' => $plugin->skillsPath,
            'agents' => array_keys($plugin->agents),
            'listeners' => array_keys($plugin->listeners),
        ];
    }
}
