<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Http;

class MergePdfService
{
    public function mergeByPaths(array $documents): string
    {
        $pdf = new Fpdi();
        $tempFiles = [];

        try {
            foreach ($documents as $document) {
                $url = $document['file_path'];
                $tempFilePath = tempnam(sys_get_temp_dir(), 'pdf_') . '.pdf';
                $tempFiles[] = $tempFilePath;

                $response = Http::get($url);
                if ($response->status() !== 200) {
                    throw new \Exception("Failed to download file: $url");
                }

                file_put_contents($tempFilePath, $response->body());

                if (!file_exists($tempFilePath)) {
                    throw new \Exception("File not found: $tempFilePath");
                }

                // Convert PDF to version 1.4 using Ghostscript
                $convertedFilePath = $this->convertPdfToVersion14($tempFilePath);
                $tempFiles[] = $convertedFilePath;

                $pageCount = $pdf->setSourceFile($convertedFilePath);

                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);

                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);
                }
            }

            $outputDir = public_path('merged_pdfs');
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $outputPath = $outputDir . '/merged_' . time() . '.pdf';
            $pdf->Output($outputPath, 'F');

            // Clean up temporary files
            foreach ($tempFiles as $tempFile) {
                unlink($tempFile);
            }

            return url('merged_pdfs/' . basename($outputPath));
        } catch (\Exception $e) {
            // Clean up temporary files in case of an error
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
            throw new \Exception('Error merging PDFs: ' . $e->getMessage());
        }
    }

    private function convertPdfToVersion14(string $filePath): string
    {
        $tempDir = sys_get_temp_dir();
        $tempFileName = 'converted_' . pathinfo($filePath, PATHINFO_FILENAME) . '.pdf';
        $newPdfPath = $tempDir . DIRECTORY_SEPARATOR . $tempFileName;

        $gsPath = 'C:\Program Files\gs\gs10.04.0\bin\gswin64c.exe'; // Cambia esto a la ruta correcta

        $command = "\"$gsPath\" -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=\"$newPdfPath\" \"$filePath\"";
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new \Exception("Ghostscript command failed: " . implode("\n", $output));
        }

        if (!file_exists($newPdfPath)) {
            throw new \Exception("Failed to create temporary PDF: $newPdfPath");
        }

        return $newPdfPath;
    }
}
