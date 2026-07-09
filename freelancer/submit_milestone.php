<?php
$activePage = 'contracts';
require_once __DIR__ . '/../auth/auth.php';
require_role('freelancer');
require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'];
$milestoneId = intval($_GET['id'] ?? 0);

if ($milestoneId <= 0) {
    set_flash('error', 'Invalid milestone.');
    redirect('contracts.php');
}

$uStmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

$stmt = $conn->prepare('SELECT m.*, c.client_id, c.freelancer_id, c.total_budget, c.id AS contract_id, j.title AS job_title FROM milestones m JOIN contracts c ON m.contract_id = c.id JOIN jobs j ON c.job_id = j.id WHERE m.id = ? AND c.freelancer_id = ?');
$stmt->bind_param('ii', $milestoneId, $userId);
$stmt->execute();
$milestone = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$milestone) {
    set_flash('error', 'Milestone not found or access denied.');
    redirect('contracts.php');
}

if ($milestone['status'] !== 'funded_in_escrow') {
    set_flash('error', 'This milestone cannot be submitted in its current status.');
    redirect('contract_detail.php?id=' . $milestone['contract_id']);
}

$pageTitle = 'Submit Milestone Work';
$pageSubtitle = 'Submit your completed work for review';
$activePage = 'contracts';
$user = ['name' => $user['name'] ?? 'Freelancer', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'freelancer');
require_once __DIR__ . '/../components/freelancer_header.php';
?>
    <div class="max-w-2xl mx-auto w-full">

        <!-- Milestone Summary -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-6 mb-6 fade-in">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-paper-plane text-white"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <a href="contract_detail.php?id=<?= $milestone['contract_id'] ?>" class="text-xs text-blue-600 hover:text-blue-700 font-medium mb-1 inline-flex items-center gap-1">
                        <i class="fas fa-arrow-left text-[10px]"></i> Back to Contract
                    </a>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white truncate"><?= sanitize_string($milestone['title']) ?></h3>
                    <div class="flex items-center gap-4 text-xs text-gray-400 mt-1">
                        <span class="flex items-center gap-1">
                            <i class="fas fa-briefcase text-blue-400"></i>
                            <?= sanitize_string($milestone['job_title']) ?>
                        </span>
                        <span class="flex items-center gap-1">
                            <i class="fas fa-dollar-sign text-emerald-500"></i>
                            <span class="font-semibold text-gray-700"><?= format_currency((float) $milestone['amount']) ?></span>
                        </span>
                    </div>
                </div>
            </div>
            <?php if (!empty($milestone['description'])): ?>
                <div class="mt-4 p-3 bg-gray-50 dark:bg-slate-700/50 rounded-xl border border-gray-100 dark:border-slate-600">
                    <p class="text-xs text-gray-400 font-semibold mb-1">Milestone Description</p>
                    <p class="text-sm text-gray-600 dark:text-slate-300"><?= nl2br(sanitize_string($milestone['description'])) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Submission Form -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-6 fade-in" style="animation-delay:.1s">
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2">
                <i class="fas fa-upload text-blue-500"></i> Submission Details
            </h3>

            <div id="errorMsg" class="bg-red-50 text-red-800 border border-red-200 rounded-xl p-4 mb-6 hidden">
                <div class="flex items-start gap-3">
                    <i class="fas fa-exclamation-circle mt-0.5"></i>
                    <div class="text-sm" id="errorText"></div>
                </div>
            </div>

            <form id="submitForm" enctype="multipart/form-data" class="space-y-5">
                <?= csrf_field() ?>
                <input type="hidden" name="milestone_id" value="<?= $milestoneId ?>">

                <!-- GitHub URL -->
                <div>
                    <label for="github_url" class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-2">
                        GitHub Repository URL <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <i class="fab fa-github absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="url" id="github_url" name="github_url" required
                               placeholder="https://github.com/username/repo"
                               class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 dark:border-slate-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all bg-gray-50 dark:bg-slate-700 focus:bg-white dark:focus:bg-slate-600 dark:text-white">
                    </div>
                    <p class="text-xs text-gray-400 mt-1.5">Link to your repository containing the completed work</p>
                </div>

                <!-- File Attachment -->
                <div>
                    <label for="submission_file" class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-2">
                        File Attachment <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <div class="relative">
                        <input type="file" id="submission_file" name="submission_file"
                               accept=".zip,.rar,.pdf,.doc,.docx,.txt,.png,.jpg,.jpeg,.gif"
                               class="hidden" onchange="updateFileName(this)">
                        <label for="submission_file" class="flex items-center gap-3 w-full px-4 py-3 rounded-xl border border-dashed border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-700 hover:bg-white dark:hover:bg-slate-600 hover:border-blue-400 cursor-pointer transition-all">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-cloud-upload-alt text-blue-500"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-700" id="fileLabel">Choose file to upload</p>
                                <p class="text-[11px] text-gray-400">ZIP, RAR, PDF, DOC, TXT, images (max 10MB)</p>
                            </div>
                            <i class="fas fa-paperclip text-gray-400"></i>
                        </label>
                    </div>
                </div>

                <!-- Submission Note -->
                <div>
                    <label for="submission_note" class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-2">
                        Submission Note <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <textarea id="submission_note" name="submission_note" rows="5" maxlength="2000"
                              class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-slate-600 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none bg-gray-50 dark:bg-slate-700 focus:bg-white dark:focus:bg-slate-600 dark:text-white"
                              placeholder="Describe what you've completed, any setup instructions, or notes for the client..."></textarea>
                    <div class="flex justify-between mt-1.5">
                        <p class="text-xs text-gray-400">Include any relevant details about your submission</p>
                        <p class="text-xs text-gray-400"><span id="charCount">0</span>/2000</p>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex items-center gap-3 pt-2">
                    <a href="contract_detail.php?id=<?= $milestone['contract_id'] ?>" class="px-6 py-3 border border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-300 rounded-xl text-sm font-semibold hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" id="submitBtn" class="btn-grad px-6 py-3 text-white rounded-xl text-sm font-bold flex items-center gap-2">
                        <i class="fas fa-paper-plane text-[10px]"></i>
                        <span id="submitBtnText">Submit Work</span>
                        <i class="fas fa-spinner fa-spin text-[10px] hidden" id="submitSpinner"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

