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

$milestoneTitle = sanitize_string($milestone['title']);
$projectName    = sanitize_string($milestone['job_title']);
$milestoneAmount = format_currency((float) $milestone['amount']);
$milestoneDesc  = !empty($milestone['description']) ? sanitize_string($milestone['description']) : '';
$contractId     = $milestone['contract_id'];
?>

<main class="bg-slate-50 min-h-screen">
    <div class="max-w-6xl mx-auto py-10 px-4 sm:px-6">

        <!-- Back Link -->
        <a href="contract_detail.php?id=<?= $contractId ?>" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-indigo-600 transition-colors mb-8">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back to Contract
        </a>

        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Submit Milestone Work</h1>
            <p class="text-slate-500 text-sm mt-1">Deliver your completed work for client review and approval.</p>
        </div>

        <!-- Error Alert -->
        <div id="errorMsg" class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6 hidden" role="alert">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                <p class="text-sm text-red-700 font-medium" id="errorText"></p>
            </div>
        </div>

        <!-- 2-Column Grid -->
        <div class="grid lg:grid-cols-3 gap-8">

            <!-- ═══ LEFT COLUMN: Milestone Context ═══════════════ -->
            <aside class="lg:col-span-1 space-y-6">

                <!-- Milestone Details Card -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">Milestone</p>
                            <h2 class="text-sm font-bold text-slate-800 truncate"><?= $milestoneTitle ?></h2>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Project -->
                        <div>
                            <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mb-1">Project</p>
                            <p class="text-sm font-medium text-slate-700"><?= $projectName ?></p>
                        </div>

                        <!-- Amount -->
                        <div>
                            <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mb-1">Escrow Amount</p>
                            <p class="text-lg font-bold text-slate-800"><?= $milestoneAmount ?></p>
                        </div>

                        <!-- Escrow Badge -->
                        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 border border-emerald-200 rounded-full">
                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="text-xs font-semibold text-emerald-700">Escrow Funded</span>
                        </div>
                    </div>

                    <?php if ($milestoneDesc): ?>
                        <div class="mt-5 pt-5 border-t border-slate-100">
                            <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider mb-2">Description</p>
                            <p class="text-sm text-slate-600 leading-relaxed"><?= nl2br($milestoneDesc) ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Submission Guidelines Card -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                        Submission Guidelines
                    </h3>
                    <ul class="space-y-3">
                        <li class="flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            <span class="text-sm text-slate-600">Provide a public or private GitHub repository link</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            <span class="text-sm text-slate-600">Attach deliverables if the client requires files</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            <span class="text-sm text-slate-600">Include setup instructions or environment notes</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            <span class="text-sm text-slate-600">Client has 7 days to review or request revisions</span>
                        </li>
                    </ul>
                </div>
            </aside>

            <!-- ═══ RIGHT COLUMN: Submission Form ═══════════════ -->
            <section class="lg:col-span-2">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-8">
                    <div class="mb-8">
                        <h2 class="text-lg font-bold text-slate-800">Submission Details</h2>
                        <p class="text-sm text-slate-500 mt-1">Provide your deliverables and a summary of what you've built.</p>
                    </div>

                    <form id="submitForm" enctype="multipart/form-data" class="space-y-7">
                        <?= csrf_field() ?>
                        <input type="hidden" name="milestone_id" value="<?= $milestoneId ?>">

                        <!-- GitHub URL -->
                        <div>
                            <label for="github_url" class="block text-sm font-semibold text-slate-700 mb-2">
                                GitHub Repository URL <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-slate-400" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                                </div>
                                <input type="url" id="github_url" name="github_url" required
                                       placeholder="https://github.com/username/repo"
                                       class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all bg-slate-50 focus:bg-white">
                            </div>
                            <p class="text-xs text-slate-400 mt-1.5 ml-0.5">Link to your repository containing the completed work</p>
                        </div>

                        <!-- File Upload Dropzone -->
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">
                                File Attachment <span class="text-slate-400 font-normal">(optional)</span>
                            </label>
                            <input type="file" id="submission_file" name="submission_file"
                                   accept=".zip,.rar,.pdf,.doc,.docx,.txt,.png,.jpg,.jpeg,.gif"
                                   class="hidden">
                            <label for="submission_file" id="dropzone"
                                   class="group relative flex flex-col items-center justify-center w-full border-2 border-dashed border-slate-300 rounded-xl p-8 text-center cursor-pointer transition-all hover:border-indigo-400 hover:bg-indigo-50/30">
                                <!-- Upload Icon -->
                                <div id="dropzoneIcon" class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center mb-4 transition-all group-hover:bg-indigo-100">
                                    <svg class="w-7 h-7 text-slate-400 group-hover:text-indigo-500 transition-colors" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"/></svg>
                                </div>
                                <p class="text-sm font-medium text-slate-700 mb-1" id="fileLabel">Drop file here or <span class="text-indigo-600 font-semibold">browse</span></p>
                                <p class="text-xs text-slate-400">ZIP, RAR, PDF, DOC, TXT, images up to 10MB</p>
                                <!-- File Info (hidden by default) -->
                                <div id="fileInfo" class="hidden mt-4 flex items-center gap-3 bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-2.5 w-full max-w-sm">
                                    <svg class="w-5 h-5 text-indigo-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-indigo-700 truncate" id="fileName"></p>
                                        <p class="text-xs text-indigo-500" id="fileSize"></p>
                                    </div>
                                    <button type="button" id="removeFile" class="text-indigo-400 hover:text-indigo-600 transition-colors p-1" onclick="event.preventDefault(); event.stopPropagation(); clearFile();">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </label>
                        </div>

                        <!-- Submission Note -->
                        <div>
                            <label for="submission_note" class="block text-sm font-semibold text-slate-700 mb-2">
                                Submission Note <span class="text-slate-400 font-normal">(optional)</span>
                            </label>
                            <textarea id="submission_note" name="submission_note" rows="5" maxlength="2000"
                                      class="w-full px-4 py-3 rounded-xl border border-slate-200 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all resize-none bg-slate-50 focus:bg-white leading-relaxed"
                                      placeholder="Describe what you've completed, any setup instructions, or notes for the client..."></textarea>
                            <div class="flex items-center gap-1">
                                <p class="text-xs font-medium tabular-nums text-slate-400" id="charCount">0</p>
                                <p class="text-xs text-slate-400">/ 2000</p>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                            <a href="contract_detail.php?id=<?= $contractId ?>"
                               class="px-5 py-2.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors">
                                Cancel
                            </a>
                            <button type="submit" id="submitBtn"
                                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg px-6 py-2.5 shadow-sm transition-all hover:shadow-md active:scale-[0.98]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                                <span id="submitBtnText">Submit Work</span>
                                <svg id="submitSpinner" class="hidden animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                            </button>
                        </div>
                    </form>
                </div>
            </section>

        </div>
    </div>
