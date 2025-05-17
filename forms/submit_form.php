<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../vendor/autoload.php'; // PHPMailer autoloader

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Fetch the settings
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch form details
$form_id = $_GET['form_id'] ?? 0;  // Get the form_id from the URL (or default to 0)

$stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
$stmt->execute([$form_id]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);

// Prepare uploads
$uploads_dir = __DIR__ . '/../uploads/';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0755, true);
}

// Handle form fields
$final_data = [];

foreach ($_POST as $key => $value) {
    if (is_array($value)) {
        $value = implode(', ', $value);
    }
    $final_data[$key] = $value;
}

foreach ($_FILES as $key => $file) {
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'];
        if (in_array($ext, $allowed)) {
            $safe_name = uniqid('', true) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploads_dir . $safe_name)) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                $final_data[$key] = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 2) . '/uploads/' . $safe_name;
            } else {
                $final_data[$key] = 'Upload failed';
            }
        } else {
            $final_data[$key] = 'Invalid file type';
        }
    } else {
        $final_data[$key] = '';
    }
}

// Save to submissions table
$entry_data = json_encode($final_data);
$stmt = $pdo->prepare("INSERT INTO submissions (form_id, entry_data, user_ip, user_agent) VALUES (?, ?, ?, ?)");
$stmt->execute([
    $form_id,
    $entry_data,
    $_SERVER['REMOTE_ADDR'] ?? '',
    $_SERVER['HTTP_USER_AGENT'] ?? ''
]);

// Send webhook
if (!empty($form['webhook_url'])) {

    $newdataset = [
        "form" => $form['form_name'],
        "fields" => $final_data,
        "ip" => $_SERVER['REMOTE_ADDR'],
        "date" => date("Y-m-d H:i:s")
    ];

    $payload = json_encode($newdataset);

    $ch = curl_init($form['webhook_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_exec($ch);
    curl_close($ch);
}

// Send webhook 2
if (!empty($form['webhook_url_2'])) {

    $newdataset = [
        "form" => $form['form_name'],
        "fields" => $final_data,
        "ip" => $_SERVER['REMOTE_ADDR'],
        "date" => date("Y-m-d H:i:s")
    ];

    $payload = json_encode($newdataset);

    $ch = curl_init($form['webhook_url_2']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_exec($ch);
    curl_close($ch);
}

// Build email body
$field_rows = '';
foreach ($final_data as $key => $value) {
    $label = ucwords(str_replace('_', ' ', $key));
    $field_rows .= "<tr><td><strong>" . htmlspecialchars($label) . "</strong></td><td>" . nl2br(htmlspecialchars((string)$value)) . "</td></tr>";
}

$field_table = "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";
$field_table .= "<thead><tr style='background-color: #f2f2f2;'><th>Field</th><th>Value</th></tr></thead><tbody>";
$field_table .= $field_rows . "</tbody></table>";

$final_subject = str_replace('{form}', htmlspecialchars((string)$form['form_name']), $settings['email_subject']);
$final_body = str_replace(
    ['{form}', '{fields}', '{ip}', '{date}'],
    [htmlspecialchars((string)$form['form_name']), $field_table, $_SERVER['REMOTE_ADDR'], date('Y-m-d H:i:s')],
    $settings['email_body']
);

// Send email (PHPMailer)
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = $settings['smtp_host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $settings['smtp_username'];
    $mail->Password   = $settings['smtp_password'];
    $mail->SMTPSecure = '';  // Auto-negotiate encryption
    $mail->Port       = $settings['smtp_port'];

    $mail->setFrom($settings['from_email'], 'Form Builder');
    $mail->addAddress($settings['email_to']);
    $mail->isHTML(true);
    $mail->Subject = $final_subject;
    $mail->Body    = $final_body;

    // Send the email
    $mail->send();
    // echo 'Submission email sent successfully.';
} catch (Exception $e) {
    // echo 'Failed to send email: ' . $mail->ErrorInfo;
}




// send to halo

// $stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
// $stmt->execute([$form_id]);
// $form = $stmt->fetch(PDO::FETCH_ASSOC);
// $userid = $form['user_id'];

// $stmt2 = $pdo->prepare("SELECT * FROM haloapi WHERE user_id = ?");
// $stmt2->execute([$userid]);
// $form2 = $stmt2->fetch(PDO::FETCH_ASSOC);
// $datahalo = $form2['access_token'];

// $stmt4 = $pdo->prepare("SELECT * FROM settings WHERE id = ?");
// $stmt4->execute(['1']);
// $form4 = $stmt4->fetch(PDO::FETCH_ASSOC);
// $datahalourl = $form4['halo_url'];




// $dataurl = $form2['access_token'];

// if ($datahalo != '') {

//     // 1. Get the latest submission for this form
//     $stmt3 = $pdo->prepare("SELECT * FROM submissions WHERE form_id = ? ORDER BY id DESC LIMIT 1");
//     $stmt3->execute([$form_id]); // use the correct $form_id from context
//     $submission = $stmt3->fetch(PDO::FETCH_ASSOC);

//     if ($submission) {
//         // 2. Decode the JSON submission data
//         $entry_data = json_decode($submission['entry_data'], true);

//         // 3. Prepare default payload structure
//         $halo_payload = [
//             "summary"      => $entry_data['summary'] ?? 'No summary',
//             "details"      => $entry_data['details'] ?? 'No details'
//         ];
//         // echo json_encode([$halo_payload])
//         try {
//             $curl = curl_init();

//             curl_setopt_array($curl, array(
//                 CURLOPT_URL => $datahalourl.'/api/tickets',
//                 CURLOPT_RETURNTRANSFER => true,
//                 CURLOPT_ENCODING => '',
//                 CURLOPT_MAXREDIRS => 10,
//                 CURLOPT_TIMEOUT => 0,
//                 CURLOPT_FOLLOWLOCATION => true,
//                 CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//                 CURLOPT_CUSTOMREQUEST => 'POST',
//                 CURLOPT_POSTFIELDS => json_encode([$halo_payload]),
//                 CURLOPT_HTTPHEADER => array(
//                     'Content-Type: application/json',
//                     'Authorization: Bearer ' . $datahalo
//                 ),
//             ));

//             $response = curl_exec($curl);
//             $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
//             $curl_error = curl_error($curl);
        
//             curl_close($curl);
        
//         } catch (Exception $e) {
//             // error_log('Halo API Error: ' .    $e->getMessage());
//         }
//     }
// }
// send to halo















// Thank you message (with Bootstrap styling)
echo "<!DOCTYPE html>";
echo "<html lang='en'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>Thank You</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "</head>";
echo "<body>";
echo "<div class='container py-5'>";
echo "<div class='alert alert-success text-center'>";
echo "✅ Thank you for your submission!";
echo "</div>";
echo "<div class='text-center'>";
echo "<a href='../admin/forms.php' class='btn btn-primary mt-3'>Back to Forms</a>";
echo "</div>";
echo "</div>";
echo "</body>";
echo "</html>";
?>

