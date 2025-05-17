<?php
require_once __DIR__ . '/../vendor/autoload.php'; 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


function sendCustomEmail($toEmail, $subject, $htmlBody, $plainBody, $smtpSettings)
{

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host       = $smtpSettings['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpSettings['smtp_username'];
        $mail->Password   = $smtpSettings['smtp_password'];
        $mail->SMTPSecure = $smtpSettings['smtp_secure'];
        $mail->Port       = $smtpSettings['smtp_port'];

        $mail->setFrom($smtpSettings['from_email'], 'Form Builder');
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>