<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimal PDF 1.4 writer for tabular administrative reports.
 *
 * Avoids a heavy HTML-to-PDF stack so exports stay available on the
 * locked-down SQL Server host without extra system packages.
 */
class SimplePdf
{
    private const PAGE_WIDTH = 595.28;

    private const PAGE_HEIGHT = 841.89;

    private const MARGIN = 42.0;

    /** @var list<list<string>> */
    private array $pages = [];

    /** @var list<string> */
    private array $current = [];

    private float $y = 0;

    public function __construct()
    {
        $this->newPage();
    }

    public function title(string $text): void
    {
        $this->ensureSpace(36);
        $this->text(self::MARGIN, $this->y, $this->sanitize($text), 18, true);
        $this->y -= 22;
    }

    public function heading(string $text): void
    {
        $this->ensureSpace(28);
        $this->y -= 8;
        $this->text(self::MARGIN, $this->y, $this->sanitize($text), 12, true);
        $this->y -= 16;
    }

    public function paragraph(string $text): void
    {
        foreach ($this->wrap($this->sanitize($text), 96) as $line) {
            $this->ensureSpace(14);
            $this->text(self::MARGIN, $this->y, $line, 9, false);
            $this->y -= 12;
        }
    }

    /**
     * @param  list<array{0: string, 1: string}>  $pairs
     */
    public function keyValues(array $pairs): void
    {
        foreach ($pairs as [$label, $value]) {
            $this->ensureSpace(14);
            $this->text(self::MARGIN, $this->y, $this->sanitize($label), 9, true);
            $this->text(self::MARGIN + 170, $this->y, $this->sanitize($value), 9, false);
            $this->y -= 13;
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    public function table(array $headers, array $rows): void
    {
        $columns = max(1, count($headers));
        $usable = self::PAGE_WIDTH - (self::MARGIN * 2);
        $widths = array_fill(0, $columns, $usable / $columns);
        $widths[0] = $usable * 0.42;
        if ($columns > 1) {
            $rest = ($usable - $widths[0]) / ($columns - 1);
            for ($i = 1; $i < $columns; $i++) {
                $widths[$i] = $rest;
            }
        }

        $this->drawRow($headers, $widths, true);

        if ($rows === []) {
            $this->drawRow(array_pad(['No data for this section.'], $columns, ''), $widths, false);

            return;
        }

        foreach ($rows as $row) {
            $cells = [];
            for ($i = 0; $i < $columns; $i++) {
                $cells[] = (string) ($row[$i] ?? '');
            }
            $this->drawRow($cells, $widths, false);
        }

        $this->y -= 8;
    }

    public function output(): string
    {
        $this->flushPage();

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';

        $kids = [];
        $contentIds = [];
        $pageCount = count($this->pages);

        for ($i = 0; $i < $pageCount; $i++) {
            $contentIds[] = 4 + ($i * 2);
            $kids[] = (3 + ($i * 2)).' 0 R';
        }

        $objects[] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pageCount.' >>';

        foreach ($this->pages as $index => $commands) {
            $stream = implode("\n", $commands);
            $pageId = 3 + ($index * 2);
            $contentId = 4 + ($index * 2);
            $objects[$pageId - 1] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Contents %d 0 R /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> >> >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $contentId,
                3 + ($pageCount * 2),
                4 + ($pageCount * 2),
            );
            $objects[$contentId - 1] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
        }

        $fontId = 3 + ($pageCount * 2);
        $objects[$fontId - 1] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $i => $body) {
            $offsets[$i + 1] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$body."\nendobj\n";
        }

        $xref = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    /**
     * @param  list<string>  $cells
     * @param  list<float>  $widths
     */
    private function drawRow(array $cells, array $widths, bool $header): void
    {
        $this->ensureSpace(16);
        $x = self::MARGIN;

        foreach ($cells as $index => $cell) {
            $maxChars = max(8, (int) floor(($widths[$index] ?? 80) / 5.2));
            $this->text($x, $this->y, $this->truncate($this->sanitize((string) $cell), $maxChars), 8, $header);
            $x += $widths[$index] ?? 80;
        }

        $this->y -= 13;
    }

    private function newPage(): void
    {
        $this->flushPage();
        $this->current = [];
        $this->y = self::PAGE_HEIGHT - self::MARGIN;
    }

    private function flushPage(): void
    {
        if ($this->current !== []) {
            $this->pages[] = $this->current;
            $this->current = [];
        }
    }

    private function ensureSpace(float $needed): void
    {
        if ($this->y - $needed < self::MARGIN) {
            $this->newPage();
        }
    }

    private function text(float $x, float $y, string $text, int $size, bool $bold): void
    {
        $font = $bold ? 'F2' : 'F1';
        $this->current[] = sprintf(
            'BT /%s %d Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET',
            $font,
            $size,
            $x,
            $y,
            $this->escape($text),
        );
    }

    private function sanitize(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        return $converted === false ? preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text : $converted;
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, int $width): array
    {
        $wrapped = wordwrap($text, $width, "\n", true);

        return $wrapped === '' ? [''] : explode("\n", $wrapped);
    }

    private function truncate(string $text, int $max): string
    {
        if (strlen($text) <= $max) {
            return $text;
        }

        return rtrim(substr($text, 0, max(1, $max - 3))).'...';
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
