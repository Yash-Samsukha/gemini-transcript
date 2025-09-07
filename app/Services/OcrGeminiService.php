<?php

namespace App\Services;

use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

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

        $this->vision->close();
        return $textAnnotations ? $textAnnotations->getText() : '';
    }

    /**
     * Clean and format extracted OCR text into a table using Gemini
     */
    public function formatTableWithGemini(string $rawText): string
    {
        try {
            $prompt = "
I have a list of books and their authors extracted via OCR. The text is messy and the authors might be on a different line or not present.
Your task is to convert this messy text into a clean, JSON array.

Follow these steps and rules precisely:
1. Parse the OCR text to extract a list of all book entries. An entry is defined by a `संख्या` (a number, e.g., १६१, १६२).
2. For each entry, extract the full `ग्रन्थ-नाम` (the text that follows the `संख्या`).
3. For each `ग्रन्थ-नाम`, search for its corresponding `कर्ता` (author's name) on the same line or any following lines until the next `संख्या`.
4. If a `कर्ता` is found for a specific `ग्रन्थ-नाम`, link them together.
5. If no `कर्ता` is found for an entry, leave the `karta` field as an empty string.
6. The final output must be a clean, JSON array where each object has three keys: `sankhya`, `granth_naam`, and `karta`.

Here is the raw OCR text:
{$rawText}
";

            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$this->apiKey}";

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => [
                        'type' => 'ARRAY',
                        'items' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'sankhya' => ['type' => 'STRING'],
                                'granth_naam' => ['type' => 'STRING'],
                                'karta' => ['type' => 'STRING'],
                            ],
                            'required' => ['sankhya', 'granth_naam', 'karta'],
                            'propertyOrdering' => ['sankhya', 'granth_naam', 'karta']
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $result = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'No response';

                // Decode the JSON and format it into a tab-separated string
                $parsedData = json_decode($result, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Failed to decode JSON from Gemini API: ' . json_last_error_msg());
                }

                // Arrays for different numeral systems
                $devanagariNumerals = ['०', '१', '२', '३', '४', '५', '६', '७', '८', '९'];
                $gujaratiNumerals   = ['૦', '૧', '૨', '૩', '૪', '૫', '૬', '૭', '૮', '૯'];
                $arabicNumerals     = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

                $output = "";
                foreach ($parsedData as $item) {
                    // Combine all numeral arrays for conversion
                    $sankhya = str_replace(
                        array_merge($devanagariNumerals, $gujaratiNumerals),
                        $arabicNumerals,
                        $item['sankhya']
                    );
                    $output .= "{$sankhya}\t{$item['granth_naam']}\t{$item['karta']}\n";
                }

                return trim($output);
            } else {
                Log::error('Gemini API error: ' . $response->body());
                throw new \Exception('Gemini API error: ' . $response->status() . ' - ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Gemini table formatting error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Clean and format extracted OCR text into a document using Gemini.
     */
    public function formatDocumentWithGemini(string $rawText): string
    {
        try {
            $prompt = "
            The following is text extracted from an image via OCR. It may contain errors,
            unnecessary line breaks, and messy formatting. Please clean it up and
            present it in a logical, easy-to-read document format. Correct any spelling
            or grammatical errors without changing the original content's meaning.
            Do not add any new information.

            Here is the raw OCR text:
            {$rawText}
            ";

            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$this->apiKey}";

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Could not format document text.';
            } else {
                Log::error('Gemini API document formatting error: ' . $response->body());
                throw new \Exception('Gemini API error: ' . $response->status() . ' - ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Gemini document formatting error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract and format text from an image in a single API call using Gemini.
     */
    public function fullGeminiOcrAndFormat(string $imagePath, string $outputFormat): string
    {
        try {
            $imageContent = base64_encode(file_get_contents($imagePath));

            if ($outputFormat === 'table') {
                $prompt = "
I have an image of a list of books and their authors. The text is messy and the authors might be on a different line or not present.
Your task is to extract the text from the image and convert it into a clean, JSON array.

Follow these steps and rules precisely:
1. Parse the text from the image to extract a list of all book entries. An entry is defined by a `संख्या` (a number, e.g., १६१, १६२).
2. For each entry, extract the full `ग्रन्थ-नाम` (the text that follows the `संख्या`).
3. For each `ग्रन्थ-नाम`, search for its corresponding `कर्ता` (author's name) on the same line or any following lines until the next `संख्या`.
4. If a `कर्ता` is found for a specific `ग्रन्थ-नाम`, link them together.
5. If no `कर्ता` is found for an entry, leave the `karta` field as an empty string.
6. The final output must be a clean, JSON array where each object has three keys: `sankhya`, `granth_naam`, and `karta`.
";
            } else { // document
                $prompt = "
I have a messy image with text. Please extract all the text from the image, clean it up, correct any misspellings, and format it into a readable document. Maintain paragraph breaks and line breaks where appropriate. Do not add any new content or summaries.
";
            }

            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$this->apiKey}";

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, [
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
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => ($outputFormat === 'table') ? [
                        'type' => 'ARRAY',
                        'items' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'sankhya' => ['type' => 'STRING'],
                                'granth_naam' => ['type' => 'STRING'],
                                'karta' => ['type' => 'STRING'],
                            ],
                            'required' => ['sankhya', 'granth_naam', 'karta'],
                            'propertyOrdering' => ['sankhya', 'granth_naam', 'karta']
                        ]
                    ] : null
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $result = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

                if ($outputFormat === 'table') {
                    // Decode the JSON and format it into a tab-separated string
                    $parsedData = json_decode($result, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \Exception('Failed to decode JSON from Gemini API: ' . json_last_error_msg());
                    }

                    $devanagariNumerals = ['०', '१', '२', '३', '४', '५', '६', '७', '८', '९'];
                    $gujaratiNumerals   = ['૦', '૧', '૨', '૩', '૪', '૫', '૬', '૭', '૮', '૯'];
                    $arabicNumerals     = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

                    $output = "";
                    foreach ($parsedData as $item) {
                        $sankhya = str_replace(
                            array_merge($devanagariNumerals, $gujaratiNumerals),
                            $arabicNumerals,
                            $item['sankhya']
                        );
                        $output .= "{$sankhya}\t{$item['granth_naam']}\t{$item['karta']}\n";
                    }

                    return trim($output);
                } else { // document
                    return trim($result);
                }
            } else {
                Log::error('Gemini API error: ' . $response->body());
                throw new \Exception('Gemini API error: ' . $response->status() . ' - ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Full Gemini OCR error: ' . $e->getMessage());
            throw $e;
        }
    }
}
