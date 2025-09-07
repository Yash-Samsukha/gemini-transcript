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
                'ocr_engine' => 'required|in:vision_and_gemini,full_gemini,raw',
                'output_format' => 'required|in:table,document',
            ]);

            $ocrEngine = $request->input('ocr_engine');
            $outputFormat = $request->input('output_format');
            $finalOutput = '';
            $extension = 'txt'; // Default extension for raw text

            if ($ocrEngine !== 'raw' && $outputFormat === 'table') {
                $finalOutput = "क्रमांक\tग्रंथ-नाम\tकर्ता\n";
                $extension = 'csv';
            }

            $totalImages = count($request->file('images'));
            $processedCount = 0;

            foreach ($request->file('images') as $image) {
                try {
                    Log::info("Processing image: " . $image->getClientOriginalName());
                    $path = $image->store('ocr_uploads', 'public');
                    $fullPath = Storage::disk('public')->path($path);

                    if ($ocrEngine === 'vision_and_gemini') {
                        $rawText = $this->ocrGemini->extractText($fullPath);
                        if ($outputFormat === 'table') {
                            $formattedText = $this->ocrGemini->formatTableWithGemini($rawText);
                        } else {
                            $formattedText = $this->ocrGemini->formatDocumentWithGemini($rawText);
                        }
                    } elseif ($ocrEngine === 'full_gemini') {
                        $formattedText = $this->ocrGemini->fullGeminiOcrAndFormat($fullPath, $outputFormat);
                    } else { // 'raw'
                        $formattedText = $this->ocrGemini->extractText($fullPath);
                        // For raw extraction, we don't need the header
                        if ($outputFormat === 'table') {
                            $finalOutput = '';
                            Log::warning('Raw extraction selected, but table format requested. Defaulting to raw document format.');
                        }
                    }

                    $finalOutput .= trim($formattedText) . "\n\n";
                    Storage::disk('public')->delete($path);

                    $processedCount++;
                    // This can be used for real-time progress updates in a more advanced setup
                    // $progress = ($processedCount / $totalImages) * 100;

                } catch (\Exception $e) {
                    Log::error("Error processing image " . $image->getClientOriginalName() . ": " . $e->getMessage());
                    throw new \Exception("Failed to process image: " . $image->getClientOriginalName() . " - " . $e->getMessage());
                }
            }

            $finalOutput = trim($finalOutput);
            $filePath = 'ocr_results/output.' . $extension;
            Storage::disk('public')->put($filePath, $finalOutput);

            return response()->download(Storage::disk('public')->path($filePath));
        } catch (\Exception $e) {
            Log::error("OCR processing error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
