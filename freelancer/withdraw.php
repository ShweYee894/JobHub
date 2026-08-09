<?php
$activePage = 'earnings';
require_once __DIR__ . '/../auth/auth.php';
require_role('freelancer');
require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'];

$uStmt = $conn->prepare('SELECT name, email, profile_image, wallet_balance FROM users WHERE id = ?');
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

$walletBalance = (float) $user['wallet_balance'];

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM wallet_transactions WHERE user_id = ? AND type = 'withdrawal' AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
$stmt->bind_param('i', $userId);
$stmt->execute();
$monthWithdrawn = (float) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$countSql = "SELECT COUNT(*) AS total FROM wallet_transactions WHERE user_id = ? AND type = 'withdrawal'";
$countStmt = $conn->prepare($countSql);
$countStmt->bind_param('i', $userId);
$countStmt->execute();
$totalWithdrawals = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();
$pagination = paginate($totalWithdrawals, $perPage, $page);
$offset = $pagination['offset'];

$stmt = $conn->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? AND type = 'withdrawal' ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param('iii', $userId, $perPage, $offset);
$stmt->execute();
$withdrawalsResult = $stmt->get_result();
$stmt->close();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid security token.';
    } else {
        $amount = sanitize_float($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            $errors[] = 'Amount must be greater than zero.';
        } elseif ($amount > $walletBalance) {
            $errors[] = 'Insufficient wallet balance. Your balance is ' . format_currency($walletBalance) . '.';
        } elseif ($amount < 10) {
            $errors[] = 'Minimum withdrawal amount is $10.00.';
        } else {
            $newBalance = $walletBalance - $amount;
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare('UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?');
                $stmt->bind_param('did', $amount, $userId, $amount);
                $stmt->execute();
                if ($stmt->affected_rows === 0) {
                    throw new Exception('Insufficient balance (concurrent modification).');
                }
                $stmt->close();

                $newBalance = $walletBalance - $amount;
                $desc = 'Withdrawal of ' . format_currency($amount);
                $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, type, amount, balance_after, reference_id, reference_type, description, created_at) VALUES (?, 'withdrawal', ?, ?, NULL, 'wallet', ?, NOW())");
                $stmt->bind_param('idds', $userId, $amount, $newBalance, $desc);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $success = true;
                $walletBalance = $newBalance;
                set_flash('success', 'Withdrawal of ' . format_currency($amount) . ' processed successfully.');
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'Failed to process withdrawal. Please try again.';
            }
        }
    }
}

