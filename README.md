# 🏛️ CBN Circulars Extractor

This PHP-based tool allows you to **extract circulars (PDFs)** from
the [Central Bank of Nigeria (CBN) Circulars Page](https://www.cbn.gov.ng/Documents/circulars.html), save metadata to a
JSON file, and download all linked PDF documents.

## 📦 Features

- Scrapes all PDF circulars from the CBN circulars page
- Extracts titles, dates (if available), and PDF links
- Saves circular metadata to a `cbn_circulars.json` file
- Downloads PDFs to a local directory
- Renames files to avoid spaces in filenames
- Easy-to-run shell script for automation
[]()
## 🛠 Requirements

- PHP 8.0+
- `file_get_contents()` enabled
- Bash shell (for running `run_circulars.sh`)

> **Note:** You can also run the PHP scripts individually if needed:
> ```bash
> php extractor.php   # Only extracts metadata to JSON
> php downloader.php  # Only downloads the PDF files
> ```
> Or you can also follow the Quick Start below

## 🚀 Quick Start

1. **Clone the Repository**
   ```bash
   git clone https://github.com/your-username/cbn-circulars-scraper.git
   cd cbn-circulars-scraper
   ```

2. **Make Shell Script Executable**
   ```bash
   chmod +x run_circulars.sh
   ```

3. **Run the Scraper**
   ```bash
   ./run_circulars.sh
   ```

This will:

- Extract circular data and save to `cbn_circulars.json`
- Download all PDFs to `pdf_downloads/`

## 🧱 Project Structure

```
├── cbn_circulars.json       # Output JSON file
├── exctractor.php    # Script to extract circular data
├── downloader.php   # Script to download PDF files
├── run_circulars.sh         # Shell script to automate the process
└── pdf_downloads/           # Folder containing downloaded PDFs
```

## ✍️ Credits

Victor Chinonso Ugwu

## 📜 License

LOL just kidding