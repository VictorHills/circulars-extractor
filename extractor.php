<?php
/**
 * Circular Extractor
 *
 * This script extracts circular information from the CBN website and saves it as JSON
 */

// Set script timeout and memory limits
set_time_limit(600); // 10 minutes
ini_set('memory_limit', '256M');

// Define constants
const TARGET_URL = 'https://www.cbn.gov.ng/api/GetAllCirculars';
const JSON_FILE = 'cbn_circulars.json';
const PDF_DIR = 'pdf_downloads/';
const BASE_URL = 'https://www.cbn.gov.ng';

/**
 * Extract circular information directly from the CBN API endpoint
 *
 * @return array Array of circular data
 */
function extractCirculars(): array
{
    echo "Fetching data from " . TARGET_URL . "...\n";

    // Initialize a cURL session with proper headers to simulate AJAX request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, TARGET_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json, text/javascript, */*; q=0.01',
        'Accept-Language: en-US,en;q=0.9',
        'X-Requested-With: XMLHttpRequest',
        'Content-Type: application/json',
        'Referer: https://www.cbn.gov.ng/Documents/circulars.html'
    ]);

    // Execute cURL session
    $response = curl_exec($ch);

    // Check for errors
    if (curl_errno($ch)) {
        die("cURL error: " . curl_error($ch) . "\n");
    }

    // Get HTTP status code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode != 200) {
        die("HTTP error: " . $httpCode . "\n");
    }

    curl_close($ch);

    if (!$response) {
        die("Failed to fetch content from the API endpoint\n");
    }

    // Save raw API response for debugging
    file_put_contents('debug_api_response.json', $response);
    echo "Saved raw API response to debug_api_response.json for inspection\n";

    // Try to decode JSON response
    $data = json_decode($response, true);

    // Check if JSON decoding succeeded
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON decode error: " . json_last_error_msg() . "\n";
        echo "Attempting to extract data from HTML...\n";

        // Fall back to extracting data from the HTML structure
        return extractCircularsFromHTML();
    }

    // Process the JSON data
    $circulars = [];

    foreach ($data as $item) {
        $title = isset($item['title']) ? trim($item['title']) : '';
        $date = isset($item['documentDate']) ? trim($item['documentDate']) : '';
        $refNo = isset($item['refNo']) ? trim($item['refNo']) : '';
        $pdfLink = isset($item['link']) ? trim($item['link']) : '';
        $fileSize = isset($item['filesize']) ? trim($item['filesize']) : '';

        // Complete relative URLs
        if (!empty($pdfLink) && !str_starts_with($pdfLink, 'http')) {
            $pdfLink = BASE_URL . (str_starts_with($pdfLink, '/') ? '' : '/') . $pdfLink;
        }

        // Generate sanitized PDF filename
        $rawFileName = basename($pdfLink);
        $pdfFileName = preg_replace('/\s+/', '_', $rawFileName);
        $localPdfPath = PDF_DIR . $pdfFileName;

        $circulars[] = [
            'title' => $title,
            'date' => $date,
            'ref_no' => $refNo,
            'pdf_url' => $pdfLink,
            'file_size' => $fileSize,
            'file_name' => $pdfFileName,
            'local_path' => $localPdfPath
        ];
    }

    echo "Found " . count($circulars) . " circulars from API\n";
    return $circulars;
}

/**
 * Fall back method to extract circular information from the HTML page
 *
 * @return array Array of circular data
 */