$pageTitle = 'Withdraw Funds';
$pageSubtitle = 'Transfer earnings to your bank account';
$activePage = 'earnings';
$user = ['name' => $user['name'] ?? 'Freelancer', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'freelancer');
require_once __DIR__ . '/../components/freelancer_header.php';
$conn->close();
?>
    <style>
        * { font-family:'Inter',system-ui,-apple-system,sans-serif; }
        .wd-card { background:#fff; border:1px solid #E5E8EB; border-radius:10px; padding:24px; }
        .wd-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .wd-title { font-size:16px; font-weight:700; color:#1A1A2E; margin:0; }
        .wd-sub { font-size:12px; color:#9CA3AF; margin:2px 0 0; }
        .wd-bal { background:#108A00; opacity: 0.9; border-radius:10px; padding:24px; color:#fff; margin-bottom:24px; }
        .wd-bal-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:rgba(255,255,255,.6); margin:0 0 8px; }
        .wd-bal-val { font-size:32px; font-weight:900; margin:0; line-height:1.1; font-variant-numeric:tabular-nums; }
        .wd-bal-note { font-size:12px; color:rgba(255,255,255,.5); margin:8px 0 0; }
        .wd-input-wrap { position:relative; }
        .wd-input-wrap .wd-prefix { position:absolute; left:16px; top:50%; transform:translateY(-50%); color:#9CA3AF; font-size:14px; font-weight:600; pointer-events:none; }
        .wd-input { width:100%; padding:14px 16px 14px 36px; border:1px solid #D1D5DB; border-radius:8px; font-size:15px; color:#1A1A2E; background:#F9FAFB; outline:none; transition:all .15s ease; box-sizing:border-box; }
        .wd-input:focus { border-color:#108A00; background:#fff; box-shadow:0 0 0 3px rgba(16,138,0,.1); }
        .wd-hint { font-size:12px; color:#9CA3AF; margin:8px 0 0; }
        .wd-quick { display:flex; gap:8px; }
        .wd-quick button { flex:1; padding:10px; border:1px solid #D1D5DB; border-radius:8px; background:#fff; color:#374151; font-size:13px; font-weight:600; cursor:pointer; transition:all .12s ease; }
        .wd-quick button:hover { background:#F3F4F6; border-color:#9CA3AF; }
        .wd-submit { width:100%; padding:14px; border:none; border-radius:8px; background:#108A00; color:#fff; font-size:14px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:all .15s ease; }
        .wd-submit:hover { background:#0D7200; box-shadow:0 4px 12px rgba(16,138,0,.25); }
        .wd-submit:disabled { opacity:.5; cursor:not-allowed; }
        .wd-note { padding:14px 16px; background:#F0FDF4; border:1px solid #DCFCE7; border-radius:8px; margin-top:16px; }
        .wd-note p { font-size:12px; color:#166534; margin:0; display:flex; align-items:flex-start; gap:8px; line-height:1.6; }
        .wd-h-item { display:flex; align-items:center; gap:14px; padding:14px 16px; background:#F9FAFB; border:1px solid #E5E8EB; border-radius:8px; transition:background .12s ease; }
        .wd-h-item:hover { background:#F3F4F6; }
        .wd-h-icon { width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; background:#FEE2E2; }
        .wd-h-text { flex:1; min-width:0; }
        .wd-h-title { font-size:13px; font-weight:600; color:#1A1A2E; margin:0; }
        .wd-h-date { font-size:11px; color:#9CA3AF; margin:2px 0 0; }
        .wd-h-amount { text-align:right; flex-shrink:0; }
        .wd-h-amt { font-size:13px; font-weight:700; color:#DC2626; margin:0; }
        .wd-h-bal { font-size:11px; color:#9CA3AF; margin:2px 0 0; }
        .wd-empty { text-align:center; padding:48px 24px; }
        .wd-empty-icon { width:56px; height:56px; border-radius:14px; background:#F3F4F6; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; }
        .wd-empty-title { font-size:14px; font-weight:600; color:#6B7280; margin:0; }
        .wd-empty-sub { font-size:12px; color:#9CA3AF; margin:4px 0 0; }
        .wd-back { display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:500; color:#6B7280; text-decoration:none; transition:color .12s ease; }
        .wd-back:hover { color:#1A1A2E; }
    </style>

    <div style="background:#fff;min-height:100vh">
    <div class="max-w-7xl mx-auto px-5 sm:px-8 py-10 sm:py-14">

    <!-- Back Link -->
    <a href="earnings.php" class="wd-back" style="margin-bottom:24px">
        <i data-lucide="arrow-left" class="text-[11px]"></i> Back to transactions
    </a>

    <?php display_flash('success') ?>
    <?php display_flash('error') ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Withdraw Form -->
        <div class="lg:col-span-1">
            <div class="wd-card">
                <div class="flex items-center gap-3" style="margin-bottom:20px">
                    <div class="wd-icon" style="background:#F0FDF4"><i data-lucide="wallet" style="color:#108A00"></i></div>
                    <div>
                        <h3 class="wd-title">Withdraw Funds</h3>
                        <p class="wd-sub">Transfer to your bank account</p>
                    </div>
                </div>

                <!-- Balance Card -->
                <div class="wd-bal">
                    <p class="wd-bal-label">Available Balance</p>
                    <p class="wd-bal-val"><?= format_currency($walletBalance) ?></p>
                    <p class="wd-bal-note">This month: <?= format_currency($monthWithdrawn) ?> withdrawn</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;padding:14px 16px;margin-bottom:16px">
                        <div style="display:flex;align-items:flex-start;gap:10px">
                            <i data-lucide="circle-alert" style="color:#DC2626;margin-top:2px"></i>
                            <div style="font-size:13px;color:#991B1B">
                                <?php foreach ($errors as $err): ?>
                                    <p style="margin:0 0 2px"><?= sanitize_string($err) ?></p>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <?= csrf_field() ?>
                    <div style="margin-bottom:16px">
                        <label for="amount" style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:8px">Withdrawal Amount</label>
                        <div class="wd-input-wrap">
                            <span class="wd-prefix">$</span>
                            <input type="number" id="amount" name="amount" step="0.01" min="10" max="<?= $walletBalance ?>"
                                   class="wd-input"
                                   placeholder="0.00" required>
                        </div>
                        <p class="wd-hint">Minimum: $10.00 | Maximum: <?= format_currency($walletBalance) ?></p>
                    </div>

                    <div class="wd-quick" style="margin-bottom:20px">
                        <button type="button" onclick="document.getElementById('amount').value='<?= $walletBalance ?>'">Max</button>
                        <button type="button" onclick="document.getElementById('amount').value=Math.min(100, <?= $walletBalance ?>)">$100</button>
                        <button type="button" onclick="document.getElementById('amount').value=Math.min(500, <?= $walletBalance ?>)">$500</button>
                    </div>

                    <button type="submit" class="wd-submit" <?= $walletBalance < 10 ? 'disabled' : '' ?>>
                        <i data-lucide="banknote" style="font-size:12px"></i> Withdraw Funds
                    </button>
                </form>

                <div class="wd-note">
                    <p>
                        <i data-lucide="info" style="margin-top:2px;flex-shrink:0"></i>
                        <span>Withdrawals are processed within 1-3 business days. Funds will be transferred to your registered payment method.</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Withdrawal History -->
        <div class="lg:col-span-2">
            <div class="wd-card">
                <div class="flex items-center gap-3" style="margin-bottom:20px">
                    <div class="wd-icon" style="background:#F3F4F6"><i data-lucide="history" style="color:#6B7280"></i></div>
                    <div>
                        <h2 class="wd-title">Withdrawal History</h2>
                        <p class="wd-sub"><?= $totalWithdrawals ?> withdrawal<?= $totalWithdrawals !== 1 ? 's' : '' ?></p>
                    </div>
                </div>

                <?php if ($withdrawalsResult->num_rows > 0): ?>
                    <div style="display:flex;flex-direction:column;gap:10px">
                        <?php while ($w = $withdrawalsResult->fetch_assoc()): ?>
                            <div class="wd-h-item">
                                <div class="wd-h-icon">
                                    <i data-lucide="arrow-up" style="color:#DC2626;font-size:13px"></i>
                                </div>
                                <div class="wd-h-text">
                                    <p class="wd-h-title">Withdrawal</p>
                                    <p class="wd-h-date"><?= date('M d, Y h:i A', strtotime($w['created_at'])) ?></p>
                                </div>
                                <div class="wd-h-amount">
                                    <p class="wd-h-amt">-<?= format_currency((float) $w['amount']) ?></p>
                                    <p class="wd-h-bal">Balance: <?= format_currency((float) $w['balance_after']) ?></p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <?php if ($pagination['total_pages'] > 1): ?>
                        <div class="flex items-center justify-between" style="margin-top:20px;padding-top:16px;border-top:1px solid #E5E8EB">
                            <p style="font-size:12px;color:#9CA3AF;margin:0">Page <span style="font-weight:600;color:#6B7280"><?= $pagination['current_page'] ?></span> of <span style="font-weight:600;color:#6B7280"><?= $pagination['total_pages'] ?></span></p>
                            <div class="flex items-center gap-1">
                                <?php if ($pagination['has_prev']): ?>
                                    <a href="?page=<?= $pagination['current_page'] - 1 ?>" style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;border:1px solid #D1D5DB;color:#6B7280;font-size:12px"><i data-lucide="chevron-left" style="font-size:10px"></i></a>
                                <?php endif; ?>
                                <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                                    <a href="?page=<?= $i ?>" style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;font-size:13px;font-weight:500;<?= $i === $pagination['current_page'] ? 'background:#108A00;color:#fff' : 'color:#6B7280;border:1px solid #E5E8EB' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                                <?php if ($pagination['has_next']): ?>
                                    <a href="?page=<?= $pagination['current_page'] + 1 ?>" style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;border:1px solid #D1D5DB;color:#6B7280;font-size:12px"><i data-lucide="chevron-right" style="font-size:10px"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="wd-empty">
                        <div class="wd-empty-icon">
                            <i data-lucide="history" style="font-size:20px;color:#D1D5DB"></i>
                        </div>
                        <p class="wd-empty-title">No withdrawals yet</p>
                        <p class="wd-empty-sub">Your withdrawal history will appear here</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    </div>
    </div>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>
