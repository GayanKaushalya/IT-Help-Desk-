<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Administration;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\AdmRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreAdmRequestRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AdmRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKilobytes = (int) config('administrationhelpdesk.attachments.max_kilobytes');
        $maxFiles = (int) config('administrationhelpdesk.attachments.max_per_request');
        $extensions = implode(',', (array) config('administrationhelpdesk.attachments.allowed_extensions'));
        $mimes = implode(',', (array) config('helpdesk.attachments.allowed_mime_types'));

        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('adm_categories', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'request_type_id' => [
                'required',
                'integer',
                Rule::exists('adm_request_types', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->where('adm_category_id', (int) $this->input('category_id'))),
            ],
            'subject' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['required', 'string', 'min:10', 'max:4000'],
            'required_date' => ['nullable', 'date', 'after_or_equal:today'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'employee_id' => ['nullable', 'string', 'min:1', 'max:40'],
            'employee_name' => ['nullable', 'string', 'min:2', 'max:150'],
            'department_name' => ['nullable', 'string', 'max:120'],
            'designation' => ['nullable', 'string', 'max:100'],
            'details' => ['nullable', 'array', 'max:20'],
            'details.*' => ['nullable', 'string', 'max:500'],
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
            'subject.required' => 'Enter a short subject for your request.',
            'subject.min' => 'The subject is too short.',
            'required_date.after_or_equal' => 'The required date cannot be in the past.',
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

        foreach (['subject', 'description', 'required_date', 'company_name', 'employee_id', 'employee_name', 'department_name', 'designation'] as $field) {
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
     * Fields an employee may set; requester, status,
     * reference and SLA are assigned server-side.
     *
     * @return array{
     *     category_id: int,
     *     request_type_id: int,
     *     subject: string,
     *     description: string,
     *     required_date: ?string,
     *     company_name: ?string,
     *     employee_id: ?string,
     *     employee_name: ?string,
     *     department_name: ?string,
     *     designation: ?string,
     *     details: ?array<string, string>
     * }
     */
    public function requestData(): array
    {
        $validated = $this->validated();

        return [
            'category_id' => (int) $validated['category_id'],
            'request_type_id' => (int) $validated['request_type_id'],
            'subject' => (string) $validated['subject'],
            'description' => (string) $validated['description'],
            'required_date' => isset($validated['required_date']) ? (string) $validated['required_date'] : null,
            'company_name' => isset($validated['company_name']) ? (string) $validated['company_name'] : null,
            'employee_id' => isset($validated['employee_id']) ? (string) $validated['employee_id'] : null,
            'employee_name' => isset($validated['employee_name']) ? (string) $validated['employee_name'] : null,
            'department_name' => isset($validated['department_name']) ? (string) $validated['department_name'] : null,
            'designation' => isset($validated['designation']) ? (string) $validated['designation'] : null,
            'details' => isset($validated['details']) && is_array($validated['details']) ? $validated['details'] : null,
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
