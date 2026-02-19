<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if ZipArchive is available
if (!class_exists('ZipArchive')) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Enable ZIP Extension</title>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    </head>
    <body>
        <div class="container mt-5">
            <div class="card">
                <div class="card-header bg-warning">
                    <h3><i class="fas fa-exclamation-triangle"></i> ZipArchive Extension Required</h3>
                </div>
                <div class="card-body">
                    <p class="lead">The PHP ZIP extension is not enabled. Please enable it to generate certificates.</p>
                    
                    <h4 class="mt-4">📋 Quick Fix Instructions:</h4>
                    <div class="alert alert-info">
                        <h5>Step 1: Open php.ini</h5>
                        <ul>
                            <li>Location: <code>C:\xampp\php\php.ini</code></li>
                            <li>Or use XAMPP Control Panel → Apache → Config → php.ini</li>
                        </ul>
                        
                        <h5>Step 2: Find and Edit</h5>
                        <ul>
                            <li>Press <kbd>Ctrl + F</kbd> and search for: <code>;extension=zip</code></li>
                            <li>Remove the semicolon to make it: <code>extension=zip</code></li>
                            <li>Save the file</li>
                        </ul>
                        
                        <h5>Step 3: Restart Apache</h5>
                        <ul>
                            <li>Open XAMPP Control Panel</li>
                            <li>Stop Apache</li>
                            <li>Start Apache again</li>
                        </ul>
                    </div>
                    
                    <h5 class="mt-3">Before:</h5>
                    <pre class="bg-light p-3"><code>;extension=zip</code></pre>
                    
                    <h5>After:</h5>
                    <pre class="bg-success text-white p-3"><code>extension=zip</code></pre>
                    
                    <div class="alert alert-success mt-3">
                        <strong>✓ After enabling:</strong> Refresh this page and the certificate will download automatically!
                    </div>
                    
                    <a href="intern.php" class="btn btn-primary mt-3">← Back to Intern List</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// If we get here, ZipArchive is available
if (!isset($_GET['intern_id'])) {
    die('Intern ID is required');
}

$intern_id = $_GET['intern_id'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Fetch intern data
$query = "SELECT * FROM intern WHERE intern_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $intern_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Intern not found');
}

$intern = $result->fetch_assoc();

// Debug: Check intern data
error_log("=== Certificate Generation Debug ===");
error_log("Intern ID: " . $intern['intern_id']);
error_log("Name: " . $intern['first_name'] . ' ' . $intern['last_name']);
error_log("Gender from DB: '" . $intern['gender'] . "'");
error_log("Gender Type: " . gettype($intern['gender']));

// Prepare data
$full_name = trim($intern['first_name'] . ' ' . ($intern['middle_name'] ? $intern['middle_name'] . ' ' : '') . $intern['last_name']);
$end_date = $intern['end_date'] ? date('F d, Y', strtotime($intern['end_date'])) : date('F d, Y');
$total_hours = $intern['number_of_hours'] ?? $intern['performance_rating'] ?? 500;

// Current date for issue date
$issue_day = date('d');
$issue_suffix = date('S');
$issue_month = date('F');
$issue_year = date('Y');

// Determine title based on gender - FIXED VERSION
$gender = trim($intern['gender'] ?? '');
$title = 'Mr.'; // Default to Mr.

// Case-insensitive gender check
if (strcasecmp($gender, 'female') === 0) {
    $title = 'Ms.';
} elseif (strcasecmp($gender, 'male') === 0) {
    $title = 'Mr.';
}

// For debugging, also check the actual value
error_log("Gender after trim: '" . $gender . "'");
error_log("Gender lowercase: '" . strtolower($gender) . "'");
error_log("Determined Title: " . $title);
error_log("Full Name: " . $full_name);

// Template path - normalize path separators for Windows
$template_path = str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/../public/templates/CERTIFICATE_OF_COMPLETION_TEMPLATE.docx');
$template_path = realpath($template_path);

if (!file_exists($template_path)) {
    die('Certificate template not found. Please place CERTIFICATE_OF_COMPLETION_TEMPLATE.docx in the /templates/ folder.<br>Looking for: ' . $template_path);
}

// Output filename
$filename = 'Certificate_' . str_replace(' ', '_', $full_name) . '_' . date('Ymd') . '.docx';
$output_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid() . '_' . $filename;

// Copy template
if (!copy($template_path, $output_path)) {
    die('Error copying template file');
}

// Open the .docx file (which is actually a ZIP file)
$zip = new ZipArchive();

