<?php
// setup_phpmailer.php - Run this once to download PHPMailer
echo "<h2>PHPMailer Setup</h2>";

$zip_url = 'https://github.com/PHPMailer/PHPMailer/archive/refs/heads/master.zip';
$zip_file = __DIR__ . '/phpmailer.zip';
$extract_path = __DIR__;

echo "Downloading PHPMailer...<br>";

// Download using file_get_contents if allow_url_fopen is enabled
$zip_content = @file_get_contents($zip_url);

if ($zip_content === false) {
    echo "<span style='color: red;'>❌ Failed to download. Please download manually from:<br>";
    echo "<a href='https://github.com/PHPMailer/PHPMailer/archive/refs/heads/master.zip' target='_blank'>https://github.com/PHPMailer/PHPMailer/archive/refs/heads/master.zip</a></span><br>";
    echo "<br><strong>Manual Steps:</strong><br>";
    echo "1. Download the ZIP file<br>";
    echo "2. Extract it<br>";
    echo "3. Copy 'src' folder to: vendor/phpmailer/phpmailer/src/<br>";
} else {
    file_put_contents($zip_file, $zip_content);
    echo "✅ Downloaded<br>";
    
    // Extract zip (requires ZipArchive)
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zip_file) === TRUE) {
            $zip->extractTo($extract_path);
            $zip->close();
            echo "✅ Extracted<br>";
            
            // Create vendor structure
            if (!is_dir('vendor/phpmailer/phpmailer/src')) {
                mkdir('vendor/phpmailer/phpmailer/src', 0777, true);
            }
            
            // Copy files
            $source = 'PHPMailer-master/src';
            $dest = 'vendor/phpmailer/phpmailer/src';
            
            if (is_dir($source)) {
                copy_recursive($source, $dest);
                echo "✅ Files copied<br>";
                
                // Clean up
                delete_recursive('PHPMailer-master');
                unlink($zip_file);
                echo "✅ Cleanup complete<br>";
                echo "<span style='color: green;'>✅ PHPMailer installed successfully!</span><br>";
            } else {
                echo "<span style='color: red;'>❌ Source folder not found</span><br>";
            }
        } else {
            echo "<span style='color: red;'>❌ Failed to extract zip</span><br>";
        }
    } else {
        echo "<span style='color: orange;'>⚠️ ZipArchive not available. Please extract manually.</span><br>";
    }
}

function copy_recursive($source, $dest) {
    if (!is_dir($dest)) {
        mkdir($dest, 0777, true);
    }
    
    $dir = opendir($source);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $src_file = $source . '/' . $file;
            $dest_file = $dest . '/' . $file;
            
            if (is_dir($src_file)) {
                copy_recursive($src_file, $dest_file);
            } else {
                copy($src_file, $dest_file);
            }
        }
    }
    closedir($dir);
}

function delete_recursive($dir) {
    if (!is_dir($dir)) return;
    
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? delete_recursive($path) : unlink($path);
    }
    rmdir($dir);
}
?>