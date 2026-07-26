<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Http\Controllers;

use AndreAgroFerreira\AiSdkToolbox\Management\SkillManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class VerifyController extends Controller
{
    public function __invoke(SkillManager $manager): JsonResponse
    {
        $report = $manager->verify();

        return new JsonResponse([
            'ok' => ! collect($report)->contains(fn (array $mismatches): bool => $mismatches !== []),
            'data' => $report,
        ]);
    }
}
