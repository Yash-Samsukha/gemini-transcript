<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use Illuminate\Http\Request;

class TextController extends Controller
{
    protected $geminiService;


    public function testGemini(GeminiService $gemini)
    {
        $output = $gemini->generateText("Format the OCR text into a CSV table");
        return response()->json(['result' => $output]);
    }
}
