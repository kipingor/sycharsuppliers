<?php

namespace App\Utilities;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use Exception;

class FileHelper
{
    /**
     * Upload a file to a specific disk location.
     */
    public static function uploadFile(UploadedFile $file, string $directory = 'uploads'): string
    {
        try {
            return $file->store($directory, 'public');
        } catch (Exception $e) {
            Log::error("File upload failed: " . $e->getMessage());
            throw new Exception("File upload failed.");
        }
    }

    /**
     * Delete a file from storage.
     */
    public static function deleteFile(string $filePath): bool
    {
        if (Storage::disk('public')->exists($filePath)) {
            return Storage::disk('public')->delete($filePath);
        }

        return false;
    }

    /**
     * Generate and download a PDF file.
     */
    public static function generatePdf(string $view, array $data, string $fileName = 'document.pdf')
    {
        try {
            $pdf = Pdf::loadView($view, $data);
            return $pdf->download($fileName);
        } catch (Exception $e) {
            Log::error("PDF generation failed: " . $e->getMessage());
            throw new Exception("Failed to generate PDF.");
        }
    }

    /**
     * Generate and download a CSV file.
     */
    public static function generateCsv(string $fileName, array $headers, array $data)
    {
        try {
            $csvFilePath = storage_path("app/public/{$fileName}.csv");
            $file = fopen($csvFilePath, 'w');

            fputcsv($file, $headers);
            foreach ($data as $row) {
                fputcsv($file, $row);
            }

            fclose($file);

            return response()->download($csvFilePath)->deleteFileAfterSend(true);
        } catch (Exception $e) {
            Log::error("CSV export failed: " . $e->getMessage());
            throw new Exception("Failed to generate CSV.");
        }
    }

    /**
     * Generate and download an Excel file.
     */
    public static function generateExcel(string $exportClass, string $fileName = 'export.xlsx')
    {
        try {
            return Excel::download(new $exportClass, $fileName);
        } catch (Exception $e) {
            Log::error("Excel export failed: " . $e->getMessage());
            throw new Exception("Failed to generate Excel file.");
        }
    }
}
