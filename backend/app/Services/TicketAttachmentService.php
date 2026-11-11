<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stores ticket files on a private disk and streams them only after
 * authorization has already been checked by the caller.
 */
class TicketAttachmentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @var list<string> */
    private const BLOCKED_EXTENSIONS = [
        'exe', 'bat', 'cmd', 'com', 'cpl', 'dll', 'js', 'jse', 'msi', 'msp',
        'pif', 'ps1', 'scr', 'vbe', 'vbs', 'wsf', 'wsh', 'sh', 'php', 'phar',
        'jar', 'hta', 'lnk',
    ];

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, TicketAttachment>
     */
    public function storeMany(Ticket $ticket, User $uploader, array $files): array
    {
        $maxPerTicket = (int) config('helpdesk.attachments.max_per_ticket');
        $existing = $ticket->attachments()->count();

        if ($existing + count($files) > $maxPerTicket) {
            throw ValidationException::withMessages([
                'attachments' => ['A ticket may have at most '.$maxPerTicket.' attachments.'],
            ]);
        }

        $stored = [];

        foreach ($files as $file) {
            $stored[] = $this->store($ticket, $uploader, $file);
        }

        return $stored;
    }

    public function store(Ticket $ticket, User $uploader, UploadedFile $file): TicketAttachment
    {
        $this->assertSafe($file);

        $extension = $this->safeExtension($file);
        $filename = Str::uuid()->toString().'.'.$extension;
        $directory = 'tickets/'.$ticket->id;
        $disk = (string) config('helpdesk.attachments.disk');

        $path = $file->storeAs($directory, $filename, $disk);

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'attachments' => ['The attachment could not be stored. Please try again.'],
            ]);
        }

        $absolute = Storage::disk($disk)->path($path);

        $attachment = $ticket->attachments()->create([
            'uploaded_by' => $uploader->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $this->detectedMime($file) ?? $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
            'checksum' => is_file($absolute) ? hash_file('sha256', $absolute) : null,
        ]);

        $this->audit->log(
            event: 'ticket.attachment_added',
            subject: $ticket,
            newValues: [
                'attachment_id' => $attachment->id,
                'original_name' => $attachment->original_name,
            ],
            description: $uploader->username.' attached '.$attachment->original_name.' to '.$ticket->reference,
        );

        return $attachment;
    }

    public function download(TicketAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->path)) {
            abort(404, 'The attachment is no longer available.');
        }

        return $disk->download(
            $attachment->path,
            $this->downloadName($attachment),
            [
                'Content-Type' => $attachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function assertSafe(UploadedFile $file): void
    {
        $extension = $this->safeExtension($file);
        $allowedExtensions = config('helpdesk.attachments.allowed_extensions');
        $allowedMimes = config('helpdesk.attachments.allowed_mime_types');
        $maxKilobytes = (int) config('helpdesk.attachments.max_kilobytes');

        if (in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'attachments' => ['Executable and script files are not allowed.'],
            ]);
        }

        if (! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'attachments' => ['This file type is not allowed.'],
            ]);
        }

        $sizeKilobytes = (int) ceil(($file->getSize() ?: 0) / 1024);

        if ($sizeKilobytes > $maxKilobytes) {
            throw ValidationException::withMessages([
                'attachments' => ['Each file must be '.$maxKilobytes.' KB or smaller.'],
            ]);
        }

        $clientMime = (string) $file->getMimeType();
        $detectedMime = $this->detectedMime($file);

        if ($clientMime !== '' && ! in_array($clientMime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'attachments' => ['The file contents do not match an allowed type.'],
            ]);
        }

        if (
            $detectedMime !== null
            && ! in_array($detectedMime, ['application/octet-stream', 'inode/x-empty', 'text/plain'], true)
            && ! in_array($detectedMime, $allowedMimes, true)
        ) {
            throw ValidationException::withMessages([
                'attachments' => ['The file contents do not match an allowed type.'],
            ]);
        }
    }

    private function safeExtension(UploadedFile $file): string
    {
        return strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
    }

    private function detectedMime(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();

        if (! is_string($path) || ! is_file($path)) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return null;
        }

        $mime = finfo_file($finfo, $path) ?: null;
        finfo_close($finfo);

        return $mime ?: null;
    }

    private function downloadName(TicketAttachment $attachment): string
    {
        $original = pathinfo($attachment->original_name, PATHINFO_FILENAME);
        $extension = pathinfo($attachment->path, PATHINFO_EXTENSION);
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $original) ?: 'attachment';

        return $safe.($extension !== '' ? '.'.$extension : '');
    }
}
