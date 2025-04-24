#!/bin/bash

echo "=== Run CBN Circulars Extraction and Download ==="

# Step 1: Extract circulars
echo "Step 1: Running the Extractor..."
php extractor.php

# Check if extraction was successful
# shellcheck disable=SC2181
if [ $? -ne 0 ]; then
    echo "Extraction failed. Aborting."
    exit 1
fi

# Step 2: Download PDFs
echo "Step 2: Running download_circulars.php..."
php downloader.php

# Final status
# shellcheck disable=SC2181
if [ $? -eq 0 ]; then
    echo "Extraction completed and PDFs downloaded successfully."
else
    echo "Download encountered issues."
    exit 1
fi
