<?php
// Nama file ZIP WordPress
$zipFile = 'latest.zip';

// Lokasi ekstrak (folder saat ini)
$extractTo = __DIR__;

// Pastikan file zip ada
if (!file_exists($zipFile)) {
    die("❌ File $zipFile tidak ditemukan!");
}

// Ekstrak ZIP
$zip = new ZipArchive;
if ($zip->open($zipFile) === TRUE) {
    $zip->extractTo($extractTo);
    $zip->close();
    echo "✅ Berhasil diekstrak ke: $extractTo";
} else {
    echo "❌ Gagal membuka file zip.";
}
?>
