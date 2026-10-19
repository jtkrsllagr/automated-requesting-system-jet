<?php
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * NotificationService
 *
 * Sends pipeline emails using the same PHPMailer config already wired
 * in AuthController for password resets.
 *
 * Usage:
 *   \App\Services\NotificationService::notifyNextApprover($formId, $nextApproverEmail, $nextApproverName, $formLabel, $submitterName);
 *   \App\Services\NotificationService::notifySubmitter($submitterEmail, $submitterName, $formLabel, $formId, $outcome, $remarks);
 */
class NotificationService
{
    // ── Notify the next approver that a form is waiting for them ──
    public static function notifyNextApprover(
        int $formId,
        string $toEmail,
        string $toName,
        string $formLabel,
        string $submitterName,
        string $stageName
    ): void {
        $subject = "[Action Required] {$formLabel} #{$formId} — {$stageName}";

        $link = url("forms/view/{$formId}")
              . "A {$formLabel} submitted by {$submitterName} requires your approval at the {$stageName} stage.\n\n"
              . "Review it here:\n{$link}\n\n"
              . "Please log in and take action at your earliest convenience.\n\n"
              . "— " . ($_ENV['MAIL_FROM_NAME'] ?? 'Automated Requesting System');

        self::send($toEmail, $toName, $subject, $body);
    }

    // ── Notify the submitter of an approval or rejection outcome ──
    public static function notifySubmitter(
        string $toEmail,
        string $toName,
        string $formLabel,
        int $formId,
        string $outcome,   // 'approved_step' | 'final_approved' | 'completed' | 'rejected'
        string $stageName,
        string $remarks = ''
    ): void {
        $link = url("forms/view/{$formId}");

        switch ($outcome) {
            case 'rejected':
                $subject = "[Update] Your {$formLabel} #{$formId} was rejected";
                $body = "Hi {$toName},\n\n"
                         . "Unfortunately, your {$formLabel} (#{$formId}) was rejected at the {$stageName} stage.\n";
                if ($remarks) {
                    $body .= "\nReason provided:\n\"{$remarks}\"\n";
                }
                $body .= "\nYou may submit a revised request if needed:\n{$link}\n";
                break;

            case 'completed':
                $subject = "[Completed] Your {$formLabel} #{$formId} has been fully approved";
                $body = "Hi {$toName},\n\n"
                         . "Great news! Your {$formLabel} (#{$formId}) has completed all approval stages.\n\n"
                         . "View the final record here:\n{$link}\n";
                break;

            case 'final_approved':
                $subject = "[Almost Done] Your {$formLabel} #{$formId} reached final approval";
                $body = "Hi {$toName},\n\n"
                         . "Your {$formLabel} (#{$formId}) has been granted final approval and is in the last stage.\n\n"
                         . "Track it here:\n{$link}\n";
                break;

            default: // approved_step — intermediate stage
                $subject = "[Update] Your {$formLabel} #{$formId} passed {$stageName}";
                $body    = "Hi {$toName},\n\n"
                         . "Your {$formLabel} (#{$formId}) has passed the {$stageName} stage and is moving to the next approver.\n\n"
                         . "Track it here:\n{$link}\n";
                break;
        }

        $body .= "\n— " . ($_ENV['MAIL_FROM_NAME'] ?? 'Automated Requesting System');
        self::send($toEmail, $toName, $subject, $body);
    }

    // ── Internal mailer ────────────────────────────────────────────
    private static function send(string $toEmail, string $toName, string $subject, string $body): void
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USERNAME'];
            $mail->Password = $_ENV['MAIL_PASSWORD'];
            $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
            $mail->Port = $_ENV['MAIL_PORT'] ?? 587;

            $mail->setFrom(
                $_ENV['MAIL_FROM_ADDRESS'],
                $_ENV['MAIL_FROM_NAME'] ?? 'Automated Requesting System'
            );
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
        } catch (Exception $e) {
            // Silently fail — never crash a user action due to a mail error.
            // Log to PHP error log for ops visibility.
            error_log("[NotificationService] Mail failed to {$toEmail}: " . $mail->ErrorInfo);
        }
    }
}