<script>
var CSRF_TOKEN = '<?= generate_csrf_token() ?>';
var BASE_URL = '/finalproject';

function updateFileName(input) {
    var label = document.getElementById('fileLabel');
    if (input.files && input.files[0]) {
        var size = (input.files[0].size / 1024 / 1024).toFixed(2);
        label.textContent = input.files[0].name + ' (' + size + ' MB)';
        label.classList.add('text-blue-600');
    } else {
        label.textContent = 'Choose file to upload';
        label.classList.remove('text-blue-600');
    }
}

document.getElementById('submission_note').addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
});

document.getElementById('submitForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    var githubUrl = document.getElementById('github_url').value.trim();
    if (!githubUrl) {
        showError('GitHub repository URL is required.');
        return;
    }
    if (!/^https?:\/\/.+/i.test(githubUrl)) {
        showError('Please enter a valid URL.');
        return;
    }

    var btn = document.getElementById('submitBtn');
    var txt = document.getElementById('submitBtnText');
    var spinner = document.getElementById('submitSpinner');
    btn.disabled = true;
    txt.textContent = 'Submitting...';
    spinner.classList.remove('hidden');

    var formData = new FormData(this);
    formData.append('action', 'submit');
    formData.append('csrf_token', CSRF_TOKEN);

    try {
        var response = await fetch(BASE_URL + '/api/milestones_api.php', {
            method: 'POST',
            body: formData
        });
        var data = await response.json();

        if (data.success) {
            showToast('success', data.message);
            setTimeout(function() {
                window.location.href = 'contract_detail.php?id=' + <?= $milestone['contract_id'] ?>;
            }, 1500);
        } else {
            showError(data.message);
            btn.disabled = false;
            txt.textContent = 'Submit Work';
            spinner.classList.add('hidden');
        }
    } catch (err) {
        showError('Network error. Please try again.');
        btn.disabled = false;
        txt.textContent = 'Submit Work';
        spinner.classList.add('hidden');
    }
});
<?php $conn->close(); ?>
function showError(msg) {
    var el = document.getElementById('errorMsg');
    document.getElementById('errorText').textContent = msg;
    el.classList.remove('hidden');
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function showToast(type, message) {
    var colors = { success: 'bg-emerald-500', error: 'bg-red-500', info: 'bg-blue-500' };
    var icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle' };
    var t = document.createElement('div');
    t.className = 'fixed top-4 right-4 z-[70] flex items-center gap-3 px-5 py-3 rounded-xl text-white text-sm font-semibold shadow-2xl ' + colors[type] + ' transition-all transform translate-x-full';
    t.innerHTML = '<i class="fas ' + icons[type] + '"></i> ' + message;
    document.body.appendChild(t);
    requestAnimationFrame(function() { t.classList.remove('translate-x-full'); });
    setTimeout(function() { t.classList.add('translate-x-full'); setTimeout(function() { t.remove(); }, 300); }, 3500);
}
</script>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>