if ($zip->open($output_path) === TRUE) {
    // Read document.xml which contains the main content
    $document_xml = $zip->getFromName('word/document.xml');
    
    if ($document_xml === false) {
        $zip->close();
        @unlink($output_path);
        die('Error reading template content');
    }
    
    // Debug: Save original XML for inspection
    $debug_dir = __DIR__ . '/../debug/';
    if (!file_exists($debug_dir)) {
        mkdir($debug_dir, 0777, true);
    }
    file_put_contents($debug_dir . 'original_document.xml', $document_xml);
    
    // Create an array of all possible variations of the Ms/Mr placeholder
    $title_placeholders = [
        'Ms/Mr.',
        'Ms/Mr',
        'Ms./Mr.',
        'Ms./Mr',
        'Ms. / Mr.',
        'Ms / Mr',
        'Ms./ Mr.',
        'Ms/ Mr.',
        'Ms/ Mr. ',
        'Ms./Mr. ',
    ];
    
    // First, replace all standard placeholders
    $replacements = [
        '{FULL_NAME}' => htmlspecialchars($full_name, ENT_XML1, 'UTF-8'),
        'Course' => htmlspecialchars($intern['course'], ENT_XML1, 'UTF-8'),
        '{YEAR-LEVEL}' => htmlspecialchars($intern['year_level'], ENT_XML1, 'UTF-8'),
        '{SCHOOL}' => htmlspecialchars($intern['school'], ENT_XML1, 'UTF-8'),
        '{HOURS}' => htmlspecialchars($total_hours, ENT_XML1, 'UTF-8'),
        'End Date' => htmlspecialchars($end_date, ENT_XML1, 'UTF-8'),
        '{TITLE}' => htmlspecialchars($title, ENT_XML1, 'UTF-8'),
        '{LAST_NAME}' => htmlspecialchars($intern['last_name'], ENT_XML1, 'UTF-8'),
        // Replace the date components
        '>02<' => '>' . htmlspecialchars($issue_day, ENT_XML1, 'UTF-8') . '<',
        '>th<' => '>' . htmlspecialchars($issue_suffix, ENT_XML1, 'UTF-8') . '<',
        '>February<' => '>' . htmlspecialchars($issue_month, ENT_XML1, 'UTF-8') . '<',
        '>2026<' => '>' . htmlspecialchars($issue_year, ENT_XML1, 'UTF-8') . '<',
    ];
    
    // Apply all standard replacements
    foreach ($replacements as $search => $replace) {
        $document_xml = str_replace($search, $replace, $document_xml);
    }
    
    // SPECIAL HANDLING FOR GENDER TITLE - MULTIPLE METHODS
    error_log("Attempting to replace Ms/Mr. with: " . $title);
    
    // Method 1: Direct replacement for common patterns
    foreach ($title_placeholders as $placeholder) {
        if (strpos($document_xml, $placeholder) !== false) {
            error_log("Found placeholder in XML: " . $placeholder);
            $document_xml = str_replace($placeholder, $title, $document_xml);
        }
    }
    
    // Method 2: Regex replacement for any Ms/Mr variation
    // This handles cases like "Ms/Mr." or "Ms / Mr" etc.
    $document_xml = preg_replace('/Ms\.?\s*\/\s*Mr\.?/i', $title, $document_xml);
    
    // Method 3: Handle the specific pattern from your template
    // The template says: "Ms/Mr. Lastname of Intern"
    // So we need to replace "Ms/Mr." followed by space and last name
    $last_name_placeholder = $intern['last_name'];
    $document_xml = preg_replace('/Ms\.?\s*\/\s*Mr\.?\s*' . preg_quote($last_name_placeholder, '/') . '/i', 
                                 $title . ' ' . $last_name_placeholder, 
                                 $document_xml);
    
    // Method 4: Also try replacing just the placeholder followed by any text
    $document_xml = preg_replace('/Ms\.?\s*\/\s*Mr\.?\s*Lastname/i', 
                                 $title . ' ' . htmlspecialchars($intern['last_name'], ENT_XML1, 'UTF-8'), 
                                 $document_xml);
    
    // Method 5: Additional check for split XML tags (common in Word documents)
    // Pattern for <w:t>Ms/</w:t><w:t>Mr.</w:t> or similar
    $document_xml = preg_replace_callback(
        '/(<w:t[^>]*>)(Ms\.?\s*\/?\s*)(<\/w:t>)(.*?)(<w:t[^>]*>)(Mr\.?\s*)(<\/w:t>)/i',
        function($matches) use ($title) {
            error_log("Found split XML pattern for Ms/Mr");
            // Replace with the title in the first tag, remove the second
            return $matches[1] . $title . $matches[3] . $matches[4] . $matches[5] . $matches[7];
        },
        $document_xml
    );
    
    // Final safety check: If we still see any Ms/Mr pattern, replace it
    if (preg_match('/Ms\.?\s*\/\s*Mr\.?/i', $document_xml)) {
        error_log("Warning: Ms/Mr pattern still found after all replacements");
        $document_xml = preg_replace('/Ms\.?\s*\/\s*Mr\.?/i', $title, $document_xml);
    }
    
    // Debug: Save modified XML
    file_put_contents($debug_dir . 'modified_document.xml', $document_xml);
    
    // Update the document.xml in the archive
    if (!$zip->deleteName('word/document.xml')) {
        $zip->close();
        @unlink($output_path);
        die('Error updating template');
    }
    
    if (!$zip->addFromString('word/document.xml', $document_xml)) {
        $zip->close();
        @unlink($output_path);
        die('Error saving changes to template');
    }
    
    $zip->close();
    
    // Verify file was created successfully
    if (!file_exists($output_path) || filesize($output_path) == 0) {
        die('Error: Generated file is invalid');
    }
    
    // Send file to browser
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($output_path));
    
    // Clear output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    flush();
    
    // Output file
    readfile($output_path);
    
    // Delete temporary file
    @unlink($output_path);
    
    // Log success
    error_log("Certificate generated successfully for: " . $full_name . " (Gender: " . $gender . ", Title: " . $title . ")");
    error_log("=== End Certificate Generation ===");
    exit();
    
} else {
    @unlink($output_path);
    die('Error opening template file. Please ensure the template is a valid .docx file.');
}