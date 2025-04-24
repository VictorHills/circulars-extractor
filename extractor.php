<?php
/**
 * CBN Circular Extractor
 * Extracts circulars from the CBN website and stores them as JSON
 */

set_time_limit(600);
ini_set('memory_limit', '256M');

const TARGET_URL = 'https://www.cbn.gov.ng/api/GetAllCirculars';
const JSON_FILE = 'cbn_circulars.json';
const PDF_DIR = 'pdf_downloads/';
const BASE_URL = 'https://www.cbn.gov.ng';

/**
 * Attempt to extract circulars from CBN API
 */
function extractCirculars(): array
{
    echo "Fetching data from API...\n";

    $ch = curl_init(TARGET_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-Requested-With: XMLHttpRequest',
            'Referer: https://www.cbn.gov.ng/Documents/circulars.html'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if (!$response || $httpCode !== 200) {
        echo "API request failed: $error (HTTP $httpCode)\n";
        return [];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON decode error: " . json_last_error_msg() . "\n";
        return [];
    }

    $circulars = [];
    foreach ($data as $item) {
        $pdfLink = trim($item['link'] ?? '');
        if (!$pdfLink) continue;

        $pdfLink = str_starts_with($pdfLink, 'http') ? $pdfLink : BASE_URL . '/' . ltrim($pdfLink, '/');
        $fileName = preg_replace('/\s+/', '_', basename($pdfLink));

        $circulars[] = [
            'title' => trim($item['title'] ?? ''),
            'date' => trim($item['documentDate'] ?? ''),
            'ref_no' => trim($item['refNo'] ?? ''),
            'pdf_url' => $pdfLink,
            'file_size' => trim($item['filesize'] ?? ''),
            'file_name' => $fileName,
            'local_path' => PDF_DIR . $fileName
        ];
    }

    echo "Found " . count($circulars) . " circulars.\n";
    return $circulars;
}

/**
 * Save circulars as JSON
 */
function saveToJson(array $data): bool
{
    echo "Saving data to " . JSON_FILE . "...\n";
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return file_put_contents(JSON_FILE, $json) !== false;
}

/**
 * Ensure the PDF directory exists
 */
function ensurePdfDir(): void
{
    if (!is_dir(PDF_DIR)) {
        mkdir(PDF_DIR, 0755, true);
    }
}

// Main Execution
try {
    $circulars = extractCirculars();
    if (empty($circulars)) {
        echo "No circulars found.\n";
        exit(1);
    }

    ensurePdfDir();

    if (!saveToJson($circulars)) {
        echo "Failed to save JSON data.\n";
        exit(1);
    }

    echo "Extraction complete. Run downloader.php to download the PDFs.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
