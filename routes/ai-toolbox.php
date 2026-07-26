<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Http\Controllers\CliToolController;
use AndreAgroFerreira\AiSdkToolbox\Http\Controllers\PluginController;
use AndreAgroFerreira\AiSdkToolbox\Http\Controllers\SkillController;
use AndreAgroFerreira\AiSdkToolbox\Http\Controllers\VerifyController;
use AndreAgroFerreira\AiSdkToolbox\Http\Middleware\AuthorizeAiToolbox;
use Illuminate\Support\Facades\Route;

Route::middleware([AuthorizeAiToolbox::class])->group(function (): void {
    Route::get('/skills', [SkillController::class, 'index'])->name('ai-toolbox.skills.index');
    Route::post('/skills/install', [SkillController::class, 'install'])
        ->middleware(sprintf('throttle:%s', config('ai-sdk-toolbox.http.throttle', '10,1')))
        ->name('ai-toolbox.skills.install');
    Route::get('/skills/{name}', [SkillController::class, 'show'])->name('ai-toolbox.skills.show');
    Route::delete('/skills/{name}', [SkillController::class, 'destroy'])->name('ai-toolbox.skills.destroy');
    Route::get('/skills/{name}/audit', [SkillController::class, 'audit'])->name('ai-toolbox.skills.audit');
    Route::post('/skills/{name}/trust', [SkillController::class, 'trust'])->name('ai-toolbox.skills.trust');
    Route::delete('/skills/{name}/trust', [SkillController::class, 'untrust'])->name('ai-toolbox.skills.untrust');

    Route::get('/cli-tools', [CliToolController::class, 'index'])->name('ai-toolbox.cli-tools.index');
    Route::post('/cli-tools/{name}/trust', [CliToolController::class, 'trust'])->name('ai-toolbox.cli-tools.trust');
    Route::delete('/cli-tools/{name}/trust', [CliToolController::class, 'untrust'])->name('ai-toolbox.cli-tools.untrust');

    Route::get('/plugins', [PluginController::class, 'index'])->name('ai-toolbox.plugins.index');
    Route::post('/plugins/install', [PluginController::class, 'install'])
        ->middleware(sprintf('throttle:%s', config('ai-sdk-toolbox.http.throttle', '10,1')))
        ->name('ai-toolbox.plugins.install');
    Route::get('/plugins/{name}', [PluginController::class, 'show'])->name('ai-toolbox.plugins.show');
    Route::delete('/plugins/{name}', [PluginController::class, 'destroy'])->name('ai-toolbox.plugins.destroy');
    Route::post('/plugins/{name}/enable', [PluginController::class, 'enable'])->name('ai-toolbox.plugins.enable');
    Route::post('/plugins/{name}/disable', [PluginController::class, 'disable'])->name('ai-toolbox.plugins.disable');

    Route::get('/verify', VerifyController::class)->name('ai-toolbox.verify');
});
