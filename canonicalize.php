<?php

require_once __DIR__ . '/FixedJsonCanonicalizer.php';

// Get file path from command line argument
$filePath = $argv[1] ?? null;

if (!$filePath) {
    fwrite(STDERR, "Usage: php canonicalize.php <json-file>\n");
    exit(1);
}

try {
    // Read JSON file
    $jsonContent = file_get_contents($filePath);
    if ($jsonContent === false) {
        throw new Exception("Could not read file: $filePath");
    }

    // Parse JSON (using objects to preserve empty {} vs [])
    $data = json_decode($jsonContent);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON: " . json_last_error_msg());
    }

    // Canonicalize the JSON using fixed canonicalizer
    $canonicalizer = new FixedJsonCanonicalizer();
    $canonical = $canonicalizer->canonicalize($data);

    // Compute SHA256 hash
    $hash = hash('sha256', $canonical);

    // Output results
    echo "=== PHP (FixedJsonCanonicalizer) ===\n";
    echo "Canonical JSON:\n";
    echo $canonical . "\n";
    echo "\n";
    echo "SHA256 Hash:\n";
    echo $hash . "\n";

} catch (Exception $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
