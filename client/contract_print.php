<?php
$currentPage = 'contracts';
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';
$userId = $_SESSION['user_id'];
$contractId = intval($_GET['id'] ?? 0);
if ($contractId <= 0) {
    set_flash('error', 'Invalid contract.');
    redirect('contracts.php');
}

// Fetch contract (filtered by client_id like contract_detail.php)
$cs = $conn->prepare('SELECT c.id, c.contract_type, c.total_budget, c.status, c.created_at, c.updated_at, c.client_id, c.freelancer_id, c.job_id, cu.name AS client_name, cu.profile_image AS client_image, cu.email AS client_email, cu.phone AS client_phone, cl.company_name, fu.name AS freelancer_name, fu.profile_image AS freelancer_image, fu.email AS freelancer_email, fu.phone AS freelancer_phone, fl.title AS freelancer_title, j.title AS job_title, j.budget AS job_budget, j.description AS job_desc, j.category AS job_category, j.job_type, j.project_duration FROM contracts c JOIN users cu ON c.client_id = cu.id LEFT JOIN clients cl ON c.client_id = cl.client_id JOIN users fu ON c.freelancer_id = fu.id LEFT JOIN freelancers fl ON c.freelancer_id = fl.user_id JOIN jobs j ON c.job_id = j.id WHERE c.id = ? AND c.client_id = ?');
$cs->bind_param('ii', $contractId, $userId);
$cs->execute();
$contract = $cs->get_result()->fetch_assoc();
$cs->close();

if (!$contract) {
    set_flash('error', 'Contract not found.');
    redirect('contracts.php');
}

// Fetch milestones
$ms = $conn->prepare('SELECT * FROM milestones WHERE contract_id = ? ORDER BY created_at ASC');
$ms->bind_param('i', $contractId);
$ms->execute();
$milestonesResult = $ms->get_result();
$ms->close();
$milestones = [];
while ($row = $milestonesResult->fetch_assoc()) $milestones[] = $row;

// Fetch job skills
$js = $conn->prepare('SELECT s.skill_name FROM skills s JOIN job_skills js ON s.id = js.skill_id WHERE js.job_id = ? ORDER BY s.skill_name');
$js->bind_param('i', $contract['job_id']);
$js->execute();
$skillsResult = $js->get_result();
$js->close();
$skills = [];
while ($row = $skillsResult->fetch_assoc()) $skills[] = $row['skill_name'];

// Fetch payments for platform fee info
$payments = [];
$milestoneIds = array_column($milestones, 'id');
if ($milestoneIds) {
    $idPH = implode(',', array_fill(0, count($milestoneIds), '?'));
    $ps = $conn->prepare("SELECT total_amount, platform_fee, freelancer_net, status FROM payments WHERE milestone_id IN ({$idPH})");
    $ps->bind_param(str_repeat('i', count($milestoneIds)), ...$milestoneIds);
    $ps->execute();
    $payResult = $ps->get_result();
    while ($row = $payResult->fetch_assoc()) $payments[] = $row;
    $ps->close();
}

// Calculate totals
$totalMilestoneAmount = 0;
$escrowTotal = 0;
$releasedTotal = 0;
foreach ($milestones as $m) {
    $totalMilestoneAmount += (float) $m['amount'];
    if (in_array($m['status'], ['funded_in_escrow', 'submitted'])) $escrowTotal += (float) $m['amount'];
    if ($m['status'] === 'released') $releasedTotal += (float) $m['amount'];
}
$totalPlatformFees = 0;
foreach ($payments as $p) {
    $totalPlatformFees += (float) $p['platform_fee'];
}

$conn->close();

