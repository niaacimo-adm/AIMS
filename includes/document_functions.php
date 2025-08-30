<?php
function generateDocumentTemplate($document_details) {
    header("Content-type: application/vnd.ms-word");
    header("Content-Disposition: attachment;Filename=".$document_details['doc_number'].".doc");
    
    // Convert images to base64 for reliable embedding in Word
    function imgToBase64($path) {
        if (file_exists($path)) {
            $type = pathinfo($path, PATHINFO_EXTENSION);
            $data = file_get_contents($path);
            return 'data:image/'.$type.';base64,'.base64_encode($data);
        }
        return '';
    }
    
    // Convert logos to base64
    $bpLogo = imgToBase64('../dist/img/3.png'); // Bagong Pilipinas Logo
    $opLogo = imgToBase64('../dist/img/1.png'); // Office of the President Logo
    $niaLogo = imgToBase64('../dist/img/2.png'); // NIA Logo
    
    // Convert QR code to base64 if it exists
    $qrCodePath = '../uploads/qrcodes/'.basename($document_details['qr_code']);
    $qrCode = imgToBase64($qrCodePath);
    
    $html = '
    <html xmlns:v="urn:schemas-microsoft-com:vml"
          xmlns:o="urn:schemas-microsoft-com:office:office"
          xmlns:w="urn:schemas-microsoft-com:office:word"
          xmlns="http://www.w3.org/TR/REC-html40">
    <head>
    <style>
        /* Main body styling */
        body {
            width: 21.59cm;
            height: 10cm;
            margin: 0;
            padding: 0.5cm;
            font-family: Arial;
            line-height: 1.3;
            font-size: 10pt;
        }
        
        /* Page size - important for Word */
        @page {
            size: 21.59cm 10cm;
            margin: 0.5cm;
            mso-page-orientation: landscape;
        }
        
        /* Header section */
        .header { 
            text-align: center; 
            margin-bottom: 0.3cm; 
        }
        
        /* Logo container */
        .logo-container { 
            display: table;
            width: 100%;
            margin-bottom: 0.2cm;
        }
        
        /* Logo styling with absolute dimensions */
        .logo {
            width: 2.39cm !important;
            height: 2.13cm !important;
            mso-width-percent: 0;
            mso-height-percent: 0;
            mso-position-horizontal: center;
        }
        
        /* Agency name */
        .agency-name { 
            font-weight: bold; 
            font-size: 10pt; 
            margin-bottom: 0.1cm; 
        }
        
        /* Document info styling */
        .label { 
            font-weight: bold; 
            font-size: 9pt;
            display: inline-block;
            width: 3.5cm;
        }
        
        .value {
            font-size: 9pt;
        }
        
        /* Other existing styles... */
    </style>
    <!--[if gte mso 9]>
    <xml>
        <w:WordDocument>
            <w:View>Print</w:View>
            <w:Zoom>100</w:Zoom>
            <w:DoNotOptimizeForBrowser/>
            <w:ValidateAgainstSchemas/>
            <w:SaveIfXMLInvalid>false</w:SaveIfXMLInvalid>
            <w:IgnoreMixedContent>false</w:IgnoreMixedContent>
            <w:AlwaysShowPlaceholderText>false</w:AlwaysShowPlaceholderText>
            <w:Compatibility>
                <w:BreakWrappedTables/>
                <w:SnapToGridInCell/>
                <w:WrapTextWithPunct/>
                <w:UseAsianBreakRules/>
                <w:DontGrowAutofit/>
            </w:Compatibility>
        </w:WordDocument>
    </xml>
    <![endif]-->
    </head>
    <body>
        <div class="header">
            <div class="logo-container" align="center">';
    
    // Add logos with VML fallback
    if ($opLogo) {
        $html .= '<!--[if gte vml 1]>
                <v:shape style="width:2.39cm;height:2.13cm" stroked="f">
                </v:shape>
                <![endif]-->
                <![if !vml]>
                <img src="'.$opLogo.'" class="logo" alt="Office of the President Logo" width="90" height="80">
                <![endif]>';
    }
    
    
    if ($niaLogo) {
        $html .= '<!--[if gte vml 1]>
                <v:shape style="width:2.39cm;height:2.13cm" stroked="f">
                </v:shape>
                <![endif]-->
                <![if !vml]>
                <img src="'.$niaLogo.'" class="logo" alt="NIA Logo" width="90" height="80">
                <![endif]>';
    }
    if ($bpLogo) {
        $html .= '<!--[if gte vml 1]>
                <v:shape style="width:2.39cm;height:2.13cm" stroked="f">
                </v:shape>
                <![endif]-->
                <![if !vml]>
                <img src="'.$bpLogo.'" class="logo" alt="Bagong Pilipinas Logo" width="90" height="80">
                <![endif]>';
    }
    

    
    $html .= '
            </div>
            <div class="agency-name">BAGONG PILIPINAS</div>
        </div>
        
        <div class="document-info">
            <div class="info-row">
                <span class="label">DOCUMENT TITLE:</span> <span class="value">'.htmlspecialchars($document_details['title']).'</span>
            </div>
            <div class="info-row">
                <span class="label">DOCUMENT TYPE:</span> <span class="value">'.htmlspecialchars($document_details['type_name']).'</span>
            </div>
            <div class="info-row">
                <span class="label">DOCUMENT NUMBER:</span> <span class="value">'.htmlspecialchars($document_details['doc_number']).'</span>
            </div>
            <div class="info-row">
                <span class="label">CREATED:</span> <span class="value">'.date('M d, Y H:i', strtotime($document_details['created_at'])).'</span>
            </div>
            <div class="info-row">
                <span class="label">LAST UPDATE:</span> <span class="value">'.date('M d, Y H:i', strtotime($document_details['updated_at'])).'</span>
            </div>
        </div>
        
        <div class="info-row">
            <span class="label">Processed By:</span> <span class="value">'.($document_details['processed_by_name'] ?? 'N/A').' ('.($document_details['processed_by_section'] ?? 'N/A').')</span>
        </div>
        <div class="info-row">
            <span class="label">Processed At:</span> <span class="value">'.($document_details['processed_at'] ? date('M d, Y H:i', strtotime($document_details['processed_at'])) : 'N/A').'</span>
        </div>
        <div class="info-row">
            <span class="label">Status:</span> <span class="value">'.($document_details['transfer_status'] ?? 'N/A').'</span>
        </div>
        
        <div class="remarks">'.nl2br(htmlspecialchars($document_details['remarks'] ?? 'No remarks')).'</div>
        
        <div class="signature">
            _________________________<br>
            <strong>ENG.R. MARK CLOYD G. SO</strong><br>
            Acting Division Manager
        </div>
        
        <div class="qrcode-container">';
    
    if ($qrCode) {
        $html .= '<img src="'.$qrCode.'" class="qrcode-img" alt="QR Code">';
    } else {
        $html .= '<div>[QR Code Image Not Available]</div>';
    }
    
    $html .= '
            <div>'.htmlspecialchars($document_details['doc_number']).'</div>
        </div>
    </body>
    </html>
    ';
    
    echo $html;
    exit;
}
?>