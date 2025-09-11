<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OcrGeminiService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Exception;

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
                'images.*' => 'required|mimes:jpeg,png,jpg,gif,svg,pdf|max:10240',
                'ocr_engine' => 'required|in:vision_and_gemini,full_gemini,raw',
                'output_format' => 'required|in:table,document',
                'prompt_type' => 'nullable|in:default,custom',
                'custom_prompt' => [
                    Rule::requiredIf($request->input('prompt_type') === 'custom' && $request->input('output_format') === 'document'),
                    'string',
                    'nullable',
                    'max:150'
                ],
                'table_columns' => [
                    Rule::requiredIf($request->input('prompt_type') === 'custom' && $request->input('output_format') === 'table'),
                    'string',
                    'nullable',
                ],
            ]);

            $ocrEngine = $request->input('ocr_engine');
            $outputFormat = $request->input('output_format');
            $promptType = $request->input('prompt_type');
            $customPrompt = ($promptType === 'custom' && $outputFormat === 'document') ? $request->input('custom_prompt') : null;
            $tableColumns = ($promptType === 'custom' && $outputFormat === 'table') ? $request->input('table_columns') : null;

            $finalOutput = '';
            $extension = 'txt';

            if ($ocrEngine !== 'raw' && $outputFormat === 'table') {
                $columnHeaders = !empty($tableColumns) ? array_map('trim', explode(',', $tableColumns)) : ['क्रमांक', 'ग्रंथ-नाम', 'कर्ता'];
                $finalOutput = implode("\t", $columnHeaders) . "\n";
                $extension = 'csv';
            }

            foreach ($request->file('images') as $file) {
                try {
                    $path = $file->store('ocr_uploads', 'public');
                    $fullPath = Storage::disk('public')->path($path);

                    if ($file->getClientMimeType() === 'application/pdf') {
                        $processedText = $this->ocrGemini->processPdf($fullPath, $ocrEngine, $outputFormat, $customPrompt, $tableColumns);
                        $finalOutput .= trim($processedText) . "\n\n";
                    } else {
                        if ($ocrEngine === 'vision_and_gemini') {
                            $rawText = $this->ocrGemini->extractText($fullPath);
                            if ($outputFormat === 'table') {
                                $formattedText = $this->ocrGemini->formatTableWithGemini($rawText, $tableColumns);
                            } else {
                                $formattedText = $this->ocrGemini->formatDocumentWithGemini($rawText, $customPrompt);
                            }
                        } elseif ($ocrEngine === 'full_gemini') {
                            $formattedText = $this->ocrGemini->fullGeminiOcrAndFormat($fullPath, $outputFormat, $customPrompt, $tableColumns);
                        } else {
                            $formattedText = $this->ocrGemini->extractText($fullPath);
                            if ($outputFormat === 'table') {
                                $finalOutput = '';
                                Log::warning('Raw extraction selected, but table format requested. Defaulting to raw document format.');
                            }
                        }
                        $finalOutput .= trim($formattedText) . "\n\n";
                    }
                    
                    Storage::disk('public')->delete($path);

                } catch (Exception $e) {
                    Log::error("Error processing file " . $file->getClientOriginalName() . ": " . $e->getMessage());
                    throw new Exception("Failed to process file: " . $file->getClientOriginalName() . " - " . $e->getMessage());
                }
            }

            $finalOutput = trim($finalOutput);
            $filePath = 'ocr_results/output.' . $extension;
            Storage::disk('public')->put($filePath, $finalOutput);

            return response()->download(Storage::disk('public')->path($filePath));
        } catch (Exception $e) {
            Log::error("OCR processing error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}