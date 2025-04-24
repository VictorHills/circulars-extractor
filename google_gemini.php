<?php
// Set your API key - you'll need to replace this with your actual Gemini API key
$apiKey = "YOUR_GEMINI_API_KEY";

// API Endpoint for Gemini Pro
$endpoint = "https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent";

// Question to ask
$question = "Who is Donald Trump?";

// Prepare the request data
$data = [
    "contents" => [
        [
            "parts" => [
                [
                    "text" => $question
                ]
            ]
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.7,
        "maxOutputTokens" => 800
    ]
];

// Initialize cURL session
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $endpoint . "?key=" . $apiKey);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

// Execute the request
$response = curl_exec($ch);

// Check for errors
if (curl_errno($ch)) {
    echo "cURL Error: " . curl_error($ch);
    exit;
}

// Close cURL session
curl_close($ch);

// Process and display the response
$result = json_decode($response, true);

// Extract the text from the response
if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
    $answer = $result['candidates'][0]['content']['parts'][0]['text'];
    echo "Question: " . $question . "\n\n";
    echo "Answer from Gemini: \n" . $answer;
} else {
    echo "No valid response received. Response details:";
    print_r($result);
}