<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Hr;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\HrRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreHrRequestRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', HrRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKilobytes = (int) config('hrhelpdesk.attachments.max_kilobytes');
        $maxFiles = (int) config('hrhelpdesk.attachments.max_per_request');
        $extensions = implode(',', (array) config('hrhelpdesk.attachments.allowed_extensions'));
        $mimes = implode(',', (array) config('helpdesk.attachments.allowed_mime_types'));

        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('hr_categories', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'request_type_id' => [
                'required',
                'integer',
                Rule::exists('hr_request_types', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->where('hr_category_id', (int) $this->input('category_id'))),
            ],
            'description' => ['required', 'string', 'min:10', 'max:4000'],
            'employee_id' => ['nullable', 'string', 'min:1', 'max:40'],
            'employee_name' => ['nullable', 'string', 'min:2', 'max:150'],
            'department_name' => ['nullable', 'string', 'max:120'],
            'designation' => ['nullable', 'string', 'max:100'],
            'attachments' => ['nullable', 'array', 'max:'.$maxFiles],
            'attachments.*' => ['file', 'max:'.$maxKilobytes, 'mimes:'.$extensions, 'mimetypes:'.$mimes],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Select a category.',
            'category_id.exists' => 'The selected category is not available.',
            'request_type_id.required' => 'Select a request type.',
            'request_type_id.exists' => 'The selected request type is not available for this category.',
            'description.required' => 'Describe your request.',
            'description.min' => 'Please give a little more detail (at least 10 characters).',
            'attachments.max' => 'Too many files were attached.',
            'attachments.*.file' => 'Each attachment must be a valid file.',
            'attachments.*.max' => 'Each file is larger than the allowed size.',
            'attachments.*.mimes' => 'This file type is not allowed.',
            'attachments.*.mimetypes' => 'The file contents do not match an allowed type.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['description', 'employee_id', 'employee_name', 'department_name', 'designation'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $merge[$field] = $value === '' ? null : $value;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * Fields an employee may set; requester, status, confidentiality,
     * reference and SLA are assigned server-side.
     *
     * @return array{
     *     category_id: int,
     *     request_type_id: int,
     *     description: string,
     *     employee_id: ?string,
     *     employee_name: ?string,
     *     department_name: ?string,
     *     designation: ?string
     * }
     */
    public function requestData(): array
    {
        $validated = $this->validated();

        return [
            'category_id' => (int) $validated['category_id'],
            'request_type_id' => (int) $validated['request_type_id'],
            'description' => (string) $validated['description'],
            'employee_id' => isset($validated['employee_id']) ? (string) $validated['employee_id'] : null,
            'employee_name' => isset($validated['employee_name']) ? (string) $validated['employee_name'] : null,
            'department_name' => isset($validated['department_name']) ? (string) $validated['department_name'] : null,
            'designation' => isset($validated['designation']) ? (string) $validated['designation'] : null,
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function uploadedFiles(): array
    {
        $files = $this->file('attachments', []);

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        return array_values(array_filter(
            is_array($files) ? $files : [],
            fn (mixed $file): bool => $file instanceof UploadedFile,
        ));
    }
}
