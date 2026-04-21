<?php

declare(strict_types=1);

namespace App\Modules\MasterDataExchange\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ImportMasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $format = $this->input('format');

        $this->merge([
            'mode' => strtolower((string) $this->input('mode', 'dry_run')),
            'format' => is_string($format) ? strtolower($format) : $format,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', Rule::in(['dry_run', 'commit'])],
            'format' => ['nullable', 'string', Rule::in(['csv', 'json'])],
            'file' => ['sometimes', 'file', 'max:5120'],
            'content' => ['sometimes', 'string', 'max:500000'],
            'rows' => ['sometimes', 'array', 'max:500'],
            'rows.*' => ['array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasFile = $this->hasFile('file');
            $hasContent = $this->filled('content');
            $hasRows = $this->has('rows');

            if (! $hasFile && ! $hasContent && ! $hasRows) {
                $validator->errors()->add('file', 'Provide a file upload, inline content, or rows payload for import.');
            }

            if ($hasRows && $this->filled('format') && $this->input('format') !== 'json') {
                $validator->errors()->add('format', 'rows payload only supports json format.');
            }

            if (($hasFile || $hasContent) && ! $this->filled('format')) {
                $uploadedFile = $this->file('file');
                $originalName = $uploadedFile?->getClientOriginalName();
                $extension = $originalName !== null ? strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) : null;
                $detected = match ($extension) {
                    'json' => 'json',
                    'csv', 'txt' => 'csv',
                    default => null,
                };

                if ($detected === null && ! $hasRows) {
                    $validator->errors()->add('format', 'format is required when it cannot be inferred from the uploaded file.');
                }
            }
        });
    }
}
