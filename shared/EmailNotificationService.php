<?php
/**
 * Email Notification Service
 * Handles sending emails for platform events.
 * Uses PHP mail() with optional SMTP configuration.
 */

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
     * Send an email
     */
    public function send(string $toEmail, string $subject, string $htmlBody, string $textBody = null): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $platformName = $this->getPlatformName();
        $fromEmail = $this->getPlatformEmail();
        $fromName = $platformName;

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

        // Wrap HTML body in a basic template
        $fullHtml = $this->wrapInTemplate($subject, $htmlBody);

        // Generate plain text version if not provided
        if ($textBody === null) {
            $textBody = strip_tags($htmlBody);
            $textBody = preg_replace('/\s+/', ' ', $textBody);
            $textBody = trim($textBody);
        }

        $result = @mail($toEmail, $subject, $fullHtml, $headerString);

        // Log email attempt for debugging
        $this->logEmail($toEmail, $subject, $result);

        return $result;
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
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>{$subject}</title>
</head>
<body style='margin:0;padding:0;background-color:#f4f4f5;font-family:system-ui,-apple-system,sans-serif;'>
    <table width='100%' cellpadding='0' cellspacing='0' style='background-color:#f4f4f5;padding:40px 20px;'>
        <tr>
            <td align='center'>
                <table width='600' cellpadding='0' cellspacing='0' style='background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);'>
                    <!-- Header -->
                    <tr>
                        <td style='background:linear-gradient(135deg,#7c3aed,#2563eb);padding:30px;text-align:center;'>
                            <h1 style='color:#ffffff;margin:0;font-size:24px;font-weight:700;'>{$platformName}</h1>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style='padding:40px 30px;'>
                            {$content}
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style='background-color:#f9fafb;padding:20px 30px;text-align:center;border-top:1px solid #e5e7eb;'>
                            <p style='margin:0 0 8px;color:#6b7280;font-size:12px;'>This is an automated notification from {$platformName}.</p>
                            <p style='margin:0;color:#9ca3af;font-size:11px;'>&copy; {$currentYear} {$platformName}. All rights reserved.</p>
                            <p style='margin:8px 0 0;color:#9ca3af;font-size:11px;'>Need help? Contact us at <a href='mailto:{$supportEmail}' style='color:#7c3aed;'>{$supportEmail}</a></p>
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
     * Log email attempt
     */
    private function logEmail(string $to, string $subject, bool $success): void
    {
        try {
            $payload = json_encode([
                'to' => $to,
                'subject' => $subject,
                'success' => $success,
            ]);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $stmt = $this->conn->prepare(
                'INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at) VALUES (0, "email_sent", ?, ?, NOW())'
            );
            $stmt->bind_param('ss', $ip, $payload);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            // Silently fail - don't break email flow
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

    // ═══════════════════════════════════════════════════════════════════
    // NOTIFICATION EMAIL METHODS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Send proposal received email to freelancer
     */
    public function sendProposalReceived(int $freelancerId, string $jobTitle, int $jobId): bool
    {
        $email = $this->getUserEmail($freelancerId);
        if (!$email) return false;

        $platformName = $this->getPlatformName();
        $jobUrl = "/jobhub/client/proposal_detail.php?id={$jobId}";

        $subject = "New Proposal Received - {$jobTitle}";
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>New Proposal Received</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>A new proposal has been submitted for your job:</p>
            <div style='background-color:#f9fafb;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #7c3aed;'>
                <p style='margin:0;color:#1f2937;font-size:16px;font-weight:600;'>{$jobTitle}</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>Log in to review the proposal and respond to the freelancer.</p>
            <a href='{$jobUrl}' style='display:inline-block;background:linear-gradient(135deg,#7c3aed,#2563eb);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>View Proposal</a>
        ";

        return $this->send($email, $subject, $content);
    }

    /**
     * Send proposal accepted email to freelancer
     */
    public function sendProposalAccepted(int $freelancerId, string $clientName, int $contractId): bool
    {
        $email = $this->getUserEmail($freelancerId);
        if (!$email) return false;

        $contractUrl = "/jobhub/freelancer/contract_detail.php?id={$contractId}";

        $subject = "Proposal Accepted - Contract Created";
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Congratulations! Your Proposal Was Accepted</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>Great news! <strong>{$clientName}</strong> has accepted your proposal.</p>
            <div style='background-color:#ecfdf5;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #10b981;'>
                <p style='margin:0;color:#065f46;font-size:14px;'>A new contract has been created. You can now start working on the project.</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>Review the contract details and milestones to get started.</p>
            <a href='{$contractUrl}' style='display:inline-block;background:linear-gradient(135deg,#10b981,#059669);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>View Contract</a>
        ";

        return $this->send($email, $subject, $content);
    }

    /**
     * Send contract created email to client
     */
    public function sendContractCreated(int $clientId, string $freelancerName, int $contractId): bool
    {
        $email = $this->getUserEmail($clientId);
        if (!$email) return false;

        $contractUrl = "/jobhub/client/contract_detail.php?id={$contractId}";

        $subject = "New Contract Created with {$freelancerName}";
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>New Contract Created</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>A new contract has been created with <strong>{$freelancerName}</strong>.</p>
            <div style='background-color:#eff6ff;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #3b82f6;'>
                <p style='margin:0;color:#1e40af;font-size:14px;'>You can now add milestones and fund them to start the project.</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>Log in to manage your contract and milestones.</p>
            <a href='{$contractUrl}' style='display:inline-block;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>View Contract</a>
        ";

        return $this->send($email, $subject, $content);
    }

    /**
     * Send milestone funded email to freelancer
     */
    public function sendMilestoneFunded(int $freelancerId, string $milestoneTitle, float $amount, int $contractId): bool
    {
        $email = $this->getUserEmail($freelancerId);
        if (!$email) return false;

        $contractUrl = "/jobhub/freelancer/contract_detail.php?id={$contractId}";
        $formattedAmount = '$' . number_format($amount, 2);

        $subject = "Milestone Funded - {$milestoneTitle}";
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Milestone Funded in Escrow</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>A milestone has been funded and the payment is now held in escrow.</p>
            <div style='background-color:#fffbeb;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #f59e0b;'>
                <p style='margin:0 0 4px;color:#92400e;font-size:14px;font-weight:600;'>{$milestoneTitle}</p>
                <p style='margin:0;color:#b45309;font-size:18px;font-weight:700;'>{$formattedAmount}</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>You can now submit your work for this milestone. Once approved, the payment will be released to your wallet.</p>
            <a href='{$contractUrl}' style='display:inline-block;background:linear-gradient(135deg,#f59e0b,#d97706);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>Submit Work</a>
        ";

        return $this->send($email, $subject, $content);
    }

    /**
     * Send milestone approved email to freelancer
     */
    public function sendMilestoneApproved(int $freelancerId, string $milestoneTitle, float $amount, int $contractId): bool
    {
        $email = $this->getUserEmail($freelancerId);
        if (!$email) return false;

        $earningsUrl = "/jobhub/freelancer/earnings.php";
        $formattedAmount = '$' . number_format($amount, 2);

        $subject = "Milestone Approved - {$milestoneTitle}";
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Milestone Approved!</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>Great news! Your milestone has been approved by the client.</p>
            <div style='background-color:#ecfdf5;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #10b981;'>
                <p style='margin:0 0 4px;color:#065f46;font-size:14px;font-weight:600;'>{$milestoneTitle}</p>
                <p style='margin:0;color:#059669;font-size:18px;font-weight:700;'>{$formattedAmount}</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>The payment has been released to your wallet. You can now withdraw your earnings.</p>
            <a href='{$earningsUrl}' style='display:inline-block;background:linear-gradient(135deg,#10b981,#059669);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>View Earnings</a>
        ";

        return $this->send($email, $subject, $content);
    }

    /**
     * Send payment released email to freelancer
     */
    public function sendPaymentReleased(int $freelancerId, float $amount, int $contractId): bool
    {
        $email = $this->getUserEmail($freelancerId);
        if (!$email) return false;

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

        return $this->send($email, $subject, $content);
    }

    /**
     * Send dispute opened email to the other party
     */
    public function sendDisputeOpened(int $userId, string $disputerName, string $contractTitle, int $contractId): bool
    {
        $email = $this->getUserEmail($userId);
        if (!$email) return false;

        $contractUrl = "/jobhub/client/contract_detail.php?id={$contractId}";

        $subject = "Dispute Opened - {$contractTitle}";
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Dispute Has Been Opened</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'><strong>{$disputerName}</strong> has opened a dispute for the contract:</p>
            <div style='background-color:#fef2f2;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #ef4444;'>
                <p style='margin:0;color:#991b1b;font-size:14px;font-weight:600;'>{$contractTitle}</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>Our team will review the dispute and contact both parties. Please respond promptly to any requests for additional information.</p>
            <a href='{$contractUrl}' style='display:inline-block;background:linear-gradient(135deg,#ef4444,#dc2626);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>View Contract</a>
        ";

        return $this->send($email, $subject, $content);
    }

    /**
     * Send dispute resolved email to the affected party
     */
    public function sendDisputeResolved(int $userId, string $adminName, string $contractTitle, int $contractId, string $resolution): bool
    {
        $email = $this->getUserEmail($userId);
        if (!$email) return false;

        $contractUrl = "/jobhub/client/contract_detail.php?id={$contractId}";

        $subject = "Dispute Resolved - {$contractTitle}";
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Dispute Has Been Resolved</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>The dispute for the contract has been reviewed and resolved by <strong>{$adminName}</strong>.</p>
            <div style='background-color:#f0fdf4;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #22c55e;'>
                <p style='margin:0 0 8px;color:#166534;font-size:14px;font-weight:600;'>{$contractTitle}</p>
                <p style='margin:0;color:#166534;font-size:13px;line-height:1.5;'>{$resolution}</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>You can view the full details on the contract page. If you have questions, please contact support.</p>
            <a href='{$contractUrl}' style='display:inline-block;background:linear-gradient(135deg,#22c55e,#16a34a);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>View Contract</a>
        ";

        return $this->send($email, $subject, $content);
    }

    /**
     * Send dispute dismissed email to the affected party
     */
    public function sendDisputeDismissed(int $userId, string $adminName, string $contractTitle, int $contractId, string $reason): bool
    {
        $email = $this->getUserEmail($userId);
        if (!$email) return false;

        $contractUrl = "/jobhub/client/contract_detail.php?id={$contractId}";

        $subject = "Dispute Dismissed - {$contractTitle}";
        $content = "
            <h2 style='margin:0 0 20px;color:#1f2937;font-size:20px;'>Dispute Has Been Dismissed</h2>
            <p style='margin:0 0 16px;color:#4b5563;font-size:14px;line-height:1.6;'>The dispute for the contract has been reviewed and dismissed by <strong>{$adminName}</strong>.</p>
            <div style='background-color:#fefce8;border-radius:8px;padding:16px;margin:0 0 20px;border-left:4px solid #eab308;'>
                <p style='margin:0 0 8px;color:#854d0e;font-size:14px;font-weight:600;'>{$contractTitle}</p>
                <p style='margin:0;color:#854d0e;font-size:13px;line-height:1.5;'>{$reason}</p>
            </div>
            <p style='margin:0 0 24px;color:#6b7280;font-size:13px;'>The contract will continue as normal. If you have questions, please contact support.</p>
            <a href='{$contractUrl}' style='display:inline-block;background:linear-gradient(135deg,#eab308,#ca8a04);color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;'>View Contract</a>
        ";

        return $this->send($email, $subject, $content);
    }
}
