<?php

namespace App\Apis;

use App\Services\MergePdfService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MergePdfApi
{
    protected $mergePdfService;

    public function __construct(MergePdfService $mergePdfService)
    {
        $this->mergePdfService = $mergePdfService;
    }

    public function mergeByPaths(Request $request): JsonResponse
    {
        
        $paths = $request->input('paths');
        if (empty($paths) || !is_array($paths)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid input'], 400);
        }

        try {
            $mergedPdfPath = $this->mergePdfService->mergeByPaths($paths);
            return response()->json([
                'status' => 'success',
                'message' => 'PDFs merged successfully',
                'data' => [
                    'merged_pdf_path' => $mergedPdfPath
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
