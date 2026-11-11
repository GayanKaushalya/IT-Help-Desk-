<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\User;
use App\Services\AuditLogger;
use App\Support\SimplePdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrReportExportService
{
    public function __construct(
        private readonly HrReportAnalyticsService $analytics,
        private readonly HrReportDetailService $details,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function download(User $actor, array $filters, string $format): StreamedResponse
    {
        $report = $this->analytics->summarise($actor, $filters);
        $requests = $this->details->rows($actor, $filters);
        $stamp = CarbonImmutable::now()->format('Y-m-d');
        $filename = 'hr-helpdesk-report-'.$stamp.'.'.$format;

        $this->audit->log(
            event: 'hr.report.exported',
            description: $actor->full_name.' exported the HR helpdesk analytics report as '.strtoupper($format).'.',
            newValues: [
                'format' => $format,
                'filters' => $report['filters'],
                'request_count' => $requests->count(),
            ],
        );

        return $format === 'pdf'
            ? $this->pdf($report, $requests, $filename)
            : $this->csv($report, $requests, $filename);
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  Collection<int, array<string, string>>  $requests
     */
    private function csv(array $report, Collection $requests, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($report, $requests): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            $this->writeRow($handle, ['HR Helpdesk Reports and Analytics']);
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

            $this->writeSection($handle, 'Request volume', ['Period', 'Created', 'Closed'], array_map(
                fn (array $point) => [$point['date'], $point['created'], $point['closed']],
                $charts['volume'],
            ));
            $this->writeSection($handle, 'Employee complaint trends', ['Period', 'Created', 'Closed'], array_map(
                fn (array $point) => [$point['date'], $point['created'], $point['closed']],
                $charts['complaint_trends'] ?? [],
            ));
            $this->writeBucketSection($handle, 'Requests by branch', $charts['by_branch']);
            $this->writeBucketSection($handle, 'Requests by category', $charts['by_category']);
            $this->writeBucketSection($handle, 'Requests by type', $charts['by_request_type']);
            $this->writeBucketSection($handle, 'Requests by status', $charts['by_status']);
            $this->writeBucketSection($handle, 'Requests by HR staff', $charts['by_staff']);
            $this->writeBucketSection($handle, 'Open request aging', $charts['aging']);
            $this->writeBucketSection($handle, 'Department referrals', $charts['referrals']);

            $headers = $this->details->headers();
            $this->writeRow($handle, []);
            $this->writeRow($handle, ['Request details']);
            $this->writeRow($handle, $headers);

            foreach ($requests as $request) {
                $this->writeRow($handle, array_map(
                    fn (string $header): string => $request[$header] ?? '',
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
     * @param  Collection<int, array<string, string>>  $requests
     */
    private function pdf(array $report, Collection $requests, string $filename): StreamedResponse
    {
        $pdf = new SimplePdf;
        $kpis = $report['kpis'];
        $filters = $report['filters'];

        $pdf->title('HR Helpdesk Reports');
        $pdf->paragraph('Analytics for HR requests raised in the selected period, followed by a full record of each request.');
        $pdf->keyValues([
            ['Generated', (string) ($report['generated_at'] ?? '')],
            ['Date from', (string) ($filters['date_from'] ?? 'All dates')],
            ['Date to', (string) ($filters['date_to'] ?? 'All dates')],
            ['Granularity', (string) ($filters['granularity'] ?? 'day')],
            ['Requests listed', (string) $requests->count()],
        ]);

        $pdf->heading('Key performance indicators');
        $pdf->table(['Metric', 'Value'], $this->kpiRows($kpis));

        $charts = $report['charts'];
        $pdf->heading('Request volume');
        $pdf->table(
            ['Period', 'Created', 'Closed'],
            array_map(fn (array $point) => [$point['date'], (string) $point['created'], (string) $point['closed']], $charts['volume']),
        );
        $pdf->heading('Employee complaint trends');
        $pdf->table(
            ['Period', 'Created', 'Closed'],
            array_map(
                fn (array $point) => [$point['date'], (string) $point['created'], (string) $point['closed']],
                $charts['complaint_trends'] ?? [],
            ),
        );

        $this->pdfBuckets($pdf, 'Requests by branch', $charts['by_branch']);
        $this->pdfBuckets($pdf, 'Requests by category', $charts['by_category']);
        $this->pdfBuckets($pdf, 'Requests by type', $charts['by_request_type']);
        $this->pdfBuckets($pdf, 'Requests by status', $charts['by_status']);
        $this->pdfBuckets($pdf, 'Requests by HR staff', $charts['by_staff']);
        $this->pdfBuckets($pdf, 'Open request aging', $charts['aging']);
        $this->pdfBuckets($pdf, 'Department referrals', $charts['referrals']);

        $pdf->heading('Request details');
        $pdf->paragraph('Each request below includes who raised it, the category, and how it was resolved.');

        if ($requests->isEmpty()) {
            $pdf->paragraph('No HR requests matched the selected filters.');
        } else {
            foreach ($requests as $request) {
                $reference = $request['Reference'] !== '' ? $request['Reference'] : 'HR request';
                $pdf->heading($reference.' — '.$request['Category']);
                $pdf->keyValues([
                    ['Created', $request['Created at']],
                    ['Requester', $this->joinParts([
                        $request['Requester'],
                        $request['Employee ID'] !== '' ? 'Emp '.$request['Employee ID'] : '',
                        $request['Username'] !== '' ? '@'.$request['Username'] : '',
                    ])],
                    ['Branch', $request['Branch']],
                    ['Department', $request['Department']],
                    ['Category', $request['Category']],
                    ['Request type', $request['Request type']],
                    ['Status', $request['Status']],
                    ['Confidential', $request['Confidential']],
                    ['Assigned to', $request['Assigned to']],
                    ['Due at', $request['Due at']],
                    ['Resolved at', $request['Resolved at']],
                    ['Closed at', $request['Closed at']],
                ]);

                if ($request['Description'] !== '') {
                    $pdf->paragraph('Request: '.$request['Description']);
                }

                if ($request['Resolution summary'] !== '') {
                    $pdf->paragraph('Resolution: '.$request['Resolution summary']);
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
            ['Request volume', (string) $kpis['request_volume']],
            ['Open requests', (string) $kpis['open_requests']],
            ['Completed requests', (string) $kpis['completed_requests']],
            ['Overdue requests', (string) $kpis['overdue_requests']],
            ['Aged open requests (over 7 days)', (string) $kpis['aged_open_requests']],
            ['Complaints', (string) $kpis['complaints']],
            ['Confidential requests', (string) $kpis['confidential_requests']],
            ['Average resolution time', (string) $kpis['average_resolution']['label']],
            ['Average first response time', (string) $kpis['average_first_response']['label']],
            ['Referrals total', (string) $kpis['referrals']['total']],
            ['Referrals completed', (string) $kpis['referrals']['completed']],
            ['Referral completion rate', $kpis['referrals']['completion_rate'].'%'],
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
