<?php

namespace App\Services;

use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Exception;

class OcrGeminiService
{
    protected $vision;
    protected $apiKey;

    public function __construct()
    {
        // Vision client
        $this->vision = new ImageAnnotatorClient([
            'credentials' => storage_path('keys/google-credentials.json')
        ]);
        // Gemini API key
        $this->apiKey = getenv('GEMINI_API_KEY');
    }

    /**
     * Extract text from an image using Vision API
     */
    public function extractText(string $imagePath): string
    {
        try {
            $image = file_get_contents($imagePath);

            $imageObj = new \Google\Cloud\Vision\V1\Image(['content' => $image]);
            $feature = new \Google\Cloud\Vision\V1\Feature([
                'type' => \Google\Cloud\Vision\V1\Feature\Type::DOCUMENT_TEXT_DETECTION
            ]);

            $request = new \Google\Cloud\Vision\V1\AnnotateImageRequest([
                'image' => $imageObj,
                'features' => [$feature]
            ]);

            $batchRequest = new \Google\Cloud\Vision\V1\BatchAnnotateImagesRequest([
                'requests' => [$request]
            ]);

            $response = $this->vision->batchAnnotateImages($batchRequest);
            $textAnnotations = $response->getResponses()[0]->getFullTextAnnotation();

            return $textAnnotations ? $textAnnotations->getText() : '';
        } catch (Exception $e) {
            Log::error('Vision API error: ' . $e->getMessage());
            throw new Exception('Vision API error: ' . $e->getMessage());
        } finally {
            $this->vision->close();
        }
    }

