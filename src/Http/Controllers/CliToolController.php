<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Http\Controllers;

use AndreAgroFerreira\AiSdkToolbox\CliTools\CliTool;
use AndreAgroFerreira\AiSdkToolbox\Management\CliToolManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class CliToolController extends Controller
{
    public function index(CliToolManager $manager): JsonResponse
    {
        return new JsonResponse([
            'data' => $manager->all()->map(fn (CliTool $tool): array => [
                'name' => $tool->name,
                'runtime' => $tool->runtime,
                'source' => $tool->source,
                'trust' => $tool->trust->value,
                'env' => array_map(
                    fn (string $variable): array => ['name' => $variable, 'set' => getenv($variable) !== false],
                    $tool->env,
                ),
            ])->values()->all(),
        ]);
    }

    public function trust(CliToolManager $manager, string $name): JsonResponse
    {
        $manager->trust($name, true);

        return new JsonResponse(['name' => $name, 'trust' => 'trusted']);
    }

    public function untrust(CliToolManager $manager, string $name): JsonResponse
    {
        $manager->trust($name, false);

        return new JsonResponse(['name' => $name, 'trust' => 'untrusted']);
    }
}
