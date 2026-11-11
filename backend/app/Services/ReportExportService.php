<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\SimplePdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function __construct(
        private readonly ReportAnalyticsService $analytics,
        private readonly ReportTicketDetailService $details,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function download(User $actor, array $filters, string $format): StreamedResponse
    {
        $report = $this->analytics->summarise($filters);
        $tickets = $this->details->rows($filters);
        $stamp = CarbonImmutable::now()->format('Y-m-d');
        $filename = 'helpdesk-report-'.$stamp.'.'.$format;

        $this->audit->log(
            event: 'report.exported',
            description: $actor->full_name.' exported the IT helpdesk analytics report as '.strtoupper($format).'.',
            newValues: [
                'format' => $format,
                'filters' => $report['filters'],
                'ticket_count' => $tickets->count(),
            ],
        );

        return $format === 'pdf'
            ? $this->pdf($report, $tickets, $filename)
            : $this->csv($report, $tickets, $filename);
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  Collection<int, array<string, string>>  $tickets
     */
    private function csv(array $report, Collection $tickets, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($report, $tickets): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            $this->writeRow($handle, ['IT Helpdesk Reports and Analytics']);
            $this->writeRow($handle, ['Generated', $report['generated_at'] ?? '']);
            $this->writeRow($handle, ['Date from', $report['filters']['date_from'] ?? 'All']);
            $this->writeRow($handle, ['Date to', $report['filters']['date_to'] ?? 'All']);
            $this->writeRow($handle, []);

            $this->writeRow($handle, ['Key performance indicators']);
            $this->writeRow($handle, ['Metric', 'Value']);

            foreach ($this->kpiRows($report['kpis']) as $row) {
                $this->writeRow($handle, $row);
            }

            $charts = $report['charts'];

            $this->writeSection($handle, 'Ticket volume', ['Period', 'Created', 'Closed'], array_map(
                fn (array $point) => [$point['date'], $point['created'], $point['closed']],
                $charts['volume'],
            ));
            $this->writeBucketSection($handle, 'Tickets by branch', $charts['by_branch']);
            $this->writeBucketSection($handle, 'Tickets by category', $charts['by_category']);
            $this->writeBucketSection($handle, 'Tickets by priority', $charts['by_priority']);
            $this->writeBucketSection($handle, 'Tickets by status', $charts['by_status']);
            $this->writeBucketSection($handle, 'Tickets by IT staff', $charts['by_staff']);
            $this->writeBucketSection($handle, 'Open ticket aging', $charts['aging']);
            $this->writeBucketSection($handle, 'Verification completion', $charts['verification']);

            $headers = $this->details->headers();
            $this->writeRow($handle, []);
            $this->writeRow($handle, ['Ticket details']);
            $this->writeRow($handle, $headers);

            foreach ($tickets as $ticket) {
                $this->writeRow($handle, array_map(
                    fn (string $header): string => $ticket[$header] ?? '',
                    $headers,
                ));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  Collection<int, array<string, string>>  $tickets
     */
    private function pdf(array $report, Collection $tickets, string $filename): StreamedResponse
    {
        $pdf = new SimplePdf;
        $kpis = $report['kpis'];
        $filters = $report['filters'];

        $pdf->title('IT Helpdesk Reports');
        $pdf->paragraph('Analytics for tickets raised in the selected period, followed by a full record of each ticket.');
        $pdf->keyValues([
            ['Generated', (string) ($report['generated_at'] ?? '')],
            ['Date from', (string) ($filters['date_from'] ?? 'All dates')],
            ['Date to', (string) ($filters['date_to'] ?? 'All dates')],
            ['Granularity', (string) ($filters['granularity'] ?? 'day')],
            ['Tickets listed', (string) $tickets->count()],
        ]);

        $pdf->heading('Key performance indicators');
        $pdf->table(['Metric', 'Value'], $this->kpiRows($kpis));

        $charts = $report['charts'];
        $pdf->heading('Ticket volume');
        $pdf->table(
            ['Period', 'Created', 'Closed'],
            array_map(fn (array $point) => [$point['date'], (string) $point['created'], (string) $point['closed']], $charts['volume']),
        );

        $this->pdfBuckets($pdf, 'Tickets by branch', $charts['by_branch']);
        $this->pdfBuckets($pdf, 'Tickets by category', $charts['by_category']);
        $this->pdfBuckets($pdf, 'Tickets by priority', $charts['by_priority']);
        $this->pdfBuckets($pdf, 'Tickets by status', $charts['by_status']);
        $this->pdfBuckets($pdf, 'Tickets by IT staff', $charts['by_staff']);
        $this->pdfBuckets($pdf, 'Open ticket aging', $charts['aging']);
        $this->pdfBuckets($pdf, 'Verification completion', $charts['verification']);

        $pdf->heading('Ticket details');
        $pdf->paragraph('Each ticket below includes who requested it, the issue description, and how it was resolved.');

        if ($tickets->isEmpty()) {
            $pdf->paragraph('No tickets matched the selected filters.');
        } else {
            foreach ($tickets as $ticket) {
                $reference = $ticket['Ticket number'] !== '' ? $ticket['Ticket number'] : 'Ticket';
                $subject = $ticket['Subject'] !== '' ? $ticket['Subject'] : 'No subject';
                $pdf->heading($reference.' — '.$subject);
                $pdf->keyValues([
                    ['Created', $ticket['Created at']],
                    ['Requester', $this->joinParts([
                        $ticket['Requester'],
                        $ticket['Employee ID'] !== '' ? 'Emp '.$ticket['Employee ID'] : '',
                        $ticket['Username'] !== '' ? '@'.$ticket['Username'] : '',
                    ])],
                    ['Requester email', $ticket['Requester email']],
                    ['Branch', $ticket['Branch']],
                    ['Department', $ticket['Department']],
                    ['Category', $ticket['Category']],
                    ['Priority', $ticket['Priority']],
                    ['Status', $ticket['Status']],
                    ['Assigned to', $ticket['Assigned to']],
                    ['Contact', $this->joinParts([
                        $ticket['Preferred contact'],
                        $ticket['Location / desk'],
                    ])],
                    ['Item request', $this->joinParts([
                        $ticket['Issue type'],
                        $ticket['Item type'],
                        $ticket['Item other'],
                        $ticket['Asset tag'] !== '' ? 'Asset '.$ticket['Asset tag'] : '',
                    ])],
                    ['Resolved by', $this->joinParts([
                        $ticket['Resolved by'],
                        $ticket['Resolved at'],
                    ])],
                    ['Closed by', $this->joinParts([
                        $ticket['Closed by'],
                        $ticket['Closed at'],
                    ])],
                    ['Verified at', $ticket['Verified at']],
                    ['Reopened', $ticket['Reopened'] === 'Yes'
                        ? 'Yes ('.$ticket['Reopen count'].')'
                        : 'No'],
                ]);

                if ($ticket['Description'] !== '') {
                    $pdf->paragraph('Issue: '.$ticket['Description']);
                }

                if ($ticket['Additional details'] !== '') {
                    $pdf->paragraph('Additional details: '.$ticket['Additional details']);
                }

                if ($ticket['Resolution summary'] !== '') {
                    $pdf->paragraph('Resolution: '.$ticket['Resolution summary']);
                }
            }
        }

        $binary = $pdf->output();

        return response()->streamDownload(function () use ($binary): void {
            echo $binary;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * @param  list<string>  $parts
     */
    private function joinParts(array $parts): string
    {
        return implode(' · ', array_values(array_filter(
            $parts,
            fn (string $part): bool => trim($part) !== '',
        )));
    }

    /**
     * @param  array<string, mixed>  $kpis
     * @return list<list<string>>
     */
    private function kpiRows(array $kpis): array
    {
        return [
            ['Ticket volume', (string) $kpis['ticket_volume']],
            ['Open tickets', (string) $kpis['open_tickets']],
            ['Closed tickets', (string) $kpis['closed_tickets']],
            ['Reopened tickets', (string) $kpis['reopened_tickets']],
            ['Reopen events', (string) $kpis['reopen_events']],
            ['Critical tickets', (string) $kpis['critical_tickets']],
            ['Open critical tickets', (string) $kpis['open_critical_tickets']],
            ['Aged open tickets (over 7 days)', (string) $kpis['aged_open_tickets']],
            ['Average resolution time', (string) $kpis['average_resolution']['label']],
            ['Average first response time', (string) $kpis['average_first_response']['label']],
            ['Verification issued', (string) $kpis['verification']['issued']],
            ['Verification completed', (string) $kpis['verification']['verified']],
            ['Verification completion rate', $kpis['verification']['completion_rate'].'%'],
        ];
    }

    /**
     * @param  resource  $handle
     * @param  list<array{name: string, count: int}>  $buckets
     */
    private function writeBucketSection($handle, string $title, array $buckets): void
    {
        $this->writeSection(
            $handle,
            $title,
            ['Name', 'Count'],
            array_map(fn (array $bucket) => [$bucket['name'], $bucket['count']], $buckets),
        );
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     */
    private function writeSection($handle, string $title, array $headers, array $rows): void
    {
        $this->writeRow($handle, []);
        $this->writeRow($handle, [$title]);
        $this->writeRow($handle, $headers);

        foreach ($rows as $row) {
            $this->writeRow($handle, $row);
        }
    }

    /**
     * @param  resource  $handle
     * @param  list<mixed>  $row
     */
    private function writeRow($handle, array $row): void
    {
        fputcsv($handle, $row);
    }

    /**
     * @param  list<array{name: string, count: int}>  $buckets
     */
    private function pdfBuckets(SimplePdf $pdf, string $title, array $buckets): void
    {
        $pdf->heading($title);
        $pdf->table(
            ['Name', 'Count'],
            array_map(fn (array $bucket) => [$bucket['name'], (string) $bucket['count']], $buckets),
        );
    }
}
