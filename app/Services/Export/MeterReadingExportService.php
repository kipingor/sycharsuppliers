<?php

namespace App\Services\Export;

use Illuminate\Support\Collection;

/**
 * Meter Reading Export Service
 * 
 * Handles CSV/Excel export generation for meter readings.
 * Extracted from controller to follow SRP.
 * 
 * @package App\Services\Export
 */
class MeterReadingExportService
{
    /**
     * Generate CSV from readings collection
     * 
     * @param Collection $readings
     * @return string
     */
    public function generateCsv(Collection $readings): string
    {
        $output = fopen('php://temp', 'r+');

        // Headers
        fputcsv($output, [
            'Reading ID',
            'Meter Number',
            'Meter Name',
            'Account Number',
            'Account Name',
            'Reading Date',
            'Reading',
            'Previous Reading',
            'Consumption',
            'Reading Type',
            'Reader',
            'Notes',
            'Created At',
        ]);

        // Data
        foreach ($readings as $reading) {
            $previousReading = $reading->previous_reading_value;

            fputcsv($output, [
                $reading->id,
                $reading->meter->meter_number,
                $reading->meter->meter_name,
                $reading->meter->account->account_number ?? 'N/A',
                $reading->meter->account->name ?? 'No Account',
                $reading->reading_date->format('Y-m-d'),
                $reading->reading,
                $previousReading?->reading ?? 'N/A',
                $reading->getConsumption(),
                $reading->reading_type,
                $reading->reader?->name ?? 'N/A',
                $reading->notes ?? '',
                $reading->created_at->format('Y-m-d H:i:s'),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Generate Excel from readings collection
     * 
     * @param Collection $readings
     * @return string Path to generated file
     */
    public function generateExcel(Collection $readings): string
    {
        // TODO: Implement using Laravel Excel or PhpSpreadsheet
        // For now, return CSV
        return $this->generateCsv($readings);
    }
}