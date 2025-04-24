<?php
/**
 * Circular PDF Downloader
 *
 * This script downloads PDF files based on the JSON data created by EXTRACT SCRIPT
 */

// Set script timeout and memory limits
set_time_limit(1800); // 30 minutes
ini_set('memory_limit', '512M');

// Define constants
const JSON_FILE = 'cbn_circulars.json';
const PDF_DIR = 'pdf_downloads/';
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';

/**
 * Load circular data from JSON file
 *
 * @return array|null Array of circular data or null on failure
 */
function loadCircularsData() {
    if (!file_exists(JSON_FILE)) {
        echo "JSON file not found: " . JSON_FILE . "\n";
        echo "Please run extract_circulars.php first\n";
        return null;
    }

    $jsonData = file_get_contents(JSON_FILE);
    if (!$jsonData) {
        echo "Failed to read JSON file\n";
        return null;
    }

    $data = json_decode($jsonData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Error decoding JSON: " . json_last_error_msg() . "\n";
        return null;
    }

    return $data;
}

/**
 * Download a PDF file
 *
 * @param string $url URL of the PDF file
 * @param string $savePath Path to save the file
 * @return bool Success or failure
 */
function downloadPdf(string $url, string $savePath): bool
{
    echo "Downloading: $url\n";

    // Initialize cURL session
    $ch = curl_init($url);

    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, USER_AGENT);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5-minute timeout per file
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/pdf,application/octet-stream',
        'Accept-Language: en-US,en;q=0.9',
        'Referer: https://www.cbn.gov.ng/Documents/circulars.html'
    ]);

    // Execute cURL session
    $pdfContent = curl_exec($ch);

    // Check for errors
    if (curl_errno($ch)) {
        echo "cURL error: " . curl_error($ch) . "\n";
        curl_close($ch);
        return false;
    }

    // Check HTTP status code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode != 200) {
        echo "HTTP error: " . $httpCode . "\n";
        curl_close($ch);
        return false;
    }

    // Check content type
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    if (stripos($contentType, 'pdf') === false &&
        stripos($contentType, 'octet-stream') === false &&
        stripos($contentType, 'application') === false) {
        echo "Warning: Content may not be a PDF (Content-Type: $contentType)\n";

        // Check if the first few bytes match the PDF signature
        if (!str_starts_with($pdfContent, '%PDF')) {
            echo "Warning: Content does not appear to be a valid PDF\n";
            // Continue anyway as some servers might not properly set content-type
        }
    }

    // Close cURL session
    curl_close($ch);

    // Save PDF file
    $result = file_put_contents($savePath, $pdfContent);
    if ($result === false) {
        echo "Failed to write file: $savePath\n";
        return false;
    }

    echo "Successfully downloaded to: $savePath (" . round(filesize($savePath) / 1024, 2) . " KB)\n";
    return true;
}

/**
 * Update the JSON file with local file paths and download status
 *
 * @param array $data Updated circular data
 * @return bool Success or failure
 */
function updateJsonFile(array $data): bool
{
    $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!$jsonData) {
        echo "Error encoding JSON: " . json_last_error_msg() . "\n";
        return false;
    }

    $result = file_put_contents(JSON_FILE, $jsonData);
    if ($result === false) {
        echo "Failed to update JSON file\n";
        return false;
    }

    return true;
}

/**
 * Verify if a file is a valid PDF
 *
 * @param string $filePath Path to the PDF file
 * @return bool Whether the file is a valid PDF
 */
function isValidPdf($filePath) {
    if (!file_exists($filePath) || filesize($filePath) < 5) {
        return false;
    }

    $file = fopen($filePath, 'rb');
    if (!$file) {
        return false;
    }

    $signature = fread($file, 4);
    fclose($file);

    return $signature === '%PDF';
}

// Main execution
try {
    echo "Starting PDF download process...\n";

    // Load circular data
    $circulars = loadCircularsData();
    if (!$circulars) {
        exit(1);
    }

    echo "Found " . count($circulars) . " circulars to process\n";

    // Create the PDF directory if it doesn't exist
    if (!file_exists(PDF_DIR) && !mkdir(PDF_DIR, 0755, true)) {
        echo "Failed to create directory for PDF downloads\n";
        exit(1);
    }

    // Download PDFs
    $totalPdfs = count($circulars);
    $successCount = 0;
    $failCount = 0;

    foreach ($circulars as $key => $circular) {
        // Get URL from the circular data
        $pdfUrl = isset($circular['pdf_url']) ? $circular['pdf_url'] : '';

        if (empty($pdfUrl)) {
            echo "Skipping circular without PDF URL: " . (isset($circular['title']) ? $circular['title'] : 'Unknown') . "\n";
            $circulars[$key]['downloaded'] = false;
            $circulars[$key]['download_error'] = "No PDF URL";
            $failCount++;
            continue;
        }

        $fileName = isset($circular['file_name']) ? $circular['file_name'] : basename($pdfUrl);
        $savePath = PDF_DIR . $fileName;

        echo "\nProcessing ({$key}/$totalPdfs): {$fileName}\n";

        // Check if file already exists and is valid
        if (file_exists($savePath) && isValidPdf($savePath)) {
            echo "File already exists and appears valid, skipping\n";
            $circulars[$key]['downloaded'] = true;
            $circulars[$key]['download_time'] = date('Y-m-d H:i:s');
            $successCount++;
            continue;
        }

        // Try to download the PDF
        if (downloadPdf($pdfUrl, $savePath)) {
            // Verify file was downloaded and is valid
            if (file_exists($savePath) && isValidPdf($savePath)) {
                $circulars[$key]['downloaded'] = true;
                $circulars[$key]['download_time'] = date('Y-m-d H:i:s');
                $circulars[$key]['file_size_bytes'] = filesize($savePath);
                $successCount++;
            } else {
                echo "Warning: Downloaded file may not be a valid PDF\n";
                if (file_exists($savePath)) {
                    unlink($savePath); // Delete invalid file
                }
                $circulars[$key]['downloaded'] = false;
                $circulars[$key]['download_error'] = "Invalid PDF";
                $failCount++;
            }
        } else {
            echo "Failed to download\n";
            $circulars[$key]['downloaded'] = false;
            $circulars[$key]['download_error'] = "Download failed";
            $failCount++;
        }

        // Update JSON file periodically
        if ($key % 5 === 0) {
            updateJsonFile($circulars);
        }

        // Sleep to avoid overwhelming the server
        usleep(1000000); // 1 second
    }

    // Final update to JSON file
    updateJsonFile($circulars);

    echo "\nDownload summary:\n";
    echo "  Total circulars: $totalPdfs\n";
    echo "  Successfully downloaded: $successCount\n";
    echo "  Failed: $failCount\n";
    echo "All done!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}