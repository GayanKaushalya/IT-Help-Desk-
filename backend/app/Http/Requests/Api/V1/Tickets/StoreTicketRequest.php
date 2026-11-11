<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tickets;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTicketRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Ticket::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKilobytes = (int) config('helpdesk.attachments.max_kilobytes');
        $maxFiles = (int) config('helpdesk.attachments.max_per_ticket');
        $extensions = implode(',', config('helpdesk.attachments.allowed_extensions'));
        $mimes = implode(',', config('helpdesk.attachments.allowed_mime_types'));
        $contactMethods = collect(config('helpdesk.contact_methods'))->pluck('value')->all();
        $issueTypes = collect(config('helpdesk.issue_types'))->pluck('value')->all();
        $itemTypes = collect(config('helpdesk.item_types'))->pluck('value')->all();

        return [
            'subject' => ['required', 'string', 'min:5', 'max:200'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('ticket_categories', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
            'priority_id' => [
                'required',
                'integer',
                Rule::exists('ticket_priorities', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)),
            ],
            'description' => ['nullable', 'string', 'max:4000'],
            'preferred_contact_method' => ['nullable', 'string', Rule::in($contactMethods)],
            'contact_phone' => ['required', 'string', 'min:7', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'contact_location' => ['nullable', 'string', 'max:150'],
            'additional_details' => ['nullable', 'string', 'max:2000'],
            'issue_type' => ['nullable', 'string', Rule::in($issueTypes)],
            'item_type' => ['nullable', 'string', Rule::in($itemTypes)],
            'item_other' => ['nullable', 'string', 'max:200'],
            'attachments' => ['nullable', 'array', 'max:'.$maxFiles],
            'attachments.*' => ['file', 'max:'.$maxKilobytes, 'mimes:'.$extensions, 'mimetypes:'.$mimes],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $categoryId = (int) $this->input('category_id');
            $category = TicketCategory::query()->active()->whereKey($categoryId)->first();
            $requiresItem = (bool) ($category?->requires_item_details);

            $issueType = $this->input('issue_type');
            $itemType = $this->input('item_type');
            $itemOther = $this->input('item_other');

            if ($requiresItem) {
                if (! is_string($issueType) || $issueType === '') {
                    $validator->errors()->add('issue_type', 'Select an issue type.');
                }

                if (! is_string($itemType) || $itemType === '') {
                    $validator->errors()->add('item_type', 'Select an item type.');
                }

                $needsOther = $issueType === 'other' || $itemType === 'other';

                if ($needsOther && (! is_string($itemOther) || $itemOther === '')) {
                    $validator->errors()->add('item_other', 'Describe the other issue or item.');
                }

                return;
            }

            if (is_string($issueType) && $issueType !== '') {
                $validator->errors()->add('issue_type', 'Issue type is only used for item-related categories.');
            }

            if (is_string($itemType) && $itemType !== '') {
                $validator->errors()->add('item_type', 'Item type is only used for item-related categories.');
            }

            if (is_string($itemOther) && $itemOther !== '') {
                $validator->errors()->add('item_other', 'Other item details are only used for item-related categories.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'Enter a subject for this request.',
            'subject.min' => 'Subject must be at least 5 characters.',
            'category_id.required' => 'Select a category.',
            'category_id.exists' => 'The selected category is not available.',
            'priority_id.required' => 'Select a priority.',
            'priority_id.exists' => 'The selected priority is not available.',
            'preferred_contact_method.in' => 'Select a valid contact method.',
            'contact_phone.required' => 'Enter a phone number so IT can contact you.',
            'contact_phone.regex' => 'Enter a valid phone number.',
            'branch_id.required' => 'Select a branch.',
            'branch_id.exists' => 'The selected branch is not available.',
            'issue_type.in' => 'Select a valid issue type.',
            'item_type.in' => 'Select a valid item type.',
            'item_other.max' => 'Other details cannot exceed 200 characters.',
            'attachments.max' => 'Too many files were attached.',
            'attachments.*.file' => 'Each attachment must be a valid file.',
            'attachments.*.max' => 'Each file must be 10 MB or smaller.',
            'attachments.*.mimes' => 'This file type is not allowed.',
            'attachments.*.mimetypes' => 'The file contents do not match an allowed type.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach ([
            'subject',
            'description',
            'contact_phone',
            'contact_location',
            'additional_details',
            'preferred_contact_method',
            'issue_type',
            'item_type',
            'item_other',
        ] as $field) {
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
     * Only the fields an employee is allowed to set when raising a ticket.
     *
     * Requester, status, assignee and reference are assigned server-side so
     * they cannot arrive through mass assignment.
     *
     * @return array{
     *     subject: string,
     *     category_id: int,
     *     priority_id: int,
     *     description: string,
     *     preferred_contact_method: string|null,
     *     contact_phone: string,
     *     branch_id: int,
     *     contact_location: string|null,
     *     additional_details: string|null,
     *     issue_type: string|null,
     *     item_type: string|null,
     *     item_other: string|null
     * }
     */
    public function ticketData(): array
    {
        $validated = $this->validated();

        $category = TicketCategory::query()->active()->whereKey((int) $validated['category_id'])->first();
        $requiresItem = (bool) ($category?->requires_item_details);

        return [
            'subject' => (string) $validated['subject'],
            'category_id' => (int) $validated['category_id'],
            'priority_id' => (int) $validated['priority_id'],
            'description' => (string) ($validated['description'] ?? ''),
            'preferred_contact_method' => $validated['preferred_contact_method'] ?? null,
            'contact_phone' => (string) $validated['contact_phone'],
            'branch_id' => (int) $validated['branch_id'],
            'contact_location' => $validated['contact_location'] ?? null,
            'additional_details' => $validated['additional_details'] ?? null,
            'issue_type' => $requiresItem ? ($validated['issue_type'] ?? null) : null,
            'item_type' => $requiresItem ? ($validated['item_type'] ?? null) : null,
            'item_other' => $requiresItem ? ($validated['item_other'] ?? null) : null,
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
