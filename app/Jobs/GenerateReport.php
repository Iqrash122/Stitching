<?php

namespace App\Jobs;

use App\Domain\Documents;
use App\Domain\Reports;
use App\Models\ReportExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $exportId) {}

    public function handle(): void
    {
        $export = ReportExport::findOrFail($this->exportId);
        if ($export->state === 'ready') {
            return;
        }$export->update(['state' => 'running', 'error' => null]);
        $rows = Reports::rows($export->report, $export->filters);
        $path = Documents::writeExport($rows, $export->report, $export->format);
        $export->update(['state' => 'ready', 'path' => $path, 'row_count' => count($rows)]);
    }

    public function failed(?\Throwable $exception): void
    {
        ReportExport::whereKey($this->exportId)->update(['state' => 'failed', 'error' => 'Report generation failed. Check the worker status and retry.']);
    }
}