function extractCircularsFromHTML(): array
{
    echo "Attempting to extract data from HTML page...\n";

    // Get HTML content of the main page
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.cbn.gov.ng/Documents/circulars.html');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

    $html = curl_exec($ch);
    curl_close($ch);

    if (!$html) {
        die("Failed to fetch HTML content\n");
    }

    // Create DOMDocument object
    $dom = new DOMDocument();

    // Suppress warnings for malformed HTML
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    // Create XPath object
    $xpath = new DOMXPath($dom);

    // Extract circular data from the HTML table in the grid
    $circulars = [];

    // Find all table rows in the k-grid-table
    $rows = $xpath->query('//table[contains(@class, "k-grid-table")]//tbody//tr');
    echo "Found " . $rows->length . " rows in the table\n";

    if ($rows->length > 0) {
        foreach ($rows as $row) {
            $cells = $xpath->query('.//td', $row);

            if ($cells->length < 3) {
                continue;
            }

            // Extract data from cells
            $refNo = $cells->length > 0 ? trim($cells->item(0)->textContent) : '';

            // Get a title and PDF link from the second cell
            $titleCell = $cells->length > 1 ? $cells->item(1) : null;
            $title = '';
            $pdfLink = '';

            if ($titleCell) {
                $link = $xpath->query('.//a', $titleCell)->item(0);
                if ($link) {
                    $title = trim($link->textContent);
                    $pdfLink = $link->getAttribute('href');

                    // Complete relative URLs
                    if (!str_starts_with($pdfLink, 'http')) {
                        $pdfLink = BASE_URL . (str_starts_with($pdfLink, '/') ? '' : '/') . $pdfLink;
                    }
                }
            }

            $date = $cells->length > 2 ? trim($cells->item(2)->textContent) : '';
            $fileSize = $cells->length > 3 ? trim($cells->item(3)->textContent) : '';

            // Generate sanitized PDF filename
            $rawFileName = basename($pdfLink);
            $pdfFileName = preg_replace('/\s+/', '_', $rawFileName);
            $localPdfPath = PDF_DIR . $pdfFileName;

            // Only add entries with PDF links
            if (!empty($pdfLink)) {
                $circulars[] = [
                    'title' => $title,
                    'date' => $date,
                    'ref_no' => $refNo,
                    'pdf_url' => $pdfLink,
                    'file_size' => $fileSize,
                    'file_name' => $pdfFileName,
                    'local_path' => $localPdfPath
                ];
            }
        }
    }

    // If no data found in the table, try to extract directly from the HTML you provided
    if (empty($circulars)) {
        echo "Attempting to extract from provided HTML structure...\n";

        // Use the HTML structure you provided
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);

        // Load the sample data you provided
        $sampleData = file_get_contents('paste.txt');
        if ($sampleData) {
            $dom->loadHTML($sampleData);
            $xpath = new DOMXPath($dom);

            $rows = $xpath->query('//tbody[@id="c404aaa8-f69a-4f21-9c39-b4e10f573926"]/tr');
            echo "Found " . $rows->length . " rows in the pasted HTML\n";

            foreach ($rows as $row) {
                $cells = $xpath->query('.//td', $row);

                if ($cells->length < 3) {
                    continue;
                }

                // Extract data from cells
                $refNo = $cells->length > 0 ? trim($cells->item(0)->textContent) : '';

                // Get a title and PDF link from the second cell
                $titleCell = $cells->length > 1 ? $cells->item(1) : null;
                $title = '';
                $pdfLink = '';

                if ($titleCell) {
                    $link = $xpath->query('.//a', $titleCell)->item(0);
                    if ($link) {
                        $title = trim($link->textContent);
                        $pdfLink = $link->getAttribute('href');

                        // Complete relative URLs
                        if (!str_starts_with($pdfLink, 'http')) {
                            $pdfLink = BASE_URL . (str_starts_with($pdfLink, '/') ? '' : '/') . $pdfLink;
                        }
                    }
                }

                $date = $cells->length > 2 ? trim($cells->item(2)->textContent) : '';
                $fileSize = $cells->length > 3 ? trim($cells->item(3)->textContent) : '';

                // Generate sanitized PDF filename
                $rawFileName = basename($pdfLink);
                $pdfFileName = preg_replace('/\s+/', '_', $rawFileName);
                $localPdfPath = PDF_DIR . $pdfFileName;

                // Only add entries with PDF links
                if (!empty($pdfLink)) {
                    $circulars[] = [
                        'title' => $title,
                        'date' => $date,
                        'ref_no' => $refNo,
                        'pdf_url' => $pdfLink,
                        'file_size' => $fileSize,
                        'file_name' => $pdfFileName,
                        'local_path' => $localPdfPath
                    ];
                }
            }
        } else {
            echo "Could not find paste.txt file for fallback parsing\n";
        }
    }

    echo "Found " . count($circulars) . " circulars from HTML\n";
    return $circulars;
}

/**
 * Save circular data to a JSON file
 *
 * @param array $data Circular data to save
 * @return bool Success or failure
 */
function saveToJson(array $data): bool
{
    echo "Saving data to " . JSON_FILE . "...\n";

    $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!$jsonData) {
        echo "Error encoding JSON: " . json_last_error_msg() . "\n";
        return false;
    }

    $result = file_put_contents(JSON_FILE, $jsonData);
    if ($result === false) {
        echo "Failed to write to JSON file\n";
        return false;
    }

    echo "Successfully saved data to " . JSON_FILE . "\n";
    return true;
}

// Main execution
try {
    // First, try the API approach
    $circulars = extractCirculars();

    if (empty($circulars)) {
        echo "No circulars found. Please check the API response or HTML structure.\n";
        exit(1);
    }

    // Create the PDF directory if it doesn't exist
    if (!file_exists(PDF_DIR) && !mkdir(PDF_DIR, 0755, true)) {
        echo "Failed to create directory for PDF downloads\n";
        exit(1);
    }

    // Save data to JSON
    if (!saveToJson($circulars)) {
        exit(1);
    }

    echo "Extraction completed successfully!\n";
    echo "Run downloader.php to download the PDF files\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}