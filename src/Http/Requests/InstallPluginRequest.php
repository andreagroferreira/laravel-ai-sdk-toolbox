<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class InstallPluginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'max:500', 'regex:#^(https?://|git@|[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+|/|[a-zA-Z]:\\\\)#'],
            'path' => ['nullable', 'string', 'max:255', 'regex:#^[a-zA-Z0-9_./-]+$#', 'not_regex:#\.\.#'],
            'disabled' => ['sometimes', 'boolean'],
        ];
    }

    public function source(): string
    {
        $source = $this->validated('source');

        return is_string($source) ? $source : '';
    }

    public function subpath(): ?string
    {
        $path = $this->validated('path');

        return is_string($path) ? $path : null;
    }

    public function disabled(): bool
    {
        return $this->boolean('disabled');
    }
}
