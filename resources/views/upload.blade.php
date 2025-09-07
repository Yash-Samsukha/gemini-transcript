<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCR Image Upload</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .drop-zone {
            border: 2px dashed #cbd5e0;
            transition: all 0.3s ease;
        }

        .drop-zone.dragover {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }

        .file-item {
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Image Processor</h1>
            <p class="text-gray-600">Upload multiple images to extract and format text</p>
        </div>

        <!-- Upload Form -->
        <form id="uploadForm" action="/bulk-ocr" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <!-- Drop Zone -->
            <div id="dropZone" class="drop-zone rounded-lg p-8 text-center cursor-pointer hover:bg-gray-50">
                <div class="space-y-4">
                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <div>
                        <p class="text-lg font-medium text-gray-900">Drop images here or click to browse</p>
                        <p class="text-sm text-gray-500">Supports JPG, PNG up to 10MB each</p>
                    </div>
                    <input type="file" id="fileInput" name="images[]" multiple accept="image/*" class="hidden">
                </div>
            </div>

            <!-- OCR Engine Selection -->
            <div class="space-y-2">
                <label for="ocr_engine" class="block text-sm font-medium text-gray-700">Choose OCR Engine:</label>
                <select id="ocr_engine" name="ocr_engine" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                    <option value="vision_and_gemini">Google Vision + Gemini (Two-step)</option>
                    <option value="full_gemini">Full Gemini AI (Single-step)</option>
                </select>
            </div>

            <!-- Format Selection -->
            <div class="space-y-2">
                <label for="output_format" class="block text-sm font-medium text-gray-700">Choose Output Format:</label>
                <select id="output_format" name="output_format" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                    <option value="table">Table (CSV)</option>
                    <option value="document">Document (Plain Text)</option>
                </select>
            </div>

            <!-- File List -->
            <div id="fileList" class="space-y-2 hidden">
                <h3 class="text-lg font-medium text-gray-900">Selected Files:</h3>
                <div id="fileItems" class="space-y-2"></div>
            </div>

            <!-- Progress Bar -->
            <div id="progressContainer" class="hidden">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700">Processing...</span>
                    <span id="progressText" class="text-sm text-gray-500">0%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div id="progressBar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" id="submitBtn" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                Process Images
            </button>
        </form>

        <!-- Results -->
        <div id="results" class="mt-8 hidden">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800">Processing Complete!</h3>
                        <p id="resultText" class="text-sm text-green-700 mt-1">Your file is ready for download.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const fileList = document.getElementById('fileList');
    const fileItems = document.getElementById('fileItems');
    const uploadForm = document.getElementById('uploadForm');
    const submitBtn = document.getElementById('submitBtn');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const results = document.getElementById('results');
    const resultText = document.getElementById('resultText');
    const outputFormat = document.getElementById('output_format');
    const ocrEngine = document.getElementById('ocr_engine');

    let selectedFiles = [];

    // Drag and drop functionality
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        const files = Array.from(e.dataTransfer.files).filter(file => file.type.startsWith('image/'));
        handleFiles(files);
    });

    dropZone.addEventListener('click', () => {
        fileInput.click();
    });

    fileInput.addEventListener('change', (e) => {
        const files = Array.from(e.target.files);
        handleFiles(files);
    });

    function handleFiles(files) {
        selectedFiles = [...selectedFiles, ...files];
        updateFileList();
        updateSubmitButton();
    }

    function updateFileList() {
        if (selectedFiles.length === 0) {
            fileList.classList.add('hidden');
            return;
        }

        fileList.classList.remove('hidden');
        fileItems.innerHTML = '';

        selectedFiles.forEach((file, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg';

            const fileInfo = document.createElement('div');
            fileInfo.className = 'flex items-center space-x-3';

            const fileIcon = document.createElement('div');
            fileIcon.className = 'w-8 h-8 bg-blue-100 rounded flex items-center justify-center';
            fileIcon.innerHTML = '<svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" /></svg>';

            const fileDetails = document.createElement('div');
            fileDetails.innerHTML = `
                    <p class="text-sm font-medium text-gray-900">${file.name}</p>
                    <p class="text-xs text-gray-500">${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                `;

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'text-red-500 hover:text-red-700';
            removeBtn.innerHTML = '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>';
            removeBtn.onclick = () => removeFile(index);

            fileInfo.appendChild(fileIcon);
            fileInfo.appendChild(fileDetails);
            fileItem.appendChild(fileInfo);
            fileItem.appendChild(removeBtn);
            fileItems.appendChild(fileItem);
        });
    }

    function removeFile(index) {
        selectedFiles.splice(index, 1);
        updateFileList();
        updateSubmitButton();
    }

    function updateSubmitButton() {
        submitBtn.disabled = selectedFiles.length === 0;
        submitBtn.textContent = selectedFiles.length === 0 ? 'Select Files First' : `Process ${selectedFiles.length} Image${selectedFiles.length !== 1 ? 's' : ''}`;
    }

    // Form submission
    uploadForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (selectedFiles.length === 0) return;

        // Show progress
        progressContainer.classList.remove('hidden');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Processing...';

        // Create FormData
        const formData = new FormData();
        selectedFiles.forEach(file => {
            formData.append('images[]', file);
        });

        // Add the OCR engine and output format to the form data
        formData.append('ocr_engine', ocrEngine.value);
        formData.append('output_format', outputFormat.value);

        try {
            const response = await fetch('/bulk-ocr', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                }
            });

            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;

                // Set the correct file name based on the selected format
                a.download = outputFormat.value === 'table' ? 'ocr_results.csv' : 'ocr_results.txt';

                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);

                // Show success
                progressContainer.classList.add('hidden');
                results.classList.remove('hidden');
                resultText.textContent = `Your ${outputFormat.value === 'table' ? 'CSV' : 'text'} file is ready for download.`;

                // Reset form
                selectedFiles = [];
                updateFileList();
                updateSubmitButton();
            } else {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Server error');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while processing the images: ' + error.message);

            // Reset UI
            progressContainer.classList.add('hidden');
            submitBtn.disabled = false;
            updateSubmitButton();
        }
    });

    // Initialize
    updateSubmitButton();
</script>
</body>

</html>
