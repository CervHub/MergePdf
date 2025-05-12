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
            // Log the incoming documents
            \Log::info('Received PDF merge request', [
                'documents' => $documents,
            ]);

            foreach ($documents as $document) {
                $url = $document['file_path'];
                $title = $document['document_name'];
                $tempFilePath = tempnam(sys_get_temp_dir(), 'pdf_') . '.pdf';
                $tempFiles[] = $tempFilePath;

                try {
                    $response = Http::withOptions(['verify' => false])->get($url);
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

                    // Add title to each page
                    $titledFilePath = $this->addTitleToPdf($convertedFilePath, $title);
                    $tempFiles[] = $titledFilePath;

                    $pageCount = $pdf->setSourceFile($titledFilePath);

                    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                        $templateId = $pdf->importPage($pageNo);
                        $size = $pdf->getTemplateSize($templateId);

                        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                        $pdf->useTemplate($templateId);
                    }
                } catch (\Exception $e) {
                    \Log::error('Error processing document', [
                        'file_path' => $url,
                        'error' => $e->getMessage(),
                    ]);
                    continue; // Skip this document and proceed with the next
                }
            }

            $outputDir = public_path('merged_pdfs');
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $outputPath = $outputDir . '/merged_' . time() . '.pdf';
            $pdf->Output($outputPath, 'F');

            // Log successful operation details
            $requestIp = request()->ip();
            $domain = request()->getHost();
            $generatedUrl = url('merged_pdfs/' . basename($outputPath));

            \Log::info('PDF merge successful', [
                'ip' => $requestIp,
                'domain' => $domain,
                'generated_url' => $generatedUrl,
            ]);

            // Clean up temporary files
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }

            return $generatedUrl;
        } catch (\Exception $e) {
            // Log the error message
            \Log::error('Error merging PDFs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

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

        $gsPath = 'C:\gs10.04.0\bin\gswin64c.exe'; // Cambia esto a la ruta correcta

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

    private function addTitleToPdf(string $filePath, string $title): string
    {
        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($filePath);
        $tempDir = sys_get_temp_dir();
        $tempFileName = 'titled_' . pathinfo($filePath, PATHINFO_FILENAME) . '.pdf';
        $newPdfPath = $tempDir . DIRECTORY_SEPARATOR . $tempFileName;

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            // Add title
            $pdf->SetFont('Helvetica', 'B', 8);
            $titleLower = strtolower($title);
            $titleLower = str_replace(['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú'], ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U'], $titleLower);
            $titleUpper = strtoupper($titleLower);

            // Get page width and title width
            $pageWidth = $size['width'];
            $titleWidth = $pdf->GetStringWidth($titleUpper);

            // Set XY to center the title horizontally (X = center) and Y to 0 for top alignment
            $pdf->SetXY(($pageWidth - $titleWidth) / 2, 0);  // Adjust Y as needed

            // Add the title in center horizontally
            $pdf->Cell($titleWidth, 10, $titleUpper, 0, 1, 'C');
        }

        $pdf->Output($newPdfPath, 'F');

        if (!file_exists($newPdfPath)) {
            throw new \Exception("Failed to create titled PDF: $newPdfPath");
        }

        return $newPdfPath;
    }
}
