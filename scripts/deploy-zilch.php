<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("This script only accepts POST requests.");
}

if (!isset($_POST['relativePath']) || empty($_POST['relativePath'])) {
    http_response_code(400); // Bad Request
    die("Missing or invalid 'relativePath' parameter.");
}

$relativePath = filter_var($_POST['relativePath'], FILTER_SANITIZE_URL);

if (!$relativePath || strpos($relativePath, '..') !== false || strpos($relativePath, '/') !== 0) {
    http_response_code(400); // Bad Request
    die("Invalid relative path format.");
}

$subdomain = "cms";
$host = $_SERVER['SERVER_NAME'];

$downloadUrl = "https://$subdomain.$host" . $relativePath;

$baseDir = __DIR__;
$directory = realpath($baseDir);

if (!$directory || strpos($directory, realpath($baseDir)) !== 0) {
    http_response_code(403);
    die("Access to the specified path is not allowed.");
}

// Verify the directory exists
if (!is_dir($directory)) {
    http_response_code(404); // Not Found
    die("The specified directory does not exist: $baseDir.");
}

// Clear the target directory
$files = array_diff(scandir($directory), ['.', '..']);
foreach ($files as $file) {
    $filePath = $directory . DIRECTORY_SEPARATOR . $file;
    if (is_file($filePath)) {
        unlink($filePath);
    } elseif (is_dir($filePath)) {
        array_map('unlink', glob("$filePath/*.*"));
        rmdir($filePath);
    }
}

// Download the ZIP file
$tempZipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('download_', true) . '.zip';
$zipResource = fopen($tempZipPath, 'w');
$ch = curl_init($downloadUrl);
curl_setopt($ch, CURLOPT_FILE, $zipResource);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_exec($ch);

if (curl_errno($ch)) {
    unlink($tempZipPath);
    http_response_code(500); // Internal Server Error
    die("Error downloading the ZIP file: " . curl_error($ch));
}

curl_close($ch);
fclose($zipResource);

// Unzip the downloaded file
$zip = new ZipArchive;
if ($zip->open($tempZipPath) === true) {
    $zip->extractTo($directory);
    $zip->close();
    unlink($tempZipPath); // Delete the temporary ZIP file
} else {
    unlink($tempZipPath);
    http_response_code(500); // Internal Server Error
    die("Failed to unzip the downloaded file.");
}

echo json_encode([
    'status' => 'success',
    'message' => "ZIP file downloaded and extracted successfully.",
    'directory' => $directory,
    'downloadUrl' => $downloadUrl
]);
?>