</main>

<script>
var CSRF_TOKEN = '<?= generate_csrf_token() ?>';
var BASE_URL = '/jobhub';

// ── Dropzone Visual States ──────────────────────────────────
var dropzone = document.getElementById('dropzone');
var fileInput = document.getElementById('submission_file');

['dragenter', 'dragover'].forEach(function(evt) {
    dropzone.addEventListener(evt, function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.add('border-indigo-400', 'bg-indigo-50');
        dropzone.classList.remove('border-slate-300');
        document.getElementById('dropzoneIcon').classList.add('bg-indigo-100');
        document.getElementById('dropzoneIcon').classList.remove('bg-slate-100');
    });
});

['dragleave', 'drop'].forEach(function(evt) {
    dropzone.addEventListener(evt, function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.remove('border-indigo-400', 'bg-indigo-50');
        dropzone.classList.add('border-slate-300');
        document.getElementById('dropzoneIcon').classList.remove('bg-indigo-100');
        document.getElementById('dropzoneIcon').classList.add('bg-slate-100');
    });
});

dropzone.addEventListener('drop', function(e) {
    var files = e.dataTransfer.files;
    if (files.length) {
        fileInput.files = files;
        showFileInfo(files[0]);
    }
});

fileInput.addEventListener('change', function() {
    if (this.files && this.files[0]) {
        showFileInfo(this.files[0]);
    }
});

function showFileInfo(file) {
    var sizeMB = (file.size / 1024 / 1024).toFixed(2);
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = sizeMB + ' MB';
    document.getElementById('fileInfo').classList.remove('hidden');
    document.getElementById('fileLabel').innerHTML = '<span class="text-indigo-600 font-semibold">Change file</span>';
}

function clearFile() {
    fileInput.value = '';
    document.getElementById('fileInfo').classList.add('hidden');
    document.getElementById('fileLabel').innerHTML = 'Drop file here or <span class="text-indigo-600 font-semibold">browse</span>';
}

// ── Character Counter ───────────────────────────────────────
var noteField = document.getElementById('submission_note');
var charCount = document.getElementById('charCount');

noteField.addEventListener('input', function() {
    var len = this.value.length;
    charCount.textContent = len;
    if (len > 1800) {
        charCount.className = 'text-xs font-medium tabular-nums text-amber-500';
    } else {
        charCount.className = 'text-xs font-medium tabular-nums text-slate-400';
    }
});

// ── Form Submission ─────────────────────────────────────────
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
                window.location.href = 'contract_detail.php?id=' + <?= $contractId ?>;
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

function showError(msg) {
    var el = document.getElementById('errorMsg');
    document.getElementById('errorText').textContent = msg;
    el.classList.remove('hidden');
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function showToast(type, message) {
    var colors = { success: 'bg-emerald-500', error: 'bg-red-500', info: 'bg-indigo-500' };
    var icons = {
        success: '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        error: '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>',
        info: '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>'
    };
    var t = document.createElement('div');
    t.className = 'fixed top-4 right-4 z-[70] flex items-center gap-3 px-5 py-3.5 rounded-xl text-white text-sm font-semibold shadow-2xl ' + colors[type] + ' transition-all transform translate-x-full';
    t.innerHTML = icons[type] + ' ' + message;
    document.body.appendChild(t);
    requestAnimationFrame(function() { t.classList.remove('translate-x-full'); });
    setTimeout(function() { t.classList.add('translate-x-full'); setTimeout(function() { t.remove(); }, 300); }, 3500);
}
</script>
<?php $conn->close(); ?>
<?php require_once __DIR__ . '/../components/freelancer_footer.php'; ?>