// Prepare display values
$contractNumber = 'JHB-' . str_pad($contract['id'], 6, '0', STR_PAD_LEFT);
$generatedDate = date('F j, Y \a\t g:i A');
$effectiveDate = date('F j, Y', strtotime($contract['created_at']));
$statusLabel = ucfirst($contract['status']);
$isActive = in_array($contract['status'], ['active', 'completed']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contract <?= sanitize_string($contractNumber) ?> &mdash; JobHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600&display=swap" rel="stylesheet">
    <!-- FA CDN removed -->
    <style>
        :root {
            --brand-green: #3a5a40;
            --brand-green-light: #588157;
            --brand-accent: #a3b18a;
            --brand-cream: #dad7cd;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        .print-container {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
        }

        /* ── Action Bar ─────────────────────────────────────── */
        .action-bar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #1e293b;
            color: #ffffff;
            padding: 12px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .action-bar-left {
            display: flex;
            flex-direction: column;
        }

        .action-bar-title {
            font-size: 14px;
            font-weight: 700;
            color: #f8fafc;
            letter-spacing: 0.02em;
        }

        .action-bar-subtitle {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 1px;
        }

        .action-bar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .action-btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #e2e8f0;
        }

        .action-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.18);
        }

        .action-btn-primary {
            background: #3a5a40;
            color: #ffffff;
        }

        .action-btn-primary:hover {
            background: #588157;
        }

        .action-btn-disabled {
            background: rgba(255, 255, 255, 0.05);
            color: #64748b;
            cursor: not-allowed;
        }

        /* ── Contract Header ────────────────────────────────── */
        .contract-header {
            background: linear-gradient(135deg, var(--brand-green) 0%, var(--brand-green-light) 100%);
            color: #ffffff;
            padding: 40px 40px 0 40px;
            position: relative;
            overflow: hidden;
        }

        .contract-header::before {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 50%;
        }

        .contract-header::after {
            content: '';
            position: absolute;
            bottom: -60px;
            left: 30%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
        }

        .header-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 24px;
            position: relative;
            z-index: 1;
        }

        .logo-box {
            width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #ffffff;
        }

        .header-meta {
            text-align: right;
            font-size: 12px;
            line-height: 1.8;
            color: rgba(255, 255, 255, 0.85);
        }

        .header-meta strong {
            color: #ffffff;
            font-weight: 600;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
        }

        .contract-title {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 24px;
            position: relative;
            z-index: 1;
            letter-spacing: -0.01em;
        }

        .info-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }

        .info-card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            padding: 14px 16px;
        }

        .info-card-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 4px;
        }

        .info-card-value {
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
        }

        .header-stripe {
            height: 4px;
            background: linear-gradient(90deg, var(--brand-accent), var(--brand-cream), var(--brand-accent));
            margin-top: 24px;
        }

        /* ── Sections ───────────────────────────────────────── */
        .section {
            padding: 32px 40px;
            border-bottom: 1px solid #e2e8f0;
        }

        .section:last-child {
            border-bottom: none;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--brand-green);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            font-size: 14px;
            width: 32px;
            height: 32px;
            background: rgba(58, 90, 64, 0.08);
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--brand-green-light);
        }

        /* ── Party Cards ────────────────────────────────────── */
        .parties-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        .party-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
        }

        .party-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--brand-green);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .party-label i {
            font-size: 11px;
        }

        .party-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .party-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--brand-accent);
            flex-shrink: 0;
        }

        .party-details {
            flex: 1;
        }

        .party-name {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .party-meta {
            font-size: 12px;
            color: #64748b;
            line-height: 1.6;
        }

        .party-meta i {
            width: 14px;
            text-align: center;
            color: var(--brand-green-light);
            margin-right: 4px;
        }

        .intro-text {
            font-size: 14px;
            color: #475569;
            line-height: 1.7;
            padding: 16px 20px;
            background: linear-gradient(135deg, rgba(58, 90, 64, 0.03), rgba(163, 177, 138, 0.06));
            border-left: 3px solid var(--brand-green-light);
            border-radius: 0 8px 8px 0;
        }

        /* ── Project Grid ───────────────────────────────────── */
        .project-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .project-field {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 16px;
        }

        .project-field.full-width {
            grid-column: 1 / -1;
        }

        .project-field-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            margin-bottom: 4px;
        }

        .project-field-value {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }

        .project-field-value.large-green {
            font-size: 22px;
            font-weight: 800;
            color: var(--brand-green);
        }

        .skill-tag {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(58, 90, 64, 0.08);
            color: var(--brand-green);
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin: 2px 4px 2px 0;
        }

        .project-desc {
            font-size: 14px;
            color: #475569;
            line-height: 1.7;
        }

        /* ── Scope of Work ──────────────────────────────────── */
        .scope-text {
            font-size: 14px;
            color: #475569;
            line-height: 1.7;
            margin-bottom: 16px;
        }

        .scope-list {
            list-style: none;
            padding: 0;
        }

        .scope-list li {
            position: relative;
            padding: 10px 0 10px 28px;
            font-size: 14px;
            color: #334155;
            border-bottom: 1px dashed #e2e8f0;
        }

        .scope-list li:last-child {
            border-bottom: none;
        }

        .scope-list li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 10px;
            color: var(--brand-green-light);
            font-size: 13px;
        }

        /* ── Milestones Table ───────────────────────────────── */
        .milestones-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .milestones-table thead th {
            background: var(--brand-green);
            color: #ffffff;
            padding: 12px 14px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .milestones-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }

        .milestones-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }

        .milestones-table tbody td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }

        .milestones-table tbody tr:last-child td {
            border-bottom: none;
        }

        .milestones-table tbody tr:hover {
            background: #f8fafc;
        }

        .ms-title {
            font-weight: 600;
            color: #1e293b;
        }

        .ms-desc {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 2px;
        }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-gray {
            background: #f1f5f9;
            color: #64748b;
        }

        .badge-amber {
            background: #fef3c7;
            color: #b45309;
        }

        .badge-blue {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .badge-green {
            background: #d1fae5;
            color: #047857;
        }

        .badge-red {
            background: #fee2e2;
            color: #b91c1c;
        }

        /* ── Payment Cards ──────────────────────────────────── */
        .payment-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .payment-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }

        .payment-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 12px;
        }

        .payment-icon-amber {
            background: #fef3c7;
            color: #d97706;
        }

        .payment-icon-blue {
            background: #dbeafe;
            color: #2563eb;
        }

        .payment-icon-green {
            background: #d1fae5;
            color: #059669;
        }

        .payment-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            margin-bottom: 6px;
        }

        .payment-amount {
            font-size: 24px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 6px;
        }

        .payment-desc {
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.5;
        }

        .release-conditions {
            padding: 16px 20px;
            background: linear-gradient(135deg, rgba(58, 90, 64, 0.03), rgba(163, 177, 138, 0.06));
            border-radius: 10px;
            font-size: 13px;
            color: #475569;
            line-height: 1.7;
        }

        .release-conditions strong {
            color: var(--brand-green);
        }

        /* ── Terms & Conditions ─────────────────────────────── */
        .terms-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
        }

        .terms-subtitle {
            font-family: 'Playfair Display', serif;
            font-size: 15px;
            font-weight: 600;
            color: var(--brand-green);
            margin-bottom: 10px;
            margin-top: 20px;
        }

        .terms-subtitle:first-child {
            margin-top: 0;
        }

        .terms-text {
            font-size: 13px;
            color: #475569;
            line-height: 1.7;
            margin-bottom: 8px;
        }

        .terms-list {
            list-style: none;
            padding: 0;
            margin-bottom: 12px;
        }

        .terms-list li {
            position: relative;
            padding: 5px 0 5px 20px;
            font-size: 13px;
            color: #475569;
            line-height: 1.6;
        }

        .terms-list li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 12px;
            width: 6px;
            height: 6px;
            background: var(--brand-green-light);
            border-radius: 50%;
        }

        /* ── Signatures ─────────────────────────────────────── */
        .signatures-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            margin-top: 8px;
        }

        .signature-block {
            text-align: center;
        }

        .signature-role {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--brand-green);
            margin-bottom: 16px;
        }

        .signature-area {
            border-bottom: 2px solid #1e293b;
            padding-bottom: 8px;
            margin-bottom: 10px;
            min-height: 48px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }

        .signature-name {
            font-family: 'Playfair Display', serif;
            font-style: italic;
            font-size: 22px;
            font-weight: 600;
            color: #1e293b;
        }

        .signature-pending {
            font-size: 13px;
            color: #94a3b8;
            font-style: italic;
        }

        .signature-printed {
            font-size: 12px;
            color: #64748b;
        }

        .signature-date {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 4px;
        }

        /* ── Disclaimer ─────────────────────────────────────── */
        .disclaimer-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 18px 20px;
            margin-top: 8px;
        }

        .disclaimer-title {
            font-size: 12px;
            font-weight: 700;
            color: #92400e;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .disclaimer-text {
            font-size: 12px;
            color: #78350f;
            line-height: 1.6;
        }

        /* ── Footer ─────────────────────────────────────────── */
        .contract-footer {
            background: var(--brand-green);
            color: rgba(255, 255, 255, 0.85);
            padding: 20px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
        }

        .footer-left {
            font-weight: 600;
        }

        .footer-left span {
            color: var(--brand-accent);
        }

        .footer-right {
            text-align: right;
            color: rgba(255, 255, 255, 0.6);
        }

        /* ── Responsive ─────────────────────────────────────── */
        @media (max-width: 768px) {
            .action-bar {
                padding: 10px 16px;
            }

            .contract-header {
                padding: 24px 20px 0 20px;
            }

            .contract-title {
                font-size: 24px;
            }

            .info-cards {
                grid-template-columns: 1fr;
            }

            .section {
                padding: 24px 20px;
            }

            .parties-grid {
                grid-template-columns: 1fr;
            }

            .project-grid {
                grid-template-columns: 1fr;
            }

            .payment-grid {
                grid-template-columns: 1fr;
            }

            .signatures-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }

            .milestones-table {
                font-size: 12px;
            }

            .contract-footer {
                flex-direction: column;
                text-align: center;
                gap: 8px;
                padding: 16px 20px;
            }

            .footer-right {
                text-align: center;
            }
        }

        /* ── Print Styles ───────────────────────────────────── */
        @media print {
            @page {
                size: A4;
                margin: 12mm;
            }

            body {
                background: #ffffff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .action-bar {
                display: none !important;
            }

            .print-container {
                box-shadow: none;
                max-width: 100%;
            }

            .section {
                break-inside: avoid;
            }

            .milestones-table {
                break-inside: avoid;
            }

            .parties-grid {
                break-inside: avoid;
            }

            .payment-grid {
                break-inside: avoid;
            }

            .signatures-grid {
                break-inside: avoid;
            }

            .terms-box {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
<div class="print-container" id="contractPage">

    <!-- ACTION BAR -->
    <div class="action-bar">
        <div class="action-bar-left">
            <div class="action-bar-title">Official Contract Document</div>
            <div class="action-bar-subtitle">Contract #<?= $contract['id'] ?> &mdash; <?= $statusLabel ?></div>
        </div>
        <div class="action-bar-right">
            <a href="../client/contract_detail.php?id=<?= $contract['id'] ?>" class="action-btn action-btn-secondary">
                <i data-lucide="arrow-left"></i> Back to Details
            </a>
            <button onclick="window.print()" class="action-btn action-btn-primary">
                <i data-lucide="printer"></i> Print Contract
            </button>
            <button onclick="downloadPDF()" class="action-btn action-btn-primary" id="pdfBtn">
                <i data-lucide="file-text"></i> <span id="pdfBtnText">Download PDF</span>
            </button>
        </div>
    </div>

    <!-- CONTRACT HEADER -->
    <div class="contract-header">
        <div class="header-top">
            <div class="logo-box">
                <i data-lucide="briefcase"></i>
            </div>
            <div class="header-meta">
                <div><strong>Contract No:</strong> <?= $contractNumber ?></div>
                <div><strong>Generated:</strong> <?= $generatedDate ?></div>
                <div><strong>Status:</strong> <span class="status-badge"><?= $statusLabel ?></span></div>
            </div>
        </div>
        <h1 class="contract-title">Official Service Agreement</h1>
        <div class="info-cards">
            <div class="info-card">
                <div class="info-card-label">Contract Type</div>
                <div class="info-card-value"><?= ucfirst(sanitize_string($contract['contract_type'])) ?></div>
            </div>
            <div class="info-card">
                <div class="info-card-label">Total Value</div>
                <div class="info-card-value"><?= format_currency((float) $contract['total_budget']) ?></div>
            </div>
            <div class="info-card">
                <div class="info-card-label">Date Created</div>
                <div class="info-card-value"><?= $effectiveDate ?></div>
            </div>
        </div>
        <div class="header-stripe"></div>
    </div>

    <!-- SECTION 1: PARTIES INVOLVED -->
    <div class="section">
        <h2 class="section-title"><i data-lucide="users"></i> Section 1 &mdash; Parties Involved</h2>

        <div class="parties-grid">
            <!-- Client -->
            <div class="party-card">
                <div class="party-label"><i data-lucide="user"></i> Client</div>
                <div class="party-info">
                    <img src="<?= sanitize_string(get_profile_image($contract['client_image'])) ?>" alt="Client" class="party-avatar">
                    <div class="party-details">
                        <div class="party-name"><?= sanitize_string($contract['client_name']) ?></div>
                        <div class="party-meta">
                            <i data-lucide="mail"></i> <?= sanitize_string($contract['client_email']) ?><br>
                            <i data-lucide="phone"></i> <?= sanitize_string($contract['client_phone'] ?? 'N/A') ?>
                            <?php if (!empty($contract['company_name'])): ?>
                            <br><i data-lucide="building"></i> <?= sanitize_string($contract['company_name'] ?? 'N/A') ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Freelancer -->
            <div class="party-card">
                <div class="party-label"><i data-lucide="monitor"></i> Freelancer</div>
                <div class="party-info">
                    <img src="<?= sanitize_string(get_profile_image($contract['freelancer_image'])) ?>" alt="Freelancer" class="party-avatar">
                    <div class="party-details">
                        <div class="party-name"><?= sanitize_string($contract['freelancer_name']) ?></div>
                        <div class="party-meta">
                            <i data-lucide="mail"></i> <?= sanitize_string($contract['freelancer_email']) ?><br>
                            <i data-lucide="phone"></i> <?= sanitize_string($contract['freelancer_phone'] ?? 'N/A') ?>
                            <?php if (!empty($contract['freelancer_title'])): ?>
                            <br><i data-lucide="badge"></i> <?= sanitize_string($contract['freelancer_title'] ?? 'N/A') ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="intro-text">
            This agreement is entered into as of <strong><?= $effectiveDate ?></strong> between the above-named parties for the provision of freelance services as described in this contract. Both parties acknowledge and agree to the terms, conditions, and obligations set forth herein. This document serves as the binding agreement governing the professional relationship between the Client and the Freelancer through the JobHub platform.
        </div>
    </div>

    <!-- SECTION 2: PROJECT DETAILS -->
    <div class="section">
        <h2 class="section-title"><i data-lucide="folder-open"></i> Section 2 &mdash; Project Details</h2>

        <div class="project-grid">
            <div class="project-field full-width">
                <div class="project-field-label">Project Title</div>
                <div class="project-field-value" style="font-size: 17px;"><?= sanitize_string($contract['job_title']) ?></div>
            </div>
            <div class="project-field">
                <div class="project-field-label">Category</div>
                <div class="project-field-value"><?= sanitize_string($contract['job_category'] ?? 'N/A') ?></div>
            </div>
            <div class="project-field">
                <div class="project-field-label">Contract Type</div>
                <div class="project-field-value"><?= ucfirst(sanitize_string($contract['contract_type'])) ?></div>
            </div>
            <div class="project-field">
                <div class="project-field-label">Budget</div>
                <div class="project-field-value large-green"><?= format_currency((float) $contract['job_budget']) ?></div>
            </div>
            <div class="project-field">
                <div class="project-field-label">Duration</div>
                <div class="project-field-value"><?= sanitize_string(str_replace('_', ' ', ucfirst(str_replace(['less_than_1_week', '1_to_4_weeks', '1_to_3_months', '3_to_6_months', 'more_than_6_months'], ['Less than 1 Week', '1 to 4 Weeks', '1 to 3 Months', '3 to 6 Months', 'More than 6 Months'], $contract['project_duration'] ?? ''))) ?: 'N/A') ?></div>
            </div>
            <?php if (!empty($skills)): ?>
            <div class="project-field full-width">
                <div class="project-field-label">Required Skills</div>
                <div>
                    <?php foreach ($skills as $skill): ?>
                        <span class="skill-tag"><?= sanitize_string($skill) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="project-field full-width">
                <div class="project-field-label">Project Description</div>
                <div class="project-desc"><?= nl2br(sanitize_string($contract['job_desc'] ?? 'No description provided.')) ?></div>
            </div>
        </div>
    </div>

    <!-- SECTION 3: SCOPE OF WORK -->
    <div class="section">
        <h2 class="section-title"><i data-lucide="clipboard-list"></i> Section 3 &mdash; Scope of Work</h2>

        <div class="scope-text">
            The Freelancer, <strong><?= sanitize_string($contract['freelancer_name']) ?></strong>, agrees to provide professional services to the Client, <strong><?= sanitize_string($contract['client_name']) ?></strong>, as outlined below. The scope of work encompasses the following deliverables and milestones associated with the project &ldquo;<?= sanitize_string($contract['job_title']) ?>&rdquo;:
        </div>

        <?php if (!empty($milestones)): ?>
            <ul class="scope-list">
                <?php foreach ($milestones as $index => $m): ?>
                    <li>
                        <strong>Milestone <?= ($index + 1) ?>:</strong> <?= sanitize_string($m['title']) ?>
                        <?php if (!empty($m['description'])): ?>
                            &mdash; <?= sanitize_string(truncate($m['description'], 120)) ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="scope-text" style="font-style: italic; color: #94a3b8;">No milestones have been defined for this contract yet.</p>
        <?php endif; ?>
    </div>

    <!-- SECTION 4: MILESTONES & DELIVERABLES -->
    <div class="section">
        <h2 class="section-title"><i data-lucide="list-checks"></i> Section 4 &mdash; Milestones &amp; Deliverables</h2>

        <?php if (!empty($milestones)): ?>
        <div style="overflow-x: auto;">
            <table class="milestones-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Milestone</th>
                        <th style="text-align: right;">Amount</th>
                        <th style="text-align: center;">Due Date</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: center;">Escrow</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($milestones as $index => $m): ?>
                    <tr>
                        <td style="font-weight: 700; color: var(--brand-green);"><?= ($index + 1) ?></td>
                        <td>
                            <div class="ms-title"><?= sanitize_string($m['title']) ?></div>
                            <?php if (!empty($m['description'])): ?>
                                <div class="ms-desc"><?= sanitize_string(truncate($m['description'], 80)) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; font-weight: 700;"><?= format_currency((float) $m['amount']) ?></td>
                        <td style="text-align: center; color: #64748b; font-size: 12px;">
                            <?= !empty($m['due_date']) ? date('M j, Y', strtotime($m['due_date'])) : 'N/A' ?>
                        </td>
                        <td style="text-align: center;">
                            <?php
                            $badgeClass = 'badge-gray';
                            if ($m['status'] === 'funded_in_escrow') $badgeClass = 'badge-amber';
                            elseif ($m['status'] === 'submitted') $badgeClass = 'badge-blue';
                            elseif ($m['status'] === 'released') $badgeClass = 'badge-green';
                            elseif ($m['status'] === 'disputed') $badgeClass = 'badge-red';
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= ucfirst(str_replace('_', ' ', $m['status'])) ?></span>
                        </td>
                        <td style="text-align: center; font-size: 12px; color: #64748b;">
                            <?php if (in_array($m['status'], ['funded_in_escrow', 'submitted'])): ?>
                                <i data-lucide="lock" style="color: #d97706;"></i> Locked
                            <?php elseif ($m['status'] === 'released'): ?>
                                <i data-lucide="circle-check" style="color: #059669;"></i> Released
                            <?php else: ?>
                                <i data-lucide="circle-minus" style="color: #94a3b8;"></i> &mdash;
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p style="text-align: center; color: #94a3b8; padding: 24px 0; font-size: 14px;">No milestones have been defined for this contract.</p>
        <?php endif; ?>
    </div>

    <!-- SECTION 5: PAYMENT TERMS -->
    <div class="section">
        <h2 class="section-title"><i data-lucide="credit-card"></i> Section 5 &mdash; Payment Terms</h2>

        <div class="payment-grid">
            <div class="payment-card">
                <div class="payment-icon payment-icon-amber"><i data-lucide="shield"></i></div>
                <div class="payment-label">Escrow Payment</div>
                <div class="payment-amount"><?= format_currency($escrowTotal) ?></div>
                <div class="payment-desc">Funds currently held in escrow pending milestone completion and approval.</div>
            </div>
            <div class="payment-card">
                <div class="payment-icon payment-icon-blue"><i data-lucide="percent"></i></div>
                <div class="payment-label">Platform Fee</div>
                <div class="payment-amount"><?= format_currency($totalPlatformFees) ?></div>
                <div class="payment-desc">Total service fees collected by the JobHub platform for facilitating this contract.</div>
            </div>
            <div class="payment-card">
                <div class="payment-icon payment-icon-green"><i data-lucide="banknote"></i></div>
                <div class="payment-label">Released to Freelancer</div>
                <div class="payment-amount"><?= format_currency($releasedTotal) ?></div>
                <div class="payment-desc">Total amount successfully released to the Freelancer upon milestone approval.</div>
            </div>
        </div>

        <div class="release-conditions">
            <strong>Release Conditions:</strong> Payments are held in escrow by the JobHub platform until the Client approves the submitted deliverables for each milestone. Upon approval, the Freelancer's net amount (after platform fees) is released to their wallet. In case of a dispute, funds remain in escrow until the matter is resolved through the platform's dispute resolution process. All payments are processed securely through the JobHub payment infrastructure.
        </div>
    </div>

    <!-- SECTION 6: TERMS AND CONDITIONS -->
    <div class="section">
        <h2 class="section-title"><i data-lucide="hammer"></i> Section 6 &mdash; Terms and Conditions</h2>

        <div class="terms-box">
            <div class="terms-subtitle">1. Client Responsibilities</div>
            <ul class="terms-list">
                <li>Provide clear and timely project requirements, feedback, and approvals for deliverables submitted by the Freelancer.</li>
                <li>Fund each milestone in escrow prior to the commencement of work on that milestone.</li>
                <li>Review and respond to submitted deliverables within the agreed-upon review period (default: 5 business days).</li>
                <li>Refrain from requesting work outside the agreed-upon scope without a formal change order and budget adjustment.</li>
                <li>Maintain professional and respectful communication throughout the duration of the contract.</li>
            </ul>

            <div class="terms-subtitle">2. Freelancer Responsibilities</div>
            <ul class="terms-list">
                <li>Deliver work that meets the quality standards and specifications outlined in the project scope and milestone descriptions.</li>
                <li>Adhere to the agreed-upon timelines and deadlines for each milestone deliverable.</li>
                <li>Submit milestone deliverables through the JobHub platform for formal review and approval.</li>
                <li>Remain available for reasonable communication and revisions during the active contract period.</li>
                <li>Ensure that all work delivered is original and does not infringe upon any third-party intellectual property rights.</li>
            </ul>

            <div class="terms-subtitle">3. Communication</div>
            <p class="terms-text">All official communication regarding this contract shall be conducted through the JobHub platform messaging system. Both parties are expected to respond to messages within a reasonable timeframe (24-48 business hours). External communication channels may be used informally, but platform records serve as the authoritative log for dispute resolution purposes.</p>

            <div class="terms-subtitle">4. Dispute Resolution</div>
            <p class="terms-text">In the event of a dispute between the Client and the Freelancer, both parties agree to first attempt resolution through direct communication. If a resolution cannot be reached, either party may escalate the matter through the JobHub dispute resolution system. The platform administration will review the evidence submitted by both parties and render a decision. Escrowed funds will be distributed according to the dispute outcome. The decision of the JobHub administration shall be considered final and binding.</p>

            <div class="terms-subtitle">5. Confidentiality</div>
            <p class="terms-text">Both parties agree to maintain the confidentiality of any proprietary or sensitive information shared during the course of this contract. This obligation shall survive the termination of the agreement. Neither party shall disclose confidential information to third parties without prior written consent from the other party.</p>

            <div class="terms-subtitle">6. Intellectual Property</div>
            <p class="terms-text">Upon full payment for each milestone, the Client shall receive full ownership of the deliverables produced for that milestone, including all related intellectual property rights. The Freelancer retains the right to display the work in their portfolio unless otherwise agreed upon in writing. Any pre-existing intellectual property used in the deliverables shall remain the property of its original owner, with the Freelancer granting the Client a non-exclusive license to use such components as part of the delivered work.</p>
        </div>
    </div>

    <!-- SECTION 7: DIGITAL SIGNATURES -->
    <div class="section">
        <h2 class="section-title"><i data-lucide="pen-tool"></i> Section 7 &mdash; Digital Signatures</h2>

        <div class="signatures-grid">
            <!-- Freelancer Signature -->
            <div class="signature-block">
                <div class="signature-role">Freelancer</div>
                <div class="signature-area">
                    <?php if ($isActive): ?>
                        <span class="signature-name"><?= sanitize_string($contract['freelancer_name']) ?></span>
                    <?php else: ?>
                        <span class="signature-pending">Pending Signature</span>
                    <?php endif; ?>
                </div>
                <div class="signature-printed">Printed Name: <?= sanitize_string($contract['freelancer_name']) ?></div>
                <div class="signature-date">Date: <?= $isActive ? $effectiveDate : '________________' ?></div>
            </div>

            <!-- Client Signature -->
            <div class="signature-block">
                <div class="signature-role">Client</div>
                <div class="signature-area">
                    <?php if ($isActive): ?>
                        <span class="signature-name"><?= sanitize_string($contract['client_name']) ?></span>
                    <?php else: ?>
                        <span class="signature-pending">Pending Signature</span>
                    <?php endif; ?>
                </div>
                <div class="signature-printed">Printed Name: <?= sanitize_string($contract['client_name']) ?></div>
                <div class="signature-date">Date: <?= $isActive ? $effectiveDate : '________________' ?></div>
            </div>
        </div>
    </div>

    <!-- DISCLAIMER -->
    <div class="section">
        <div class="disclaimer-box">
            <div class="disclaimer-title"><i data-lucide="triangle-alert"></i> Legal Disclaimer</div>
            <div class="disclaimer-text">
                This contract is generated and maintained by the JobHub Freelance Platform for administrative and record-keeping purposes. While this document reflects the agreed-upon terms between the parties, it does not constitute legal advice. Both parties are encouraged to consult with legal counsel for matters requiring professional legal interpretation. The JobHub platform facilitates the transaction but is not a party to the underlying agreement between the Client and the Freelancer. This document was auto-generated on <?= $generatedDate ?> and reflects the contract state at the time of generation.
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="contract-footer">
        <div class="footer-left">
            <i data-lucide="briefcase" style="margin-right: 6px;"></i> JobHub Freelance Platform <span>&bull;</span> <?= $contractNumber ?>
        </div>
        <div class="footer-right">
            Generated on <?= $generatedDate ?> &bull; Page 1 of 1
        </div>
    </div>

</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadPDF() {
    var btn = document.getElementById('pdfBtn');
    var txt = document.getElementById('pdfBtnText');
    btn.disabled = true;
    txt.textContent = 'Generating...';

    var element = document.getElementById('contractPage');
    var opt = {
        margin: 0,
        filename: 'Contract_<?= $contractId ?>_<?= $generatedDate ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, letterRendering: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
    };

    html2pdf().set(opt).from(element).save().then(function() {
        btn.disabled = false;
        txt.textContent = 'Download PDF';
    }).catch(function() {
        btn.disabled = false;
        txt.textContent = 'Download PDF';
        alert('PDF generation failed. Please use Print instead.');
    });
}
</script>
</body>
</html>
