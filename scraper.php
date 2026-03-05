<?php
/**
 * Scraper for hkfm903.live - PHP Version
 */

$BASE_URL = "https://hkfm903.live/";

// Debug Connectivity
if (isset($_GET['debug'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    header("Content-Type: text/plain");
    echo "903 Debug Mode\n";
    echo "--------------\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'ON' : 'OFF') . "\n";
    echo "Directory writable: " . (is_writable(__DIR__) ? 'YES' : 'NO') . "\n";

    $test_file = __DIR__ . '/test_write.txt';
    if (@file_put_contents($test_file, "test")) {
        echo "Test write: SUCCESS\n";
        unlink($test_file);
    } else {
        echo "Test write: FAILED\n";
    }

    $check_url = $BASE_URL;
    $headers = @get_headers($check_url);
    echo "Remote site access: " . ($headers ? 'SUCCESS' : 'FAILED') . "\n";

    // Test Scraping Logic in Debug
    echo "\nScraping Test:\n";
    $show = "Bad Girl大過佬";
    $url = $BASE_URL . "?show=" . urlencode($show);
    $options = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n",
            "timeout" => 10
        ]
    ];
    $context = stream_context_create($options);
    $html = @file_get_contents($url, false, $context);

    if ($html) {
        echo "Fetched HTML Length: " . strlen($html) . " bytes\n";
        // Using DOMDocument instead of Regex for robust parsing
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        $cards = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' recording-card ')]");

        echo "Recording cards found: " . $cards->length . "\n";

        if ($cards->length === 0) {
            echo "DEBUG: DOM query failed to find cards. HTML snippet:\n";
            echo substr(htmlspecialchars($html), 0, 1000) . "...\n";
        }
    } else {
        echo "Failed to fetch HTML content.\n";
    }

    exit;
}

function get_show_recordings($show_name)
{
    global $BASE_URL;
    $url = $BASE_URL . "?show=" . urlencode($show_name);

    $options = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n",
            "timeout" => 10
        ]
    ];
    $context = stream_context_create($options);

    $html = @file_get_contents($url, false, $context);
    if ($html === false) {
        error_log("Failed to fetch page: $url");
        return [];
    }

    $recordings = [];

    // Using DOMDocument to find recording cards and their metadata
    // This is more robust against HTML structure changes than regex
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);

    $cards = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' recording-card ')]");

    foreach ($cards as $card) {
        $titleNode = $xpath->query(".//h5[contains(@class, 'card-title')]", $card)->item(0);
        $sourceNode = $xpath->query(".//source", $card)->item(0);

        if ($titleNode && $sourceNode) {
            $title = trim(strip_tags($titleNode->nodeValue));
            $src = $sourceNode->getAttribute('src');

            if (strpos($src, 'http') !== 0) {
                $src = rtrim($BASE_URL, '/') . '/' . ltrim($src, '/');
            }

            $recordings[] = [
                "title" => $title,
                "url" => $src
            ];
        }
    }

    return $recordings;
}

// Helper to prevent overlapping syncs (simple file lock)
$lock_file = __DIR__ . '/.sync.lock';
$json_file = __DIR__ . '/recordings.json';

function update_recordings($show_names = ["Bad Girl大過佬", "在晴朗的一天出發", "聖艾粒LaLaLaLa"])
{
    global $lock_file, $json_file;

    // Check if sync is already running
    if (file_exists($lock_file) && (time() - filemtime($lock_file) < 300)) {
        return []; // Skip if sync started less than 5 minutes ago
    }

    if (!@touch($lock_file)) {
        error_log("903 Scraper: Failed to create lock file at $lock_file. Check directory permissions.");
        return [];
    }

    $all_recs = [];
    foreach ($show_names as $show_name) {
        $recs = get_show_recordings($show_name);
        if (!empty($recs)) {
            $all_recs = array_merge($all_recs, $recs);
        }
    }

    if (!empty($all_recs)) {
        $encoded = json_encode($all_recs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($json_file, $encoded) === false) {
            error_log("903 Scraper: Failed to write recordings to $json_file. Check file/folder permissions.");
        }
    }

    @unlink($lock_file);
    return $all_recs;
}

// Handle Browser Request
if (php_sapi_name() !== 'cli') {
    $is_sync = isset($_GET['sync']) && $_GET['sync'] == '1';

    if ($is_sync) {
        header('Content-Type: application/json');
        $recs = update_recordings();
        echo json_encode($recs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Return immediately to the browser for background sync
    ignore_user_abort(true);
    set_time_limit(300); // 5 minutes max

    header("Content-Length: 0");
    header("Connection: close");
    flush();
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    // Continue in background
    update_recordings();
    exit;
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $shows = ["Bad Girl大過佬", "在晴朗的一天出發", "聖艾粒LaLaLaLa"];
    echo "Scraping remote URLs for multiple shows (PHP Version)...\n";
    $recs = update_recordings($shows);
    if (!empty($recs)) {
        echo "Successfully updated recordings.json with " . count($recs) . " items.\n";
    } else {
        echo "No recordings found or update failed.\n";
    }
}
