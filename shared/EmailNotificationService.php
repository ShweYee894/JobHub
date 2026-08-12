<?php
/**
 * Email Notification Service
 * Handles sending emails for platform events via PHPMailer SMTP.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/env.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailNotificationService
{
    private $conn;
    private $settings = [];

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->loadSettings();
    }

    /**
     * Load email settings from platform_settings.json
     */
    private function loadSettings(): void
    {
        $settingsFile = __DIR__ . '/../config/platform_settings.json';
        if (file_exists($settingsFile)) {
            $this->settings = json_decode(file_get_contents($settingsFile), true) ?? [];
        }
    }

    /**
     * Check if email notifications are enabled
     */
    public function isEnabled(): bool
    {
        return !empty($this->settings['email_notifications']);
    }

    /**
     * Get platform name for email branding
     */
    private function getPlatformName(): string
    {
        return $this->settings['platform_name'] ?? 'JobHub';
    }

    /**
     * Get platform email for sender
     */
    private function getPlatformEmail(): string
    {
        return $this->settings['platform_email'] ?? 'noreply@jobhub.com';
    }

    /**
     * Get support email
     */
    private function getSupportEmail(): string
    {
        return $this->settings['support_email'] ?? 'support@jobhub.com';
    }

    /**
     * Get SMTP configuration from .env, falling back to platform_settings.json
     */
    private function getSmtpConfig(): array
    {
        return [
            'host'       => env('MAIL_HOST', $this->settings['smtp_host'] ?? ''),
            'port'       => (int) env('MAIL_PORT', $this->settings['smtp_port'] ?? 587),
            'username'   => env('MAIL_USERNAME', $this->settings['smtp_username'] ?? ''),
            'password'   => env('MAIL_PASSWORD', $this->settings['smtp_password'] ?? ''),
            'encryption' => env('MAIL_ENCRYPTION', $this->settings['smtp_encryption'] ?? 'tls'),
        ];
    }

    /**
     * Check if SMTP is enabled
     */
    private function isSmtpEnabled(): bool
    {
        return !empty($this->settings['smtp_enabled']);
    }

    /**
     * Send an email using PHPMailer (SMTP) or fallback to mail()
     *
     * @return array{success: bool, message: string}
     */
    public function send(string $toEmail, string $subject, string $htmlBody, ?string $textBody = null, string $notificationType = 'general'): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'Email notifications are disabled'];
        }

        $platformName = $this->getPlatformName();
        $fromEmail = $this->getPlatformEmail();
        $fromName = env('MAIL_FROM_NAME', $platformName);

        // Wrap HTML body in template
        $fullHtml = $this->wrapInTemplate($subject, $htmlBody);

        // Generate plain text if not provided
        if ($textBody === null) {
            $textBody = strip_tags($htmlBody);
            $textBody = preg_replace('/\s+/', ' ', $textBody);
            $textBody = trim($textBody);
        }

        // Try SMTP first, fallback to mail()
        if ($this->isSmtpEnabled()) {
            $result = $this->sendViaSmtp($toEmail, $subject, $fullHtml, $textBody, $fromEmail, $fromName);
        } else {
            $result = $this->sendViaMail($toEmail, $subject, $fullHtml, $fromEmail, $fromName);
        }

        // Log email attempt
        $this->logEmail($toEmail, $subject, $result['success'], $notificationType, $result['message'] ?? null);

        return $result;
    }

    /**
     * Send email via PHPMailer SMTP
     */
    private function sendViaSmtp(string $toEmail, string $subject, string $htmlBody, string $textBody, string $fromEmail, string $fromName): array
    {
        $smtp = $this->getSmtpConfig();

        if (empty($smtp['host']) || empty($smtp['username']) || empty($smtp['password'])) {
            return ['success' => false, 'message' => 'SMTP credentials not configured'];
        }

        try {
            $mail = new PHPMailer(true);

            // SMTP configuration
            $mail->isSMTP();
            $mail->Host = $smtp['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtp['username'];
            $mail->Password = $smtp['password'];
            $mail->SMTPSecure = $smtp['encryption'] === 'none' ? false : $smtp['encryption'];
            $mail->Port = $smtp['port'];
            $mail->CharSet = 'UTF-8';

            // Sender — use SMTP username as From (required by Gmail/others)
            $mail->setFrom($smtp['username'], $fromName);
            if ($smtp['username'] !== $fromEmail) {
                $mail->addReplyTo($fromEmail, $fromName);
            }
            $mail->addAddress($toEmail);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;

            $mail->send();

            return ['success' => true, 'message' => 'Email sent successfully'];
        } catch (Exception $e) {
            error_log('[JobHub] SMTP email failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Email could not be sent: ' . $e->getMessage()];
        }
    }

    /**
     * Send email via PHP mail() function (fallback)
     */
    private function sendViaMail(string $toEmail, string $subject, string $htmlBody, string $fromEmail, string $fromName): array
    {
        $headers = [
            'From' => "{$fromName} <{$fromEmail}>",
            'Reply-To' => $fromEmail,
            'X-Mailer' => 'PHP/' . phpversion(),
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/html; charset=UTF-8',
        ];

        $headerString = '';
        foreach ($headers as $key => $value) {
            $headerString .= "{$key}: {$value}\r\n";
        }

        $result = @mail($toEmail, $subject, $htmlBody, $headerString);

        if ($result) {
            return ['success' => true, 'message' => 'Email sent successfully'];
        }

        return ['success' => false, 'message' => 'Email could not be sent via mail()'];
    }

    /**
     * Wrap email content in an HTML template
     */
    private function wrapInTemplate(string $subject, string $content): string
    {
        $platformName = $this->getPlatformName();
        $supportEmail = $this->getSupportEmail();
        $currentYear = date('Y');

        return "
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>{$subject}</title>
</head>
<body style='margin:0;padding:0;background-color:#F9FAFB;font-family:Inter,Helvetica Neue,Arial,sans-serif;'>
    <table width='100%' cellpadding='0' cellspacing='0' style='background-color:#F9FAFB;padding:40px 20px;'>
        <tr>
            <td align='center'>
                <table width='600' cellpadding='0' cellspacing='0' style='background-color:#FFFFFF;border:1px solid #E5E7EB;border-radius:8px;overflow:hidden;'>
                    <!-- Header -->
                    <tr>
                        <td style='padding:24px 40px;border-bottom:1px solid #E5E7EB;'>
                            <table cellpadding='0' cellspacing='0' width='100%'>
                                <tr>
                                    <td align='left' style='font-family:Inter,Helvetica Neue,Arial,sans-serif;'>
                                        <span style='font-size:20px;font-weight:800;color:#111827;letter-spacing:-0.5px;'>{$platformName}</span>
                                        <span style='display:inline-block;width:6px;height:6px;background-color:#2563EB;border-radius:50%;margin-left:4px;vertical-align:middle;position:relative;top:-6px;'></span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style='padding:40px 40px 48px;'>
                            {$content}
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style='padding:24px 40px;border-top:1px solid #E5E7EB;'>
                            <table cellpadding='0' cellspacing='0' width='100%'>
                                <tr>
                                    <td align='center' style='font-family:Inter,Helvetica Neue,Arial,sans-serif;'>
                                        <p style='margin:0 0 6px;color:#9CA3AF;font-size:12px;line-height:1.5;'>This is an automated notification from {$platformName}. Please do not reply.</p>
                                        <p style='margin:0 0 8px;color:#9CA3AF;font-size:12px;line-height:1.5;'>&copy; {$currentYear} {$platformName}. All rights reserved.</p>
                                        <p style='margin:0;color:#9CA3AF;font-size:12px;line-height:1.5;'>Need help? Contact us at <a href='mailto:{$supportEmail}' style='color:#2563EB;text-decoration:none;'>{$supportEmail}</a></p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>";
    }

    /**
     * Log email attempt to user_behavior_logs
     */
    private function logEmail(string $to, string $subject, bool $success, string $type = 'general', ?string $errorMessage = null): void
    {
        try {
            $payload = json_encode([
                'to' => $to,
                'subject' => $subject,
                'notification_type' => $type,
                'success' => $success,
                'error_message' => $errorMessage,
            ]);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $actionType = $success ? 'email_sent' : 'email_failed';
            $stmt = $this->conn->prepare(
                'INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at) VALUES (0, ?, ?, ?, NOW())'
            );
            $stmt->bind_param('sss', $actionType, $ip, $payload);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log('[JobHub] Email log failed: ' . $e->getMessage());
        }
    }

    /**
     * Get user email from database
     */
    public function getUserEmail(int $userId): ?string
    {
        $stmt = $this->conn->prepare('SELECT email FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result ? $result['email'] : null;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AUTHENTICATION EMAILS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Send welcome email after registration
     */
    public function sendWelcomeEmail(int $userId, string $name, string $role): array
    {
        $email = $this->getUserEmail($userId);
        if (!$email) return ['success' => false, 'message' => 'User email not found'];

        $platformName = $this->getPlatformName();
        $dashboardUrl = "/jobhub/{$role}/dashboard.php";

        $subject = "Welcome to {$platformName}!";
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Welcome to {$platformName}, " . htmlspecialchars($name) . "!</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>Your account has been created successfully. You're now a <strong>" . ucfirst(htmlspecialchars($role)) . "</strong> on our platform.</p>
            <div style='background-color:#eff6ff;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #3b82f6;'>
                <p style='margin:0;color:#1e40af;font-size:14px;'>Start exploring the platform and connect with " . ($role === 'client' ? 'talented freelancers' : 'exciting projects') . ".</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>Complete your profile to attract more " . ($role === 'client' ? 'freelancers' : 'clients') . ".</p>
            <a href='{$dashboardUrl}' style='display:inline-block;background:linear-gradient(135deg,#7c3aed,#2563eb);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>Go to Dashboard</a>
        ";

        return $this->send($email, $subject, $content, null, 'welcome');
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail(string $email, string $name, string $resetLink): array
    {
        $platformName = $this->getPlatformName();
        $supportEmail = $this->getSupportEmail();

        $subject = "Password Reset Request - {$platformName}";
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Password Reset Request</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>Hi " . htmlspecialchars($name) . ",</p>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>We received a request to reset your password. Click the button below to set a new password:</p>
            <div style='text-align:center;margin:0 0 24px;'>
                <a href='{$resetLink}' style='display:inline-block;background:linear-gradient(135deg,#7c3aed,#2563eb);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>Reset Password</a>
            </div>
            <p style='margin:0 0 16px;color:#6b7280;font-size:13px;'>This link will expire in 1 hour.</p>
            <p style='margin:0 0 16px;color:#6b7280;font-size:13px;'>If you didn't request this, please ignore this email or contact support at <a href='mailto:{$supportEmail}' style='color:#7c3aed;'>{$supportEmail}</a>.</p>
        ";

        return $this->send($email, $subject, $content, null, 'password_reset');
    }

    /**
     * Send password changed alert
     */
    public function sendPasswordChangedEmail(int $userId, string $name): array
    {
        $email = $this->getUserEmail($userId);
        if (!$email) return ['success' => false, 'message' => 'User email not found'];

        $platformName = $this->getPlatformName();
        $supportEmail = $this->getSupportEmail();

        $subject = "Password Changed - {$platformName}";
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Password Changed</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>Hi " . htmlspecialchars($name) . ",</p>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>Your password has been changed successfully.</p>
            <div style='background-color:#fef2f2;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #ef4444;'>
                <p style='margin:0;color:#991b1b;font-size:14px;'>If you did not make this change, please contact support immediately at <a href='mailto:{$supportEmail}' style='color:#ef4444;'>{$supportEmail}</a>.</p>
            </div>
        ";

        return $this->send($email, $subject, $content, null, 'security');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PROPOSAL EMAILS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Send proposal received email to client
     */
    public function sendProposalReceived(int $clientId, string $jobTitle, int $jobId): array
    {
        $email = $this->getUserEmail($clientId);
        if (!$email) return ['success' => false, 'message' => 'User email not found'];

        $jobUrl = "/jobhub/client/proposal_detail.php?id={$jobId}";

        $subject = "New Proposal Received - " . htmlspecialchars($jobTitle);
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>New Proposal Received</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>A new proposal has been submitted for your job:</p>
            <div style='background-color:#f9fafb;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #7c3aed;'>
                <p style='margin:0;color:#1f2937;font-size:16px;font-weight:600;'>" . htmlspecialchars($jobTitle) . "</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>Log in to review the proposal and respond to the freelancer.</p>
            <a href='{$jobUrl}' style='display:inline-block;background:linear-gradient(135deg,#7c3aed,#2563eb);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>View Proposal</a>
        ";

        return $this->send($email, $subject, $content, null, 'proposal');
    }

    /**
     * Send proposal accepted email to freelancer
     */
    public function sendProposalAccepted(int $freelancerId, string $clientName, int $contractId): array
    {
        $email = $this->getUserEmail($freelancerId);
        if (!$email) return ['success' => false, 'message' => 'User email not found'];

        $contractUrl = "/jobhub/freelancer/contract_detail.php?id={$contractId}";

        $subject = "Proposal Accepted - Contract Created";
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Congratulations! Your Proposal Was Accepted</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>Great news! <strong>" . htmlspecialchars($clientName) . "</strong> has accepted your proposal.</p>
            <div style='background-color:#ecfdf5;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #10b981;'>
                <p style='margin:0;color:#065f46;font-size:14px;'>A new contract has been created. You can now start working on the project.</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>Review the contract details and milestones to get started.</p>
            <a href='{$contractUrl}' style='display:inline-block;background:linear-gradient(135deg,#10b981,#059669);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>View Contract</a>
        ";

        return $this->send($email, $subject, $content, null, 'proposal');
    }

    /**
     * Send proposal rejected email to freelancer
     */
    public function sendProposalRejected(int $freelancerId, string $jobTitle): array
    {
        $email = $this->getUserEmail($freelancerId);
        if (!$email) return ['success' => false, 'message' => 'User email not found'];

        $jobsUrl = "/jobhub/freelancer/browse_jobs.php";

        $subject = "Proposal Not Selected - " . htmlspecialchars($jobTitle);
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Proposal Not Selected</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>Your proposal for the job <strong>" . htmlspecialchars($jobTitle) . "</strong> was not selected this time.</p>
            <div style='background-color:#f9fafb;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #6b7280;'>
                <p style='margin:0;color:#374151;font-size:14px;'>Don't be discouraged! There are many other projects looking for your skills.</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>Keep applying to find the right match.</p>
            <a href='{$jobsUrl}' style='display:inline-block;background:linear-gradient(135deg,#6b7280,#4b5563);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>Browse Jobs</a>
        ";

        return $this->send($email, $subject, $content, null, 'proposal');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CONTRACT EMAILS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Send contract created email to client
     */
    public function sendContractCreated(int $clientId, string $freelancerName, int $contractId): array
    {
        $email = $this->getUserEmail($clientId);
        if (!$email) return ['success' => false, 'message' => 'User email not found'];

        $contractUrl = "/jobhub/client/contract_detail.php?id={$contractId}";

        $subject = "New Contract Created with " . htmlspecialchars($freelancerName);
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>New Contract Created</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>A new contract has been created with <strong>" . htmlspecialchars($freelancerName) . "</strong>.</p>
            <div style='background-color:#eff6ff;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #3b82f6;'>
                <p style='margin:0;color:#1e40af;font-size:14px;'>You can now add milestones and fund them to start the project.</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>Log in to manage your contract and milestones.</p>
            <a href='{$contractUrl}' style='display:inline-block;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>View Contract</a>
        ";

        return $this->send($email, $subject, $content, null, 'contract');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // MILESTONE EMAILS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Send milestone created email to freelancer
     */
    public function sendMilestoneCreated(int $freelancerId, string $milestoneTitle, float $amount, int $contractId): array
    {
        $email = $this->getUserEmail($freelancerId);
        if (!$email) return ['success' => false, 'message' => 'User email not found'];

        $contractUrl = "/jobhub/freelancer/contract_detail.php?id={$contractId}";
        $formattedAmount = '$' . number_format($amount, 2);

        $subject = "New Milestone Created - " . htmlspecialchars($milestoneTitle);
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>New Milestone Created</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>A new milestone has been added to your contract.</p>
            <div style='background-color:#f9fafb;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #7c3aed;'>
                <p style='margin:0 0 4px;color:#1f2937;font-size:14px;font-weight:600;'>" . htmlspecialchars($milestoneTitle) . "</p>
                <p style='margin:0;color:#7c3aed;font-size:18px;font-weight:700;'>{$formattedAmount}</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>Review the milestone details and wait for it to be funded before submitting your work.</p>
            <a href='{$contractUrl}' style='display:inline-block;background:linear-gradient(135deg,#7c3aed,#2563eb);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>View Contract</a>
        ";

        return $this->send($email, $subject, $content, null, 'milestone');
    }

    /**
     * Send milestone funded email to freelancer
     */
    public function sendMilestoneFunded(int $freelancerId, string $milestoneTitle, float $amount, int $contractId): array
    {
        $email = $this->getUserEmail($freelancerId);
        if (!$email) return ['success' => false, 'message' => 'User email not found'];

        $contractUrl = "/jobhub/freelancer/contract_detail.php?id={$contractId}";
        $formattedAmount = '$' . number_format($amount, 2);

        $subject = "Milestone Funded - " . htmlspecialchars($milestoneTitle);
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Milestone Funded in Escrow</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>A milestone has been funded and the payment is now held in escrow.</p>
            <div style='background-color:#fffbeb;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #f59e0b;'>
                <p style='margin:0 0 4px;color:#92400e;font-size:14px;font-weight:600;'>" . htmlspecialchars($milestoneTitle) . "</p>
                <p style='margin:0;color:#b45309;font-size:18px;font-weight:700;'>{$formattedAmount}</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>You can now submit your work for this milestone. Once approved, the payment will be released to your wallet.</p>
            <a href='{$contractUrl}' style='display:inline-block;background:linear-gradient(135deg,#f59e0b,#d97706);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>Submit Work</a>
        ";

        return $this->send($email, $subject, $content, null, 'milestone');
    }

    /**
     * Send milestone submitted email to client
     */
    public function sendMilestoneSubmitted(int $clientId, string $freelancerName, string $milestoneTitle): array
    {
        $email = $this->getUserEmail($clientId);
        if (!$email) return ['success' => false, 'message' => 'User email not found'];

        $contractsUrl = "/jobhub/client/contracts.php";

        $subject = "Milestone Submitted - " . htmlspecialchars($milestoneTitle);
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Milestone Submitted for Review</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'><strong>" . htmlspecialchars($freelancerName) . "</strong> has submitted a milestone for your review.</p>
            <div style='background-color:#eff6ff;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #3b82f6;'>
                <p style='margin:0;color:#1e40af;font-size:14px;font-weight:600;'>" . htmlspecialchars($milestoneTitle) . "</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>Log in to review the submission and approve or request revisions.</p>
            <a href='{$contractsUrl}' style='display:inline-block;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>Review Submission</a>
        ";

        return $this->send($email, $subject, $content, null, 'milestone');
    }

    /**
     * Send milestone approved email to freelancer
     */
    public function sendMilestoneApproved(int $freelancerId, string $milestoneTitle, float $amount, int $contractId): array
    {
        $email = $this->getUserEmail($freelancerId);
        if (!$email) return ['success' => false, 'message' => 'User email not found'];

        $earningsUrl = "/jobhub/freelancer/earnings.php";
        $formattedAmount = '$' . number_format($amount, 2);

        $subject = "Milestone Approved - " . htmlspecialchars($milestoneTitle);
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Milestone Approved!</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>Great news! Your milestone has been approved by the client.</p>
            <div style='background-color:#ecfdf5;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #10b981;'>
                <p style='margin:0 0 4px;color:#065f46;font-size:14px;font-weight:600;'>" . htmlspecialchars($milestoneTitle) . "</p>
                <p style='margin:0;color:#059669;font-size:18px;font-weight:700;'>{$formattedAmount}</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>The payment has been released to your wallet. You can now withdraw your earnings.</p>
            <a href='{$earningsUrl}' style='display:inline-block;background:linear-gradient(135deg,#10b981,#059669);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>View Earnings</a>
        ";

        return $this->send($email, $subject, $content, null, 'milestone');
    }

    /**
     * Send revision requested email to freelancer
     */
    public function sendRevisionRequested(int $freelancerId, string $milestoneTitle, string $revisionNote): array
    {
        $email = $this->getUserEmail($freelancerId);
        if (!$email) return ['success' => false, 'message' => 'User email not found'];

        $notePreview = !empty($revisionNote) ? htmlspecialchars(substr($revisionNote, 0, 200)) : 'No notes provided.';

        $subject = "Revision Requested - " . htmlspecialchars($milestoneTitle);
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Revision Requested</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>The client has requested a revision for your milestone submission.</p>
            <div style='background-color:#fefce8;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #eab308;'>
                <p style='margin:0 0 4px;color:#854d0e;font-size:14px;font-weight:600;'>" . htmlspecialchars($milestoneTitle) . "</p>
                <p style='margin:0;color:#854d0e;font-size:13px;line-height:1.5;'>{$notePreview}</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>Please review the feedback and resubmit your work.</p>
        ";

        return $this->send($email, $subject, $content, null, 'milestone');
    }

    /**
     * Send payment released email to freelancer
     */
    public function sendPaymentReleased(int $freelancerId, float $amount, int $contractId): array
    {
        $email = $this->getUserEmail($freelancerId);
        if (!$email) return ['success' => false, 'message' => 'User email not found'];

        $earningsUrl = "/jobhub/freelancer/earnings.php";
        $formattedAmount = '$' . number_format($amount, 2);

        $subject = "Payment Released - {$formattedAmount}";
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Payment Released to Your Wallet</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>A payment has been released and added to your wallet balance.</p>
            <div style='background-color:#ecfdf5;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #10b981;'>
                <p style='margin:0;color:#065f46;font-size:14px;'>Amount Released</p>
                <p style='margin:0;color:#059669;font-size:24px;font-weight:700;'>{$formattedAmount}</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>You can withdraw your earnings or use them for other transactions.</p>
            <a href='{$earningsUrl}' style='display:inline-block;background:linear-gradient(135deg,#10b981,#059669);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>View Earnings</a>
        ";

        return $this->send($email, $subject, $content, null, 'payment');
    }

    /**
     * Send withdrawal completed email
     */
    public function sendWithdrawalCompleted(int $userId, float $amount): array
    {
        $email = $this->getUserEmail($userId);
        if (!$email) return ['success' => false, 'message' => 'User email not found'];

        $walletUrl = "/jobhub/freelancer/wallet.php";
        $formattedAmount = '$' . number_format($amount, 2);

        $subject = "Withdrawal Completed - {$formattedAmount}";
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Withdrawal Completed</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>Your withdrawal has been processed successfully.</p>
            <div style='background-color:#ecfdf5;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #10b981;'>
                <p style='margin:0;color:#065f46;font-size:14px;'>Amount Withdrawn</p>
                <p style='margin:0;color:#059669;font-size:24px;font-weight:700;'>{$formattedAmount}</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>The funds have been sent to your linked payment method.</p>
            <a href='{$walletUrl}' style='display:inline-block;background:linear-gradient(135deg,#10b981,#059669);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>View Wallet</a>
        ";

        return $this->send($email, $subject, $content, null, 'payment');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DISPUTE EMAILS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Send dispute opened email to the other party
     */
    public function sendDisputeOpened(int $userId, string $disputerName, string $contractTitle, int $contractId): array
    {
        $email = $this->getUserEmail($userId);
        if (!$email) return ['success' => false, 'message' => 'User email not found'];

        $contractUrl = "/jobhub/client/contract_detail.php?id={$contractId}";

        $subject = "Dispute Opened - " . htmlspecialchars($contractTitle);
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Dispute Has Been Opened</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'><strong>" . htmlspecialchars($disputerName) . "</strong> has opened a dispute for the contract:</p>
            <div style='background-color:#fef2f2;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #ef4444;'>
                <p style='margin:0;color:#991b1b;font-size:14px;font-weight:600;'>" . htmlspecialchars($contractTitle) . "</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>Our team will review the dispute and contact both parties. Please respond promptly to any requests for additional information.</p>
            <a href='{$contractUrl}' style='display:inline-block;background:linear-gradient(135deg,#ef4444,#dc2626);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>View Contract</a>
        ";

        return $this->send($email, $subject, $content, null, 'dispute');
    }

    /**
     * Send dispute resolved email to the affected party
     */
    public function sendDisputeResolved(int $userId, string $adminName, string $contractTitle, int $contractId, string $resolution): array
    {
        $email = $this->getUserEmail($userId);
        if (!$email) return ['success' => false, 'message' => 'User email not found'];

        $contractUrl = "/jobhub/client/contract_detail.php?id={$contractId}";

        $subject = "Dispute Resolved - " . htmlspecialchars($contractTitle);
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Dispute Has Been Resolved</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>The dispute for the contract has been reviewed and resolved by <strong>" . htmlspecialchars($adminName) . "</strong>.</p>
            <div style='background-color:#f0fdf4;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #22c55e;'>
                <p style='margin:0 0 8px;color:#166534;font-size:14px;font-weight:600;'>" . htmlspecialchars($contractTitle) . "</p>
                <p style='margin:0;color:#166534;font-size:13px;line-height:1.5;'>" . htmlspecialchars($resolution) . "</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>You can view the full details on the contract page. If you have questions, please contact support.</p>
            <a href='{$contractUrl}' style='display:inline-block;background:linear-gradient(135deg,#22c55e,#16a34a);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>View Contract</a>
        ";

        return $this->send($email, $subject, $content, null, 'dispute');
    }

    /**
     * Send dispute dismissed email to the affected party
     */
    public function sendDisputeDismissed(int $userId, string $adminName, string $contractTitle, int $contractId, string $reason): array
    {
        $email = $this->getUserEmail($userId);
        if (!$email) return ['success' => false, 'message' => 'User email not found'];

        $contractUrl = "/jobhub/client/contract_detail.php?id={$contractId}";

        $subject = "Dispute Dismissed - " . htmlspecialchars($contractTitle);
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Dispute Has Been Dismissed</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>The dispute for the contract has been reviewed and dismissed by <strong>" . htmlspecialchars($adminName) . "</strong>.</p>
            <div style='background-color:#fefce8;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #eab308;'>
                <p style='margin:0 0 8px;color:#854d0e;font-size:14px;font-weight:600;'>" . htmlspecialchars($contractTitle) . "</p>
                <p style='margin:0;color:#854d0e;font-size:13px;line-height:1.5;'>" . htmlspecialchars($reason) . "</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>The contract will continue as normal. If you have questions, please contact support.</p>
            <a href='{$contractUrl}' style='display:inline-block;background:linear-gradient(135deg,#eab308,#ca8a04);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>View Contract</a>
        ";

        return $this->send($email, $subject, $content, null, 'dispute');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST EMAIL
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Send a test email to verify SMTP configuration
     */
    public function sendTestEmail(string $toEmail): array
    {
        $platformName = $this->getPlatformName();
        $smtpHost = htmlspecialchars(env('MAIL_HOST', $this->settings['smtp_host'] ?? 'Not configured'));

        $subject = "Test Email from {$platformName}";
        $content = "
            <h2 style='margin:0 0 16px;color:#111827;font-size:24px;font-weight:700;font-family:Inter,Helvetica Neue,Arial,sans-serif;line-height:1.3;'>SMTP Configuration Test</h2>
            <p style='margin:0 0 24px;color:#4B5563;font-size:16px;line-height:1.5;font-family:Inter,Helvetica Neue,Arial,sans-serif;'>This is a test email from <strong style='color:#111827;'>{$platformName}</strong>.</p>

            <!-- Success Callout -->
            <table cellpadding='0' cellspacing='0' width='100%' style='margin:0 0 24px;'>
                <tr>
                    <td style='background-color:#ECFDF5;border-radius:8px;padding:16px 20px;'>
                        <table cellpadding='0' cellspacing='0' width='100%'>
                            <tr>
                                <td width='24' valign='top' style='padding-right:12px;'>
                                    <svg width='20' height='20' viewBox='0 0 20 20' fill='none' xmlns='http://www.w3.org/2000/svg' style='display:block;'>
                                        <circle cx='10' cy='10' r='10' fill='#10B981'/>
                                        <path d='M6 10.5L8.5 13L14 7.5' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/>
                                    </svg>
                                </td>
                                <td valign='middle' style='font-family:Inter,Helvetica Neue,Arial,sans-serif;'>
                                    <p style='margin:0;color:#065F46;font-size:14px;line-height:1.5;font-weight:500;'>If you received this email, your SMTP configuration is working correctly.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- Metadata Box -->
            <table cellpadding='0' cellspacing='0' width='100%' style='margin:0;'>
                <tr>
                    <td style='background-color:#F3F4F6;border-radius:8px;padding:16px 20px;font-family:Inter,Helvetica Neue,Arial,sans-serif;'>
                        <table cellpadding='0' cellspacing='0' width='100%'>
                            <tr>
                                <td style='padding:0 0 10px;font-size:13px;line-height:1.5;'>
                                    <span style='color:#374151;font-weight:600;'>Sent at:</span>
                                    <span style='color:#6B7280;margin-left:6px;'>" . date('Y-m-d H:i:s') . "</span>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding:0;font-size:13px;line-height:1.5;'>
                                    <span style='color:#374151;font-weight:600;'>SMTP Host:</span>
                                    <span style='color:#6B7280;margin-left:6px;'>{$smtpHost}</span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        ";

        return $this->send($toEmail, $subject, $content, null, 'test');
    }
}
