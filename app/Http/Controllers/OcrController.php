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
                'output_format' => 'required|in:table,document',
            ]);

            $outputFormat = $request->input('output_format');
            $finalOutput = '';

            // Define header for table format
            if ($outputFormat === 'table') {
                $finalOutput = "क्रमांक\tग्रंथ-नाम\tकर्ता\n";
            }

            foreach ($request->file('images') as $image) {
                try {
                    Log::info("Processing image: " . $image->getClientOriginalName());

                    // Save temp to public disk
                    $path = $image->store('ocr_uploads', 'public');
                    $fullPath = Storage::disk('public')->path($path);

                    // OCR with Vision API
                    $rawText = $this->ocrGemini->extractText($fullPath);

                    // Process text based on format
                    if ($outputFormat === 'table') {
                        $formattedText = $this->ocrGemini->formatTableWithGemini($rawText);
                        // Append to final output
                        $finalOutput .= trim($formattedText) . "\n\n";
                    } else { // document
                        $formattedText = $this->ocrGemini->formatDocumentWithGemini($rawText);
                        // Append to final output
                        $finalOutput .= trim($formattedText) . "\n\n";
                    }

                    // Clean up temp image
                    Storage::disk('public')->delete($path);
                } catch (\Exception $e) {
                    Log::error("Error processing image " . $image->getClientOriginalName() . ": " . $e->getMessage());
                    throw new \Exception("Failed to process image: " . $image->getClientOriginalName() . " - " . $e->getMessage());
                }
            }

            // Trim any extra blank lines and save the final file
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
