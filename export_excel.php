<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
require_once __DIR__ . '/includes/db.php';

// Fetch submissions from the database
$stmt = $pdo->query("SELECT submissions.*, forms.form_name FROM submissions JOIN forms ON submissions.form_id = forms.id");
$submissions = $stmt->fetchAll();

// Set the filename for the Excel export (.xlsx)
$filename = 'submissions_' . date('Y-m-d_H-i-s') . '.xlsx';

// Set the headers for Excel file download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Open the output stream to write the Excel content
$output = fopen('php://output', 'w');

// Write the header row (field names will be column headers)
fputcsv($output, ['Submission ID', 'Form Name', 'Entry Data', 'User IP', 'User Agent', 'Submitted At']);

// Write the submission data rows
foreach ($submissions as $submission) {
    fputcsv($output, [
        $submission['id'],
        $submission['form_name'],
        $submission['entry_data'],
        $submission['user_ip'],
        $submission['user_agent'],
        $submission['submitted_at'],
    ]);
}

// Close the output stream
fclose($output);
exit;
?>