    /**
     * Clean and format extracted OCR text into a table using Gemini
     */
    public function formatTableWithGemini(string $rawText, string $tableColumns = null): string
    {
        // Use the env variable for the URL
        $url = env('GEMINI_API')."?key={$this->apiKey}";

        $maxRetries = 5;
        $retries = 0;
        $delay = 1;

        $columnNames = !empty($tableColumns) ? array_map('trim', explode(',', $tableColumns)) : ['sankhya', 'granth_naam', 'karta'];

        // Build an example JSON array to include in the prompt for explicit schema guidance
        $jsonExample = json_encode([
            [
                'sankhya' => '161',
                'granth_naam' => 'Book Name Example',
                'karta' => 'Author Name Example'
            ],
            [
                'sankhya' => '162',
                'granth_naam' => 'Another Book Name',
                'karta' => ''
            ]
        ], JSON_PRETTY_PRINT);


        $prompt = "
I have a list of books and their authors extracted via OCR. The text is messy and the authors might be on a different line or not present.
Your task is to convert this messy text into a clean, JSON array.

Follow these steps and rules precisely:
1. Parse the OCR text to extract a list of all book entries. An entry is defined by a `संख्या` (a number, e.g., १६१, १६२).
2. For each entry, extract the full `ग्रन्थ-नाम` (the text that follows the `संख्या`).
3. For each `ग्रन्थ-नाम`, search for its corresponding `कर्ता` (author's name) on the same line or any following lines until the next `संख्या`.
4. If a `कर्ता` is found for a specific `ग्रन्थ-नाम`, link them together.
5. If no `कर्ता` is found for an entry, leave the `karta` field as an empty string.
6. The final output **MUST be a clean, parsable JSON array** with the exact keys: `sankhya`, `granth_naam`, and `karta`.

**CRITICAL RULE: The ENTIRE response MUST contain ONLY the JSON array. DO NOT include any introductory text, explanation, or markdown code fences (like ```json).**

Here is the raw OCR text:
{$rawText}

Here is the required JSON structure:
{$jsonExample}
";

        while ($retries < $maxRetries) {
            try {
                // *** FIX: INCREASED TIMEOUT TO 90 SECONDS ***
                $response = Http::timeout(90)->withHeaders(['Content-Type' => 'application/json'])->post($url, [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    // generationConfig block is intentionally omitted to prevent 400 error
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    // Extract the text part which will now be the JSON string
                    $result = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'No response';

                    // The model might still wrap the JSON, so we aggressively strip markdown/text.
                    $cleanedResult = trim(preg_replace('/^```json\s*|(?:\s*```)?$/i', '', $result));

                    $parsedData = json_decode($cleanedResult, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        // Throw a detailed error if JSON decoding fails
                        throw new Exception('Failed to decode JSON from Gemini API: ' . json_last_error_msg() . ' (Raw: ' . $result . ')');
                    }

                    $output = "";
                    foreach ($parsedData as $item) {
                        $row = [];
                        foreach ($columnNames as $name) {
                            $safeName = str_replace(' ', '_', strtolower(trim($name)));
                            $row[] = $item[$safeName] ?? '';
                        }
                        $output .= implode("\t", $row) . "\n";
                    }
                    return trim($output);
                } elseif ($response->status() === 503 || $response->status() === 429) {
                    $retries++;
                    Log::warning("Gemini API returned " . $response->status() . ". Retrying... (Attempt {$retries})");
                    sleep($delay);
                    $delay *= 2;
                    continue;
                } else {
                    Log::error('Gemini API error: ' . $response->body());
                    throw new Exception('Gemini API error: ' . $response->status() . ' - ' . $response->body());
                }
            } catch (Exception $e) {
                $retries++;
                Log::error('Gemini table formatting error: ' . $e->getMessage() . ". Retrying... (Attempt {$retries})");
                sleep($delay);
                $delay *= 2;
            }
        }
        throw new Exception('Failed to get a response from Gemini API after multiple retries.');
    }

    /**
     * Clean and format extracted OCR text into a document using Gemini.
     */
    public function formatDocumentWithGemini(string $rawText, string $customPrompt = null): string
    {
        $url = env('GEMINI_API')."?key={$this->apiKey}";

        $maxRetries = 5;
        $retries = 0;
        $delay = 1;

        while ($retries < $maxRetries) {
            try {
                $prompt = $customPrompt ?? "
The following is text extracted from an image via OCR. It may contain errors,
unnecessary line breaks, and messy formatting. Please clean it up and
present it in a logical, easy-to-read document format. Correct any spelling
or grammatical errors without changing the original content's meaning.
Do not add any new information.

Here is the raw OCR text:
{$rawText}
";
                // *** FIX: INCREASED TIMEOUT TO 90 SECONDS ***
                $response = Http::timeout(90)->withHeaders(['Content-Type' => 'application/json'])->post($url, [
                    'contents' => [['parts' => [['text' => $prompt]]]]
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Could not format document text.';
                } elseif ($response->status() === 503 || $response->status() === 429) {
                    $retries++;
                    Log::warning("Gemini API returned " . $response->status() . ". Retrying... (Attempt {$retries})");
                    sleep($delay);
                    $delay *= 2;
                    continue;
                } else {
                    Log::error('Gemini API document formatting error: ' . $response->body());
                    throw new Exception('Gemini API error: ' . $response->status() . ' - ' . $response->body());
                }
            } catch (Exception $e) {
                $retries++;
                Log::error('Gemini document formatting error: ' . $e->getMessage() . ". Retrying... (Attempt {$retries})");
                sleep($delay);
                $delay *= 2;
            }
        }

        throw new Exception('Failed to get a response from Gemini API after multiple retries.');
    }

    /**
     * Extract and format text from an image in a single API call using Gemini.
     */
    public function fullGeminiOcrAndFormat(string $imagePath, string $outputFormat, string $customPrompt = null, string $tableColumns = null): string
    {
        $url = env('GEMINI_API')."?key={$this->apiKey}";
        $maxRetries = 5;
        $retries = 0;
        $delay = 1;

        $columnNames = !empty($tableColumns) ? array_map('trim', explode(',', $tableColumns)) : ['sankhya', 'granth_naam', 'karta'];

        $jsonExample = json_encode([
            [
                'sankhya' => '161',
                'granth_naam' => 'Book Name Example',
                'karta' => 'Author Name Example'
            ]
        ], JSON_PRETTY_PRINT);

        while ($retries < $maxRetries) {
            try {
                $imageContent = base64_encode(file_get_contents($imagePath));

                if ($outputFormat === 'table') {
                    $prompt = "
I have an image of a list of books and their authors. Your task is to extract the text and convert it into a clean, JSON array with the exact keys: " . implode(', ', $columnNames) . ".

**CRITICAL RULE: The ENTIRE response MUST contain ONLY the JSON array. DO NOT include any introductory text, explanation, or markdown code fences (like ```json).**

Here is the required JSON structure:
{$jsonExample}
";
                    // generationConfig is removed to avoid 400 errors
                    $generationConfig = null;
                } else { // document
                    $prompt = $customPrompt ?? "
I have a messy image with text. Please extract all the text from the image, clean it up, correct any misspellings, and format it into a readable document. Maintain paragraph breaks and line breaks where appropriate. Do not add any new content or summaries.
";
                    $generationConfig = null;
                }

                // *** FIX: INCREASED TIMEOUT TO 90 SECONDS ***
                $response = Http::timeout(90)->withHeaders(['Content-Type' => 'application/json'])->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                                ['inlineData' => [
                                    'mimeType' => mime_content_type($imagePath),
                                    'data' => $imageContent
                                ]]
                            ]
                        ]
                    ],
                    'generationConfig' => $generationConfig,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $result = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

                    if ($outputFormat === 'table') {
                        // Aggressively clean the result before decoding
                        $cleanedResult = trim(preg_replace('/^```json\s*|(?:\s*```)?$/i', '', $result));
                        $parsedData = json_decode($cleanedResult, true);

                        if (json_last_error() !== JSON_ERROR_NONE) {
                            throw new Exception('Failed to decode JSON from Gemini API: ' . json_last_error_msg() . ' (Raw: ' . $result . ')');
                        }

                        $output = implode("\t", $columnNames) . "\n";
                        foreach ($parsedData as $item) {
                            $row = [];
                            foreach ($columnNames as $name) {
                                $safeName = str_replace(' ', '_', strtolower(trim($name)));
                                $row[] = $item[$safeName] ?? '';
                            }
                            $output .= implode("\t", $row) . "\n";
                        }
                        return trim($output);
                    } else {
                        return trim($result);
                    }
                } elseif ($response->status() === 503 || $response->status() === 429) {
                    $retries++;
                    Log::warning("Gemini API returned " . $response->status() . ". Retrying... (Attempt {$retries})");
                    sleep($delay);
                    $delay *= 2;
                    continue;
                } else {
                    Log::error('Gemini API error: ' . $response->body());
                    throw new Exception('Gemini API error: ' . $response->status() . ' - ' . $response->body());
                }
            } catch (Exception $e) {
                $retries++;
                Log::error('Full Gemini OCR error: ' . $e->getMessage() . ". Retrying... (Attempt {$retries})");
                sleep($delay);
                $delay *= 2;
            }
        }
        throw new Exception('Failed to get a response from Gemini API after multiple retries.');
    }

    /**
     * Process a PDF by converting each page to an image and running it through OCR.
     */
    public function processPdf(string $pdfPath, string $ocrEngine, string $outputFormat, ?string $customPrompt = null, ?string $tableColumns = null): string
    {
        $outputDir = storage_path('app/public/temp_images');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $command = "gs -dNOPAUSE -dBATCH -sDEVICE=jpeg -r300 -sOutputFile={$outputDir}/page_%d.jpg {$pdfPath}";

        $output = shell_exec($command);
        Log::info("Ghostscript output: " . $output);

        $totalOutput = '';
        $pageNumber = 1;

        while (file_exists("{$outputDir}/page_{$pageNumber}.jpg")) {
            $imagePath = "{$outputDir}/page_{$pageNumber}.jpg";

            try {
                if ($ocrEngine === 'vision_and_gemini') {
                    $rawText = $this->extractText($imagePath);
                    if ($outputFormat === 'table') {
                        $totalOutput .= $this->formatTableWithGemini($rawText, $tableColumns);
                    } else {
                        $totalOutput .= $this->formatDocumentWithGemini($rawText, $customPrompt);
                    }
                } elseif ($ocrEngine === 'full_gemini') {
                    $totalOutput .= $this->fullGeminiOcrAndFormat($imagePath, $outputFormat, $customPrompt, $tableColumns);
                } else { // 'raw'
                    $totalOutput .= $this->extractText($imagePath);
                }
            } catch (Exception $e) {
                Log::error("Error processing PDF page {$pageNumber}: " . $e->getMessage());
            }

            unlink($imagePath);
            $pageNumber++;
        }

        return $totalOutput;
    }
}
