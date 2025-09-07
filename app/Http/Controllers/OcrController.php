<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OcrGeminiService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class OcrController extends Controller
{
    protected $ocrGemini;

    public function __construct(OcrGeminiService $ocrGemini)
    {
        $this->ocrGemini = $ocrGemini;
    }

    /**
     * Handle bulk OCR for uploaded images with format selection.
     */
    public function bulkOcr(Request $request)
    {
        try {
            $request->validate([
                'images.*' => 'required|image|max:10240', // 10MB max
                'ocr_engine' => 'required|in:vision_and_gemini,full_gemini',
                'output_format' => 'required|in:table,document',
            ]);

            $ocrEngine = $request->input('ocr_engine');
            $outputFormat = $request->input('output_format');
            $finalOutput = '';

            // Define header for table format
            if ($outputFormat === 'table') {
                $finalOutput = "क्रमांक\tग्रंथ-नाम\tकर्ता\n";
            }

            foreach ($request->file('images') as $image) {
                try {
                    Log::info("Processing image: " . $image->getClientOriginalName());
                    $path = $image->store('ocr_uploads', 'public');
                    $fullPath = Storage::disk('public')->path($path);

                    if ($ocrEngine === 'vision_and_gemini') {
                        // Original workflow: Vision API for OCR, then Gemini for formatting
                        $rawText = $this->ocrGemini->extractText($fullPath);
                        if ($outputFormat === 'table') {
                            $formattedText = $this->ocrGemini->formatTableWithGemini($rawText);
                        } else {
                            $formattedText = $this->ocrGemini->formatDocumentWithGemini($rawText);
                        }
                    } else { // full_gemini
                        // New workflow: Gemini for both OCR and formatting
                        $formattedText = $this->ocrGemini->fullGeminiOcrAndFormat($fullPath, $outputFormat);
                    }

                    // Append the result and clean up the temp image
                    $finalOutput .= trim($formattedText) . "\n\n";
                    Storage::disk('public')->delete($path);

                } catch (\Exception $e) {
                    Log::error("Error processing image " . $image->getClientOriginalName() . ": " . $e->getMessage());
                    throw new \Exception("Failed to process image: " . $image->getClientOriginalName() . " - " . $e->getMessage());
                }
            }

            $finalOutput = trim($finalOutput);
            $extension = ($outputFormat === 'table') ? 'csv' : 'txt';
            $filePath = 'ocr_results/output.' . $extension;
            Storage::disk('public')->put($filePath, $finalOutput);

            return response()->download(Storage::disk('public')->path($filePath));
        } catch (\Exception $e) {
            Log::error("OCR processing error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
