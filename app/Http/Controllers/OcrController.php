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
     * Handle bulk OCR for uploaded images
     */
    public function bulkOcr(Request $request)
    {
        try {
            $request->validate([
                'images.*' => 'required|image|max:10240' // 10MB max
            ]);

            // Define the CSV header
            $header = "क्रमांक\tग्रंथ-नाम\tकर्ता";
            $finalCsv = $header . "\n";

            $count = 1;
            foreach ($request->file('images') as $image) {
                try {

                    Log::info("image count is : " . $count++);
                    // Save temp to public disk
                    $path = $image->store('ocr_uploads', 'public');
                    Log::info("Image saved to: $path");

                    // OCR with Vision API - use the correct storage path
                    $fullPath = Storage::disk('public')->path($path);
                    $rawText = $this->ocrGemini->extractText($fullPath);
                    Log::info("raw text are : ", [$rawText]);
                    Log::info("OCR completed for: " . $image->getClientOriginalName());

                    // Clean/format with Gemini
                    $csv = $this->ocrGemini->formatWithGemini($rawText);
                    Log::info("Gemini formatting completed for: " . $image->getClientOriginalName());

                    // Remove accidental headers if Gemini inserted them again
                    $csv = preg_replace('/^.*क्रमांक.*$/m', '', $csv);

                    // Append the current image's CSV data to the final string, followed by a blank line
                    $finalCsv .= trim($csv) . "\n\n";
                } catch (\Exception $e) {
                    Log::error("Error processing image " . $image->getClientOriginalName() . ": " . $e->getMessage());
                    // Decide whether to throw the exception or log it and continue
                    throw new \Exception("Failed to process image: " . $image->getClientOriginalName() . " - " . $e->getMessage());
                }
            }

            // Trim any extra blank lines from the end
            $finalCsv = trim($finalCsv);

            // Save to file in public disk
            $filePath = 'ocr_results/output.csv';
            Storage::disk('public')->put($filePath, $finalCsv);


            return response()->download(Storage::disk('public')->path($filePath));
        } catch (\Exception $e) {
            Log::error("OCR processing error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
