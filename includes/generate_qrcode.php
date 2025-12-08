<?php
// includes/generate_qrcode.php
include('../libs/phpqrcode/qrlib.php');

if (isset($_GET['data'])) {
    $data = $_GET['data'];
    
    // Clear any output before this
    ob_clean();
    
    // Output QR code directly
    QRcode::png($data, false, QR_ECLEVEL_H, 10);
    exit;
}