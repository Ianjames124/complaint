<?php
// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get current user from JWT token
$user = getCurrentUser();
if (!$user || !isset($user['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Only allow citizens
if (($user['role'] ?? '') !== 'citizen') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get complaint ID from query parameter
$complaintId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($complaintId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID']);
    exit;
}

try {
    $db = (new Database())->getConnection();
    
    if ($db === null) {
        throw new Exception('Failed to connect to database');
    }
    
    // Get complaint details - ensure it belongs to the logged-in citizen
    $stmt = $db->prepare('
        SELECT c.*, u.full_name as citizen_name, u.email as citizen_email
        FROM complaints c
        JOIN users u ON c.citizen_id = u.id
        WHERE c.id = ? AND c.citizen_id = ?
    ');
    $stmt->execute([$complaintId, $user['id']]);
    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$complaint) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Complaint not found']);
        exit;
    }
    
    // Get department name if assigned
    $departmentName = 'N/A';
    if (!empty($complaint['department_id'])) {
        $stmtDept = $db->prepare('SELECT name FROM departments WHERE id = ?');
        $stmtDept->execute([$complaint['department_id']]);
        $dept = $stmtDept->fetch(PDO::FETCH_ASSOC);
        if ($dept) {
            $departmentName = $dept['name'];
        }
    }
    
    // Create PDF using TCPDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('E-Complaint System');
    $pdf->SetAuthor('E-Complaint System');
    $pdf->SetTitle('Complaint Receipt #' . $complaintId);
    $pdf->SetSubject('Complaint Receipt');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 10, 'COMPLAINT RECEIPT', 0, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Ln(10);
    
    // Receipt details
    $html = '
    <table border="0" cellpadding="5" cellspacing="0" width="100%">
        <tr>
            <td width="40%"><strong>Receipt Number:</strong></td>
            <td width="60%">#' . htmlspecialchars($complaintId) . '</td>
        </tr>
        <tr>
            <td><strong>Date Submitted:</strong></td>
            <td>' . htmlspecialchars(date('F d, Y h:i A', strtotime($complaint['created_at']))) . '</td>
        </tr>
        <tr>
            <td><strong>Citizen Name:</strong></td>
            <td>' . htmlspecialchars($complaint['citizen_name']) . '</td>
        </tr>
        <tr>
            <td><strong>Email:</strong></td>
            <td>' . htmlspecialchars($complaint['citizen_email']) . '</td>
        </tr>
        <tr>
            <td><strong>Complaint Title:</strong></td>
            <td>' . htmlspecialchars($complaint['title']) . '</td>
        </tr>
        <tr>
            <td><strong>Category:</strong></td>
            <td>' . htmlspecialchars($complaint['category']) . '</td>
        </tr>
        <tr>
            <td><strong>Location:</strong></td>
            <td>' . htmlspecialchars($complaint['location']) . '</td>
        </tr>
        <tr>
            <td><strong>Status:</strong></td>
            <td><strong>' . htmlspecialchars($complaint['status']) . '</strong></td>
        </tr>
        <tr>
            <td><strong>Department:</strong></td>
            <td>' . htmlspecialchars($departmentName) . '</td>
        </tr>
        <tr>
            <td colspan="2"><strong>Description:</strong></td>
        </tr>
        <tr>
            <td colspan="2">' . nl2br(htmlspecialchars($complaint['description'])) . '</td>
        </tr>
    </table>
    ';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Footer note
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 10);
    $pdf->Cell(0, 10, 'This is an official receipt from the E-Complaint System.', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Please keep this receipt for your records.', 0, 1, 'C');
    
    // Output PDF
    $filename = 'complaint_receipt_' . $complaintId . '_' . date('YmdHis') . '.pdf';
    $pdf->Output($filename, 'D'); // 'D' = download
    
} catch (Exception $e) {
    error_log('Error in download-receipt.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to generate receipt']);
    exit;
}

