<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCR Image Processor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        .drop-zone {
            border: 2px dashed #d1d5db;
            background-color: #f9fafb;
            transition: all 0.3s ease;
        }

        .drop-zone.dragover {
            border-color: #a78bfa;
            background-color: #f5f3ff;
        }

        .file-item {
            background-color: #fff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease;
        }

        .file-item:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background-image: linear-gradient(to right, #8b5cf6, #a78bfa);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
        }

        .btn-primary:hover {
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.6);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-image: linear-gradient(to right, #10b981, #34d399);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }

        .btn-secondary:hover {
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.6);
            transform: translateY(-1px);
        }

        .btn-gemini {
            background-image: linear-gradient(to right, #6d28d9, #9333ea);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(109, 40, 217, 0.4);
        }

        .btn-gemini:hover {
            box-shadow: 0 6px 20px rgba(109, 40, 217, 0.6);
            transform: translateY(-1px);
        }

        .modal {
            background-color: rgba(0, 0, 0, 0.7);
        }

        .modal-content {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        .cropper-view-box,
        .cropper-face {
            outline-color: #ff9933;
        }

        .cropper-line {
            background-color: #ff9933;
        }

        .cropper-point {
            background-color: #ff9933;
        }

        .cropper-crop-box {
            border: 2px solid #ff9933;
        }

        #cropImage {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen font-sans text-gray-800">
<div class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-5xl font-bold text-gray-900 mb-2 tracking-tight">Sacred Texts OCR</h1>
            <p class="text-xl text-gray-600">पवित्र ग्रंथों को डिजिटल रूप दें</p>
        </div>

        <!-- Upload Form -->
        <form id="uploadForm" action="/bulk-ocr" method="POST" enctype="multipart/form-data" class="space-y-8 p-8 bg-white rounded-3xl shadow-xl">
            @csrf

            <!-- Drop Zone and File Input -->
            <div id="dropZone" class="drop-zone rounded-3xl p-12 text-center cursor-pointer transition-all duration-300">
                <div class="space-y-4">
                    <svg class="mx-auto h-16 w-16 text-gray-400 transition-transform duration-300 transform group-hover:scale-110" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <div class="space-y-1">
                        <p class="text-xl font-medium text-gray-900">Drop your sacred texts or images here</p>
                        <p class="text-base text-gray-500">पवित्र ग्रंथ या छवियों को यहां रखें</p>
                    </div>
                    <input type="file" id="fileInput" name="images[]" multiple accept="image/*" class="hidden">
                </div>
            </div>

            <!-- OCR Engine Selection -->
            <div class="space-y-2">
                <label for="ocr_engine" class="block text-sm font-medium text-gray-700">Choose OCR Engine:</label>
                <select id="ocr_engine" name="ocr_engine" class="mt-1 block w-full pl-3 pr-10 py-3 text-base border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 rounded-lg shadow-sm">
                    <option value="vision_and_gemini">Google Vision + Gemini (Two-step)</option>
                    <option value="full_gemini">Full Gemini AI (Single-step)</option>
                    <option value="raw">Raw Text Extraction</option>
                </select>
            </div>

            <!-- Format Selection -->
            <div class="space-y-2">
                <label for="output_format" class="block text-sm font-medium text-gray-700">Choose Output Format:</label>
                <select id="output_format" name="output_format" class="mt-1 block w-full pl-3 pr-10 py-3 text-base border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 rounded-lg shadow-sm">
                    <option value="table">Table (CSV)</option>
                    <option value="document">Document (Plain Text)</option>
                </select>
            </div>

            <!-- File Action Buttons -->
            <div id="fileActionButtons" class="flex flex-col md:flex-row justify-center space-y-4 md:space-y-0 md:space-x-4 hidden">
                <button id="cropAllBtn" type="button" class="btn-primary px-6 py-3 text-white rounded-lg font-semibold transform hover:-translate-y-1">
                    Crop All Images
                </button>
                <button id="proceedBtn" type="button" class="btn-secondary px-6 py-3 text-white rounded-lg font-semibold transform hover:-translate-y-1">
                    Proceed without Cropping
                </button>
            </div>

            <!-- File List -->
            <div id="fileList" class="space-y-4 hidden">
                <h3 class="text-lg font-bold text-gray-900">Selected Files:</h3>
                <div id="fileItems" class="space-y-2"></div>
            </div>

            <!-- Progress Bar -->
            <div id="progressContainer" class="hidden">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700">Processing...</span>
                    <span id="progressText" class="text-sm text-gray-500">0%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div id="progressBar" class="bg-indigo-600 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" id="submitBtn" class="btn-primary w-full py-3 text-white rounded-lg font-semibold transform hover:-translate-y-1 disabled:opacity-50 disabled:cursor-not-allowed hidden">
                Process Images
            </button>
        </form>

        <!-- Results -->
        <div id="results" class="mt-8 hidden">
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-md">
                <div class="flex items-center">
                    <svg class="h-6 w-6 text-green-500 mr-3" viewBox="0 0 24 24" fill="currentColor">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.23a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <h3 class="text-lg font-bold">Processing Complete!</h3>
                        <p id="resultText" class="text-sm">Your file is ready for download.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gemini Summary Section -->
        <div id="geminiSummarySection" class="mt-8 hidden">
            <div class="p-8 bg-white rounded-3xl shadow-xl">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Gemini AI Features</h2>
                <button id="summarizeBtn" type="button" class="btn-gemini w-full py-3 text-white rounded-lg font-semibold transform hover:-translate-y-1">
                    ✨ Summarize OCR Text
                </button>
                <div id="summaryResult" class="mt-6 p-4 bg-gray-100 border border-gray-200 rounded-xl shadow-inner hidden">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Summary:</h3>
                    <p id="summaryText" class="text-gray-700 leading-relaxed"></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cropper Modal -->
<div id="cropModal" class="modal fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
    <div class="modal-content bg-white rounded-xl p-6 w-full md:w-3/4 max-h-[95vh] flex flex-col">
        <h2 id="modalTitle" class="text-2xl font-bold text-gray-900 mb-4">Crop Image (1 of 1)</h2>
        <div class="relative flex-grow overflow-hidden rounded-lg mb-4 bg-gray-100">
            <img id="cropImage" src="" alt="Image to crop">
        </div>
        <p class="text-sm text-gray-500 text-center mb-6">Use mouse wheel to zoom in/out, click and drag to move.</p>
        <div class="flex justify-end space-x-4">
            <button id="cancelCropBtn" type="button" class="px-6 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
                Cancel
            </button>
            <button id="confirmCropBtn" type="button" class="btn-primary px-6 py-2 text-white rounded-lg font-medium">
                Crop and Add
            </button>
        </div>
    </div>
</div>

<!-- Duplicate File Modal -->
<div id="duplicateModal" class="modal fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-xl p-6 w-full md:w-1/3">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Duplicate File Detected</h2>
        <p id="duplicateMessage" class="text-gray-700 mb-4"></p>
        <div class="flex justify-end space-x-4 mt-6">
            <button id="keepBothBtn" type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
                Keep Both
            </button>
            <button id="replaceBtn" type="button" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">
                Replace
            </button>
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

    const fileActionButtons = document.getElementById('fileActionButtons');
    const cropAllBtn = document.getElementById('cropAllBtn');
    const proceedBtn = document.getElementById('proceedBtn');

    const cropModal = document.getElementById('cropModal');
    const modalTitle = document.getElementById('modalTitle');
    const cropImage = document.getElementById('cropImage');
    const cancelCropBtn = document.getElementById('cancelCropBtn');
    const confirmCropBtn = document.getElementById('confirmCropBtn');

    const duplicateModal = document.getElementById('duplicateModal');
    const duplicateMessage = document.getElementById('duplicateMessage');
    const keepBothBtn = document.getElementById('keepBothBtn');
    const replaceBtn = document.getElementById('replaceBtn');

    const geminiSummarySection = document.getElementById('geminiSummarySection');
    const summarizeBtn = document.getElementById('summarizeBtn');
    const summaryResult = document.getElementById('summaryResult');
    const summaryText = document.getElementById('summaryText');

    let fileQueue = [];
    let finalFiles = [];
    let currentFileIndex = 0;
    let cropper = null;
    let ocrTextResult = '';
    let isProcessing = false;
    let progressInterval = null;

    // Centralized processing state management
    function startProcessing(message) {
        isProcessing = true;
        submitBtn.disabled = true;
        submitBtn.textContent = message;
        progressContainer.classList.remove('hidden');

        let progress = 0;
        const progressSpeed = 500 / 100; // Adjust for desired animation speed
        progressInterval = setInterval(() => {
            if (progress < 95) {
                progress += 1;
                progressBar.style.width = `${progress}%`;
                progressText.textContent = `${progress}%`;
            }
        }, progressSpeed);
    }

    function stopProcessing(message) {
        isProcessing = false;
        submitBtn.disabled = false;
        submitBtn.textContent = message;
        clearInterval(progressInterval);
        progressBar.style.width = '100%';
        progressText.textContent = '100%';
        setTimeout(() => {
            progressContainer.classList.add('hidden');
        }, 1000);
    }

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
        handleNewFiles(files);
    });

    dropZone.addEventListener('click', () => {
        fileInput.click();
    });

    fileInput.addEventListener('change', (e) => {
        const files = Array.from(e.target.files);
        handleNewFiles(files);
    });

    function handleNewFiles(files) {
        if (files && files.length > 0) {
            files.forEach(file => {
                const existingFileIndex = fileQueue.findIndex(f => f.name === file.name);
                if (existingFileIndex !== -1) {
                    duplicateModal.classList.remove('hidden');
                    duplicateMessage.textContent = `A file named "${file.name}" already exists. Do you want to replace it or keep both?`;

                    const onReplaceClick = () => {
                        fileQueue[existingFileIndex] = file;
                        duplicateModal.classList.add('hidden');
                        keepBothBtn.removeEventListener('click', onKeepBothClick);
                        replaceBtn.removeEventListener('click', onReplaceClick);
                        updateUI();
                    };

                    const onKeepBothClick = () => {
                        fileQueue.push(file);
                        duplicateModal.classList.add('hidden');
                        keepBothBtn.removeEventListener('click', onKeepBothClick);
                        replaceBtn.removeEventListener('click', onReplaceClick);
                        updateUI();
                    };

                    replaceBtn.addEventListener('click', onReplaceClick);
                    keepBothBtn.addEventListener('click', onKeepBothClick);
                } else {
                    fileQueue.push(file);
                }
            });
            updateUI();
        }
    }

    // User action buttons
    cropAllBtn.addEventListener('click', () => {
        finalFiles = [];
        currentFileIndex = 0;
        startCroppingQueue();
    });

    proceedBtn.addEventListener('click', () => {
        finalFiles = [...fileQueue];
        fileQueue = [];
        updateUI();
        uploadForm.dispatchEvent(new Event('submit'));
    });

    // Cropper modal actions
    cancelCropBtn.addEventListener('click', () => {
        if (cropper) {
            cropper.destroy();
        }
        cropModal.classList.add('hidden');
        fileQueue = [];
        finalFiles = [];
        updateUI();
    });

    confirmCropBtn.addEventListener('click', () => {
        cropper.getCroppedCanvas().toBlob((blob) => {
            const croppedFile = new File([blob], `cropped_${fileQueue[currentFileIndex].name}`, { type: 'image/png' });
            finalFiles.push(croppedFile);
            cropper.destroy();

            currentFileIndex++;
            if (currentFileIndex < fileQueue.length) {
                startCroppingQueue();
            } else {
                cropModal.classList.add('hidden');
                fileQueue = [];
                updateUI();
                uploadForm.dispatchEvent(new Event('submit'));
            }
        }, 'image/png');
    });

    function startCroppingQueue() {
        if (fileQueue.length === 0) return;

        const file = fileQueue[currentFileIndex];
        modalTitle.textContent = `Crop Image (${currentFileIndex + 1} of ${fileQueue.length})`;

        const reader = new FileReader();
        reader.onload = (e) => {
            cropModal.classList.remove('hidden');
            cropImage.src = e.target.result;

            if (cropper) {
                cropper.destroy();
            }

            cropper = new Cropper(cropImage, {
                aspectRatio: 0,
                dragMode: 'move',
                viewMode: 1,       // ✅ ensures the whole image fits in preview
                autoCropArea: 1,
                responsive: true,
                zoomable: true,
                minCropBoxWidth: 10,
                minCropBoxHeight: 10
            });

        };
        reader.readAsDataURL(file);
    }

    // Update UI state
    function updateUI() {
        if (fileQueue.length === 0 && finalFiles.length === 0) {
            fileList.classList.add('hidden');
            fileActionButtons.classList.add('hidden');
            submitBtn.classList.add('hidden');
            results.classList.add('hidden');
            geminiSummarySection.classList.add('hidden');
            return;
        }

        fileList.classList.remove('hidden');
        fileItems.innerHTML = '';

        const filesToDisplay = fileQueue.length > 0 ? fileQueue : finalFiles;

        if (filesToDisplay.length > 0) {
            filesToDisplay.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item flex items-center justify-between p-4 bg-gray-100 rounded-lg transition-shadow duration-300 hover:shadow-md';

                const fileInfo = document.createElement('div');
                fileInfo.className = 'flex items-center space-x-4';

                const fileIcon = document.createElement('div');
                fileIcon.className = 'w-10 h-10 flex items-center justify-center text-indigo-600';
                fileIcon.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                    `;

                const fileDetails = document.createElement('div');
                fileDetails.innerHTML = `
                        <p class="text-sm font-semibold text-gray-900">${file.name}</p>
                        <p class="text-xs text-gray-500">${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                    `;

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'text-red-500 hover:text-red-700 transition-colors duration-200';
                removeBtn.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6L6 18M6 6l12 12"></path>
                        </svg>
                    `;
                removeBtn.onclick = () => removeFile(index);

                fileInfo.appendChild(fileIcon);
                fileInfo.appendChild(fileDetails);
                fileItem.appendChild(fileInfo);
                fileItem.appendChild(removeBtn);
                fileItems.appendChild(fileItem);
            });
        }

        if (fileQueue.length > 0) {
            fileActionButtons.classList.remove('hidden');
            submitBtn.classList.add('hidden');
        } else {
            fileActionButtons.classList.add('hidden');
            if (finalFiles.length > 0) {
                submitBtn.classList.remove('hidden');
            }
        }
    }

    function removeFile(index) {
        const filesToSplice = fileQueue.length > 0 ? fileQueue : finalFiles;
        filesToSplice.splice(index, 1);
        updateUI();
    }

    async function summarizeTextWithGemini(text) {
        startProcessing('Generating Summary...');
        summaryResult.classList.remove('hidden');
        summaryText.textContent = 'Generating...';

        try {
            // Placeholder API call to a Gemini LLM
            // In a real application, this would be a fetch call to a backend endpoint
            // that handles the Gemini API integration.
            const response = await new Promise(resolve => setTimeout(() => {
                resolve({
                    ok: true,
                    json: () => Promise.resolve({ summary: "This is a concise summary of the OCR'd text generated by Gemini AI." })
                });
            }, 2000));

            const data = await response.json();
            if (response.ok) {
                summaryText.textContent = data.summary;
            } else {
                summaryText.textContent = data.error || 'Failed to generate summary.';
            }
        } catch (error) {
            summaryText.textContent = 'An error occurred: ' + error.message;
        } finally {
            stopProcessing('✨ Summarize OCR Text');
        }
    }

    // Form submission
    uploadForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const filesToUpload = finalFiles.length > 0 ? finalFiles : fileQueue;
        if (!filesToUpload || filesToUpload.length === 0) return;

        startProcessing('Processing...');

        // Create FormData
        const formData = new FormData();
        filesToUpload.forEach(file => {
            formData.append('images[]', file);
        });

        // Add the OCR engine and output format to the form data
        const ocrEngineValue = ocrEngine ? ocrEngine.value : null;
        const outputFormatValue = outputFormat ? outputFormat.value : null;
        if (ocrEngineValue) formData.append('ocr_engine', ocrEngineValue);
        if (outputFormatValue) formData.append('output_format', outputFormatValue);

        try {
            const response = await fetch('/bulk-ocr', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;

                // Set the correct file name based on the selected format
                a.download = outputFormatValue === 'table' ? 'ocr_results.csv' : 'ocr_results.txt';

                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);

                // Show success
                results.classList.remove('hidden');
                resultText.textContent = `Your ${outputFormatValue === 'table' ? 'CSV' : 'text'} file is ready for download.`;

                // Show Gemini summary section after successful OCR
                geminiSummarySection.classList.remove('hidden');
                // In a real app, you would get the raw text from the server response
                ocrTextResult = "Placeholder text from OCR process. This is where the actual text would go.";

                // Reset form
                fileQueue = [];
                finalFiles = [];
                updateUI();
            } else {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Server error');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while processing the images: ' + error.message);

        } finally {
            stopProcessing('Process Images');
        }
    });

    summarizeBtn.addEventListener('click', () => {
        if (ocrTextResult) {
            summarizeTextWithGemini(ocrTextResult);
        }
    });

    // Initialize
    updateUI();
</script>
</body>

</html>
