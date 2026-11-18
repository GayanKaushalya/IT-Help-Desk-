<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Administration\Staff;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Http\UploadedFile;

class AttachAdmRequestFilesRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'attachments' => ['required', 'array', 'min:1', 'max:'.$maxFiles],
            'attachments.*' => ['file', 'max:'.$maxKilobytes, 'mimes:'.$extensions, 'mimetypes:'.$mimes],
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