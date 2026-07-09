<?php
$page_title = 'Create Milestone';
require_once __DIR__ . '/../auth/auth.php';
require_role('client');
require_once __DIR__ . '/../config/db.php';

$currentPage = 'contracts';
$userId = $_SESSION['user_id'];
$contractId = isset($_GET['contract_id']) ? sanitize_int($_GET['contract_id']) : 0;

if ($contractId <= 0) {
    set_flash('error', 'Invalid contract.');
    redirect('/finalproject/client/contracts.php');
}

$stmt = $conn->prepare('
    SELECT c.id, c.total_budget, c.status, j.title AS job_title
    FROM contracts c
    JOIN jobs j ON c.job_id = j.id
    WHERE c.id = ? AND c.client_id = ?
');
$stmt->bind_param('ii', $contractId, $userId);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$contract) {
    set_flash('error', 'Contract not found or access denied.');
    redirect('/finalproject/client/contracts.php');
}

if ($contract['status'] !== 'active') {
    set_flash('error', 'Milestones can only be added to active contracts.');
    redirect('/finalproject/client/contract_detail.php?id=' . $contractId);
}

$mStmt = $conn->prepare('SELECT COALESCE(SUM(amount), 0) AS total_milestones FROM milestones WHERE contract_id = ?');
$mStmt->bind_param('i', $contractId);
$mStmt->execute();
$totalExisting = (float) $mStmt->get_result()->fetch_assoc()['total_milestones'];
$mStmt->close();

$errors = [];
$title = '';
$amount = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $amount = trim($_POST['amount'] ?? '');

        if (empty($title)) {
            $errors[] = 'Milestone title is required.';
        } elseif (mb_strlen($title) > 255) {
            $errors[] = 'Milestone title must not exceed 255 characters.';
        }

        if (!is_numeric($amount) || (float) $amount <= 0) {
            $errors[] = 'Amount must be a positive number.';
        } else {
            $amount = (float) $amount;
            if ($totalExisting + $amount > (float) $contract['total_budget']) {
                $remaining = (float) $contract['total_budget'] - $totalExisting;
                $errors[] = 'Total milestones cannot exceed the contract budget. Remaining budget: ' . format_currency($remaining) . '.';
            }
        }

        if (empty($errors)) {
            $insert = $conn->prepare('INSERT INTO milestones (contract_id, title, amount, status, created_at, updated_at) VALUES (?, ?, ?, "pending", NOW(), NOW())');
            $insert->bind_param('isd', $contractId, $title, $amount);
            if ($insert->execute()) {
                $insert->close();
                $conn->close();
                set_flash('success', 'Milestone created successfully!');
                redirect('/finalproject/client/contract_detail.php?id=' . $contractId);
            } else {
                $errors[] = 'Failed to create milestone. Please try again.';
            }
            $insert->close();
        }
    }
}

$conn->close();

$pageTitle = 'Create Milestone';
$pageSubtitle = 'Add a milestone to your contract';
$activePage = 'contracts';
$user = ['name' => $_SESSION['user_name'] ?? 'Client', 'profile_image' => $_SESSION['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
$profileLink = 'profile.php';
require_once __DIR__ . '/../includes/client_topbar.php';
?>
    <div class="max-w-2xl mx-auto w-full">

        <!-- Contract Summary -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-6 mb-6 fade-in">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-handshake text-white"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <a href="contract_detail.php?id=<?= $contractId ?>" class="text-xs text-blue-600 hover:text-blue-700 font-medium mb-1 inline-flex items-center gap-1">
                        <i class="fas fa-arrow-left text-[10px]"></i> Back to Contract
                    </a>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white truncate"><?= sanitize_string($contract['job_title']) ?></h3>
                    <div class="flex items-center gap-4 text-xs text-gray-400 mt-1">
                        <span class="flex items-center gap-1">
                            <i class="fas fa-wallet text-blue-400"></i>
                            Budget: <span class="font-semibold text-gray-700"><?= format_currency((float) $contract['total_budget']) ?></span>
                        </span>
                        <span class="flex items-center gap-1">
                            <i class="fas fa-flag text-violet-400"></i>
                            Remaining: <span class="font-semibold text-green-600"><?= format_currency((float) $contract['total_budget'] - $totalExisting) ?></span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-6 fade-in" style="animation-delay:.1s">
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2">
                <i class="fas fa-flag text-blue-500"></i> Milestone Details
            </h3>

            <?php if (!empty($errors)): ?>
            <div class="bg-red-50 text-red-800 border border-red-200 rounded-xl p-4 mb-6">
                <div class="flex items-start gap-3">
                    <i class="fas fa-exclamation-circle mt-0.5"></i>
                    <div class="text-sm">
                        <?php foreach ($errors as $err): ?>
                        <p><?= sanitize_string($err) ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-5">
                <?= csrf_field() ?>
                <input type="hidden" name="contract_id" value="<?= $contractId ?>">

                <div>
                    <label for="title" class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-2">Milestone Title</label>
                    <input type="text" id="title" name="title" value="<?= sanitize_string($title) ?>"
                           class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-slate-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all bg-gray-50 dark:bg-slate-700 focus:bg-white dark:focus:bg-slate-600 dark:text-white"
                           placeholder="e.g., Design mockups delivery" required maxlength="255">
                    <p class="text-xs text-gray-400 mt-1.5">A clear, descriptive name for this milestone</p>
                </div>

                <div>
                    <label for="amount" class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-2">Amount ($)</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-semibold">$</span>
                        <input type="number" id="amount" name="amount" value="<?= sanitize_string($amount) ?>" step="0.01" min="0.01"
                               class="w-full pl-8 pr-4 py-3 rounded-xl border border-gray-200 dark:border-slate-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all bg-gray-50 dark:bg-slate-700 focus:bg-white dark:focus:bg-slate-600 dark:text-white"
                               placeholder="0.00" required>
                    </div>
                    <p class="text-xs text-gray-400 mt-1.5">Maximum: <?= format_currency((float) $contract['total_budget'] - $totalExisting) ?></p>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <a href="contract_detail.php?id=<?= $contractId ?>" class="px-6 py-3 border border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-300 rounded-xl text-sm font-semibold hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="btn-grad px-6 py-3 text-white rounded-xl text-sm font-bold">
                        <i class="fas fa-plus mr-1.5 text-[10px]"></i> Create Milestone
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>
