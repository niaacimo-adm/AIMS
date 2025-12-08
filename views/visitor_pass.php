<?php
// visitor_pass.php
require_once '../config/database.php';

if (isset($_GET['id'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM visitor_queue WHERE id = ?");
    $stmt->bind_param("i", $_GET['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $visitor = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Visitor Pass</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .pass { border: 2px solid #333; padding: 20px; max-width: 400px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 20px; }
        .qr-code { text-align: center; margin: 20px 0; }
        .details { margin: 20px 0; }
        .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <?php if (isset($visitor)): ?>
    <div class="pass">
        <div class="header">
            <h2>NIA-ACIMO VISITOR PASS</h2>
            <p>Visitor ID: <?= $visitor['queue_number'] ?></p>
        </div>
        
        <div class="details">
            <p><strong>Name:</strong> <?= htmlspecialchars($visitor['visitor_name']) ?></p>
            <p><strong>Company:</strong> <?= htmlspecialchars($visitor['company']) ?></p>
            <p><strong>Purpose:</strong> <?= htmlspecialchars($visitor['purpose']) ?></p>
            <p><strong>To Visit:</strong> <?= htmlspecialchars($visitor['person_to_visit']) ?></p>
            <p><strong>Time In:</strong> <?= date('g:i A', strtotime($visitor['time_in'])) ?></p>
            <p><strong>Status:</strong> <?= strtoupper($visitor['status']) ?></p>
        </div>
        
        <div class="qr-code">
            <!-- QR Code will be displayed here -->
            <div id="qrcode"></div>
        </div>
        
        <div class="footer">
            <p>Please present this pass at the reception</p>
            <p>Valid for one day only</p>
        </div>
    </div>
    
    <script src="../plugins/qrcodejs/qrcode.min.js"></script>
    <script>
        // Generate QR Code with visitor data
        const visitorData = JSON.stringify({
            id: "<?= $visitor['id'] ?>",
            name: "<?= $visitor['visitor_name'] ?>",
            queue: "<?= $visitor['queue_number'] ?>",
            time: "<?= $visitor['time_in'] ?>"
        });
        
        new QRCode(document.getElementById("qrcode"), {
            text: visitorData,
            width: 150,
            height: 150
        });
    </script>
    
    <?php else: ?>
    <p>Invalid visitor pass</p>
    <?php endif; ?>
</body>
</html>