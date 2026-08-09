<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: ../auth/login.php');
    exit();
}

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$allSkills = $conn->query('SELECT id, skill_name, category FROM skills ORDER BY category, skill_name');

// Get categories for job categories
$allCategories = $conn->query('SELECT DISTINCT category FROM skills WHERE category IS NOT NULL ORDER BY category');

$errors = $_SESSION['job_errors'] ?? [];
$form = $_SESSION['job_form'] ?? [];
unset($_SESSION['job_errors'], $_SESSION['job_form']);

$pageTitle = 'Post a Job';
$pageSubtitle = 'Describe your project and find the best freelancers.';
$activePage = 'post_job';
$user = ['name' => $user['name'] ?? 'Client', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = get_unread_message_count($userId, 'client');
$profileLink = 'profile.php';
require_once __DIR__ . '/../includes/client_topbar.php';

// Get selected skills for form persistence
$selectedSkills = $form['skills'] ?? [];
?>
<?php display_flash('success');
display_flash('error'); ?>

<?php if (!empty($errors)): ?>
    <div class="rounded-2xl p-4 bg-red-50 border border-red-200 fade-in dark:bg-red-900/30 dark:border-red-800 mb-6">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-[10px] bg-red-100 flex items-center justify-center flex-shrink-0 dark:bg-red-900/50"><i data-lucide="triangle-alert" class="w-5 h-5 text-red-500"></i></div>
            <div>
                <p class="font-bold text-red-700 text-sm mb-1 dark:text-red-300">Please fix the following errors:</p>
                <ul class="space-y-1"><?php foreach ($errors as $e): ?><li class="text-red-600 text-xs flex items-start gap-2 dark:text-red-400"><i data-lucide="circle" class="w-2 h-2 mt-1.5 text-red-400 flex-shrink-0"></i><?= sanitize_string($e) ?></li><?php endforeach; ?></ul>
            </div>
        </div>
    </div>
<?php endif; ?>

<form action="save_job.php" method="POST" id="job-form" class="max-w-6xl mx-auto mt-8">
    <?= csrf_field() ?>

    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="dashboard.php" class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors no-underline">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
            </a>
            <h1 class="text-2xl font-extrabold text-slate-800 dark:text-white m-0">Create Job Posting</h1>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Job Title Section -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700/50 p-6 fade-in">
                <div class="flex flex-col sm:flex-row gap-6">
                    <div class="flex-1">
                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1">Job post title</label>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mb-1">Create a strong job post title</p>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mb-3">This help your jobs post stand out to the right applicants.</p>
                        <input type="text" id="title" name="title" required value="<?= sanitize_string($form['title'] ?? '') ?>" placeholder="e.g. Legal Counsel for Law Issues" maxlength="255" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-900 dark:text-white placeholder-slate-400 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all">
                        <p id="title-err" class="text-red-500 text-xs mt-1.5 hidden"></p>
                    </div>
                </div>
            </div>

            <!-- Description Section -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700/50 p-6 fade-in" style="animation-delay:.05s">
                <div class="flex flex-col sm:flex-row gap-6">
                    <div class="flex-1">
                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1">Description</label>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mb-3">Provide a brief and concise job description</p>
                        <!-- Hidden textarea to send value to server -->
                        <textarea id="description" name="description" class="hidden"><?= sanitize_string($form['description'] ?? '') ?></textarea>
                        <div class="w-full max-w-2xl font-sans">
                            <!-- The Fake Textarea Container -->
                            <div class="border border-[#E2E8F0] rounded-xl p-4 bg-white min-h-[240px] flex flex-col justify-between focus-within:border-blue-400 focus-within:ring-1 focus-within:ring-blue-400">
                                <!-- This editable div acts exactly like a textarea but allows styled text -->
                                <div
                                    id="editor"
                                    contenteditable="true"
                                    placeholder="Enter description for the job"
                                    class="w-full min-h-[140px] text-[#2D3748] text-md focus:outline-none break-words empty:before:content-[attr(placeholder)] empty:before:text-slate-400 empty:before:pointer-events-none"></div>
                                <!-- Formatting Toolbar fixed at the bottom left -->
                                <div class="flex items-center gap-1.5 mt-4 border-t border-[#F7FAFC] pt-3">
                                    <button type="button" onclick="formatText('bold')" class="w-8 h-8 rounded-lg border border-[#E2E8F0] flex items-center justify-center font-bold text-[#4A5568] hover:bg-[#F7FAFC] transition-colors focus:outline-none">B</button>
                                    <button type="button" onclick="formatText('italic')" class="w-8 h-8 rounded-lg border border-[#E2E8F0] flex items-center justify-center italic font-serif text-[#4A5568] hover:bg-[#F7FAFC] transition-colors focus:outline-none">I</button>
                                    <button type="button" onclick="formatText('underline')" class="w-8 h-8 rounded-lg border border-[#E2E8F0] flex items-center justify-center underline text-[#4A5568] hover:bg-[#F7FAFC] transition-colors focus:outline-none">U</button>
                                    <button type="button" onclick="formatText('strikeThrough')" class="w-8 h-8 rounded-lg border border-[#E2E8F0] flex items-center justify-center line-through text-[#4A5568] hover:bg-[#F7FAFC] transition-colors focus:outline-none">S</button>
                                </div>
                            </div>
                        </div>
                        <p id="description-err" class="text-red-500 text-xs mt-1.5 hidden"></p>
                    </div>
                </div>
            </div>

            <!-- Skills Section -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700/50 p-6 fade-in" style="animation-delay:.15s">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">Required Skills</h3>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Select the skills needed for this project</p>
                    </div>
                    <span id="skill-count" class="bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 text-xs font-semibold px-3 py-1.5 rounded-full transition-all">0 Selected</span>
                </div>

                <div class="relative mb-5">
                        <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 w-4 h-4"></i>
                    <input type="text" id="skill-search" placeholder="Type to filter skills..." class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl pl-11 pr-4 py-2.5 text-slate-900 dark:text-white placeholder-slate-400 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all">
                </div>

                <div class="space-y-4 max-h-[400px] overflow-y-auto pr-2" id="skills-container">
                    <?php
                    $grouped = [];
                    if ($allSkills && $allSkills->num_rows > 0) {
                        while ($s = $allSkills->fetch_assoc()) {
                            $grouped[$s['category']][] = $s;
                        }
                    }
                    if (!empty($grouped)):
                        foreach ($grouped as $cat => $list):
                            ?>
                            <div class="skill-category" data-cat="<?= sanitize_string(strtolower($cat)) ?>">
                                <div class="flex items-center gap-2 mb-2.5">
                                    <span class="w-1 h-4 rounded-full bg-violet-500"></span>
                                    <h4 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider"><?= sanitize_string($cat) ?></h4>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($list as $skill):
                                        $isSelected = in_array($skill['id'], $selectedSkills); ?>
                                        <label class="skill-tag inline-flex items-center gap-1.5 border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 text-xs text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-700 cursor-pointer select-none transition-all hover:border-violet-400 hover:bg-violet-50 dark:hover:bg-violet-900/20 <?= $isSelected ? 'skill-tag-active border-violet-500 bg-violet-50 dark:bg-violet-900/30' : '' ?>" data-id="<?= $skill['id'] ?>" data-name="<?= sanitize_string(strtolower($skill['skill_name'])) ?>">
                                            <input type="checkbox" name="skills[]" value="<?= $skill['id'] ?>" class="hidden" <?= $isSelected ? 'checked' : '' ?>>
                                            <span class="font-medium"><?= sanitize_string($skill['skill_name']) ?></span>
                                                <i data-lucide="check" class="w-3 h-3 text-violet-600 transition-all <?= $isSelected ? '' : 'hidden' ?>"></i>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach;
                    else: ?>
                        <div class="text-center py-8 text-slate-400 italic text-xs border border-dashed border-slate-200 dark:border-slate-600 rounded-xl">No skills available.</div>
                    <?php endif; ?>
                </div>
                <div id="skills-empty" class="hidden border border-dashed border-slate-200 dark:border-slate-600 rounded-xl p-8 text-center bg-slate-50/50 dark:bg-slate-700/30">
                        <i data-lucide="search" class="w-8 h-8 text-slate-300 dark:text-slate-500 mb-2"></i>
                    <p class="text-xs text-slate-500 font-medium">No skills match your search.</p>
                </div>
                <p id="skills-err" class="text-red-500 text-xs mt-3 hidden"></p>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Job Type Section -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700/50 p-6 fade-in" style="animation-delay:.2s">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1">Job Type</label>
                <p class="text-xs text-slate-400 dark:text-slate-500 mb-4">Choose how do you prefer to pay for the job</p>

                <div class="space-y-3">
                    <label class="flex items-center gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all <?= ($form['job_type'] ?? 'fixed') === 'hourly' ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/20' : 'border-slate-200 dark:border-slate-600 hover:border-slate-300 dark:hover:border-slate-500' ?>">
                        <input type="radio" name="job_type" value="hourly" class="hidden" <?= ($form['job_type'] ?? '') === 'hourly' ? 'checked' : '' ?> onchange="toggleJobType()">
                        <div class="w-5 h-5 rounded-full border-2 <?= ($form['job_type'] ?? 'fixed') === 'hourly' ? 'border-violet-500' : 'border-slate-300 dark:border-slate-500' ?> flex items-center justify-center">
                            <div class="w-2.5 h-2.5 rounded-full bg-violet-500 <?= ($form['job_type'] ?? 'fixed') === 'hourly' ? '' : 'hidden' ?> radio-dot"></div>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-700 dark:text-slate-200">Hourly</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">Pay by the hour</p>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all <?= ($form['job_type'] ?? 'fixed') === 'fixed' ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/20' : 'border-slate-200 dark:border-slate-600 hover:border-slate-300 dark:hover:border-slate-500' ?>">
                        <input type="radio" name="job_type" value="fixed" class="hidden" <?= ($form['job_type'] ?? 'fixed') === 'fixed' ? 'checked' : '' ?> onchange="toggleJobType()">
                        <div class="w-5 h-5 rounded-full border-2 <?= ($form['job_type'] ?? 'fixed') === 'fixed' ? 'border-violet-500' : 'border-slate-300 dark:border-slate-500' ?> flex items-center justify-center">
                            <div class="w-2.5 h-2.5 rounded-full bg-violet-500 <?= ($form['job_type'] ?? 'fixed') === 'fixed' ? '' : 'hidden' ?> radio-dot"></div>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-700 dark:text-slate-200">Fixed Price</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">Set a fixed budget</p>
                        </div>
                    </label>
                </div>

                <!-- Hourly Rate Fields -->
                <div id="hourly-fields" class="mt-5 space-y-4 <?= ($form['job_type'] ?? 'fixed') === 'hourly' ? '' : 'hidden' ?>">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5">Cost per hour</label>
                        <div class="relative">
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">$</div>
                            <input type="number" id="hourly_rate" name="hourly_rate" step="0.01" min="1" value="<?= sanitize_string($form['hourly_rate'] ?? '75.00') ?>" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl pl-8 pr-4 py-2.5 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5">Estimated hours</label>
                        <input type="number" id="estimated_hours" name="estimated_hours" min="1" value="<?= sanitize_string($form['estimated_hours'] ?? '10') ?>" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all">
                    </div>
                    <div class="p-3 bg-slate-50 dark:bg-slate-700/50 rounded-xl">
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="text-slate-500 dark:text-slate-400">Platform Fee - 2%</span>
                            <span id="platform-fee" class="text-slate-600 dark:text-slate-300">$1.50</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold text-slate-700 dark:text-slate-200">Total cost</span>
                            <span id="total-cost" class="text-lg font-extrabold text-violet-600 dark:text-violet-400">$76.50</span>
                        </div>
                    </div>
                </div>

                <!-- Fixed Price Field -->
                <div id="fixed-fields" class="mt-5 <?= ($form['job_type'] ?? 'fixed') === 'fixed' ? '' : 'hidden' ?>">
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5">Budget Amount (USD)</label>
                    <div class="relative">
                        <div class="absolute left-3 top-1/2 -translate-y-1/2 text-green-500">$</div>
                        <input type="number" id="budget" name="budget" step="0.01" min="1" value="<?= sanitize_string($form['budget'] ?? '') ?>" placeholder="0.00" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl pl-8 pr-4 py-2.5 text-slate-900 dark:text-white placeholder-slate-400 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all">
                    </div>
                    <p id="budget-err" class="text-red-500 text-xs mt-1.5 hidden"></p>
                </div>
            </div>

            <!-- Experience Level -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700/50 p-6 fade-in" style="animation-delay:.25s">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1">Experience Level</label>
                <p class="text-xs text-slate-400 dark:text-slate-500 mb-3">Select required experience level</p>
                <select name="experience_level" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all">
                    <option value="entry" <?= ($form['experience_level'] ?? '') === 'entry' ? 'selected' : '' ?>>Entry Level</option>
                    <option value="intermediate" <?= ($form['experience_level'] ?? 'intermediate') === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                    <option value="expert" <?= ($form['experience_level'] ?? '') === 'expert' ? 'selected' : '' ?>>Expert</option>
                </select>
            </div>

            <!-- Duration -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700/50 p-6 fade-in" style="animation-delay:.3s">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1">Project Duration</label>
                <p class="text-xs text-slate-400 dark:text-slate-500 mb-3">Estimated project timeline</p>
                <select name="project_duration" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all">
                    <option value="">Select duration</option>
                    <option value="less_than_1_week" <?= ($form['project_duration'] ?? '') === 'less_than_1_week' ? 'selected' : '' ?>>Less than 1 week</option>
                    <option value="1_to_2_weeks" <?= ($form['project_duration'] ?? '') === '1_to_2_weeks' ? 'selected' : '' ?>>1 to 2 weeks</option>
                    <option value="2_to_4_weeks" <?= ($form['project_duration'] ?? '') === '2_to_4_weeks' ? 'selected' : '' ?>>2 to 4 weeks</option>
                    <option value="1_to_3_months" <?= ($form['project_duration'] ?? '') === '1_to_3_months' ? 'selected' : '' ?>>1 to 3 months</option>
                    <option value="3_to_6_months" <?= ($form['project_duration'] ?? '') === '3_to_6_months' ? 'selected' : '' ?>>3 to 6 months</option>
                    <option value="more_than_6_months" <?= ($form['project_duration'] ?? '') === 'more_than_6_months' ? 'selected' : '' ?>>More than 6 months</option>
                </select>
            </div>

            <!-- Category -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700/50 p-6 fade-in" style="animation-delay:.33s">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1">Category</label>
                <p class="text-xs text-slate-400 dark:text-slate-500 mb-3">Job category</p>
                <select name="category" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all">
                    <option value="">Select category</option>
                    <?php if ($allCategories && $allCategories->num_rows > 0): ?>
                        <?php
                        $allCategories->data_seek(0);
                        while ($cat = $allCategories->fetch_assoc()):
                            ?>
                            <option value="<?= sanitize_string($cat['category']) ?>" <?= ($form['category'] ?? '') === $cat['category'] ? 'selected' : '' ?>><?= sanitize_string($cat['category']) ?></option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Deadline -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700/50 p-6 fade-in" style="animation-delay:.36s">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1">Deadline</label>
                <p class="text-xs text-slate-400 dark:text-slate-500 mb-3">Project deadline (optional)</p>
                <input type="date" name="deadline" value="<?= sanitize_string($form['deadline'] ?? '') ?>" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all">
            </div>

            <!-- Max Freelancers -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700/50 p-6 fade-in" style="animation-delay:.39s">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-1">Max Freelancers</label>
                <p class="text-xs text-slate-400 dark:text-slate-500 mb-3">How many freelancers can you hire?</p>
                <input type="number" name="max_freelancers" min="1" max="50" value="<?= sanitize_string($form['max_freelancers'] ?? '1') ?>" class="w-full bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all">
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-3 fade-in" style="animation-delay:.35s">
                <a href="dashboard.php" class="flex-1 px-5 py-3 border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 rounded-xl text-sm font-semibold transition-all text-center no-underline">Cancel</a>
                <button type="submit" id="submit-btn" class="flex-1 flex justify-center items-center gap-2.5 px-5 py-3 bg-gradient-to-r from-violet-500 to-purple-500 hover:from-violet-600 hover:to-purple-600 text-white rounded-xl text-sm font-bold shadow-lg shadow-purple-500/25 transition-all">
                   <i data-lucide="send" id="submit-icon" class="w-4 h-4"></i> <span id="submit-text">Post Job</span>
                </button>
            </div>
        </div>
    </div>
</form>

<script>
    // Job Type Toggle
    function toggleJobType() {
        const type = document.querySelector('input[name="job_type"]:checked').value;
        const hourlyFields = document.getElementById('hourly-fields');
        const fixedFields = document.getElementById('fixed-fields');

        document.querySelectorAll('input[name="job_type"]').forEach(radio => {
            const label = radio.closest('label');
            const dot = label.querySelector('.radio-dot');
            if (radio.checked) {
                label.classList.add('border-violet-500', 'bg-violet-50', 'dark:bg-violet-900/20');
                label.classList.remove('border-slate-200', 'dark:border-slate-600');
                label.querySelector('div').classList.add('border-violet-500');
                label.querySelector('div').classList.remove('border-slate-300', 'dark:border-slate-500');
                dot.classList.remove('hidden');
            } else {
                label.classList.remove('border-violet-500', 'bg-violet-50', 'dark:bg-violet-900/20');
                label.classList.add('border-slate-200', 'dark:border-slate-600');
                label.querySelector('div').classList.remove('border-violet-500');
                label.querySelector('div').classList.add('border-slate-300', 'dark:border-slate-500');
                dot.classList.add('hidden');
            }
        });

        if (type === 'hourly') {
            hourlyFields.classList.remove('hidden');
            fixedFields.classList.add('hidden');
            calculateTotal();
        } else {
            hourlyFields.classList.add('hidden');
            fixedFields.classList.remove('hidden');
        }
    }

    // Calculate total cost for hourly
    function calculateTotal() {
        const rate = parseFloat(document.getElementById('hourly_rate').value) || 0;
        const hours = parseFloat(document.getElementById('estimated_hours').value) || 0;
        const subtotal = rate * hours;
        const fee = subtotal * 0.02;
        const total = subtotal + fee;

        document.getElementById('platform-fee').textContent = '$' + fee.toFixed(2);
        document.getElementById('total-cost').textContent = '$' + total.toFixed(2);
    }

    // Category Management
    let categories = [];

    function addCategory(cat) {
        if (!categories.includes(cat)) {
            categories.push(cat);
            renderCategories();
        }
        document.getElementById('category-search').value = '';
        document.getElementById('category-dropdown').classList.add('hidden');
    }

    function removeCategory(cat) {
        categories = categories.filter(c => c !== cat);
        renderCategories();
    }

    function renderCategories() {
        const container = document.getElementById('selected-categories');
        container.innerHTML = categories.map(c => `
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 rounded-lg text-xs font-semibold">
            ${c}
            <button type="button" onclick="removeCategory('${c}')" class="hover:text-violet-900 dark:hover:text-white">
                <i data-lucide="x" class="w-3 h-3"></i>
            </button>
        </span>
    `).join('');
        document.getElementById('categories-input').value = categories.join(',');
    }

    // Skill Selection
    function syncSkillCount() {
        const c = document.querySelectorAll('input[name="skills[]"]:checked').length;
        const b = document.getElementById('skill-count');
        const e = document.getElementById('skills-err');
        if (b) {
            if (c > 0) {
                b.textContent = c + ' Skill' + (c === 1 ? '' : 's') + ' Selected';
                b.className = 'bg-violet-600 text-white text-xs font-bold px-3 py-1.5 rounded-full shadow-sm transition-all';
                if (e) e.classList.add('hidden');
            } else {
                b.textContent = '0 Selected';
                b.className = 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 text-xs font-semibold px-3 py-1.5 rounded-full transition-all';
            }
        }
    }

    // Format text (placeholder)
    // function formatText(command) {
    //     // Rich text formatting would go here
    //     alert('Text formatting: ' + command);
    // }
    function formatText(command) {
        // Keeps focus inside the editor container
        document.getElementById('editor').focus();
        // Naturally bolds, underlines, or italics the selection
        document.execCommand(command, false, null);
    }

    // Preview job
    function previewJob() {
        alert('Preview functionality - would show job preview modal');
    }

    // Save as draft
    function saveDraft() {
        alert('Draft saved successfully!');
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Skill selection
        document.querySelectorAll('input[name="skills[]"]').forEach(function(cb) {
            cb.addEventListener('change', function() {
                var tag = this.closest('.skill-tag');
                if (!tag) return;
                var checkIcon = tag.querySelector('[data-lucide="check"]');
                tag.classList.toggle('skill-tag-active', this.checked);
                tag.classList.toggle('border-violet-500', this.checked);
                tag.classList.toggle('bg-violet-50', this.checked);
                tag.classList.toggle('dark:bg-violet-900/30', this.checked);
                if (checkIcon) checkIcon.classList.toggle('hidden', !this.checked);
                syncSkillCount();
            });
        });

        // Skill search
        var search = document.getElementById('skill-search');
        if (search) search.addEventListener('input', function() {
            var q = this.value.toLowerCase().trim();
            var vis = 0;
            document.querySelectorAll('.skill-category').forEach(function(cat) {
                var catVis = 0;
                cat.querySelectorAll('.skill-tag').forEach(function(tag) {
                    var n = tag.getAttribute('data-name') || '';
                    if (n.indexOf(q) !== -1) {
                        tag.style.display = '';
                        catVis++;
                        vis++;
                    } else {
                        tag.style.display = 'none';
                    }
                });
                cat.style.display = catVis === 0 ? 'none' : '';
            });
            document.getElementById('skills-empty').classList.toggle('hidden', vis > 0);
        });

        // Category search
        var catSearch = document.getElementById('category-search');
        var catDropdown = document.getElementById('category-dropdown');
        if (catSearch) {
            catSearch.addEventListener('focus', function() {
                catDropdown.classList.remove('hidden');
            });
            catSearch.addEventListener('input', function() {
                var q = this.value.toLowerCase();
                catDropdown.querySelectorAll('button').forEach(function(btn) {
                    btn.style.display = btn.textContent.toLowerCase().includes(q) ? '' : 'none';
                });
            });
            document.addEventListener('click', function(e) {
                if (!catSearch.contains(e.target) && !catDropdown.contains(e.target)) {
                    catDropdown.classList.add('hidden');
                }
            });
        }

        // Hourly rate calculation
        var hourlyRate = document.getElementById('hourly_rate');
        var estimatedHours = document.getElementById('estimated_hours');
        if (hourlyRate) hourlyRate.addEventListener('input', calculateTotal);
        if (estimatedHours) estimatedHours.addEventListener('input', calculateTotal);

        // Sync editor content to hidden textarea before form submit
        var editor = document.getElementById('editor');
        var descriptionTextarea = document.getElementById('description');
        if (editor && descriptionTextarea) {
            // Load existing content into editor
            var existingContent = descriptionTextarea.value.trim();
            if (existingContent) {
                editor.innerText = existingContent;
            }
        }

        // Form validation
        var form = document.getElementById('job-form');
        if (form) form.addEventListener('submit', function(e) {
            // Sync editor to textarea before validation
            if (editor && descriptionTextarea) {
                descriptionTextarea.value = editor.innerText.trim();
            }

            var v = true;
            var t = document.getElementById('title');
            var d = document.getElementById('description');
            var jobType = document.querySelector('input[name="job_type"]:checked').value;
            var te = document.getElementById('title-err');
            var de = document.getElementById('description-err');
            var be = document.getElementById('budget-err');
            var se = document.getElementById('skills-err');

            if (!t || t.value.trim().length < 10) {
                if (te) {
                    te.textContent = 'Title must be at least 10 characters.';
                    te.classList.remove('hidden');
                }
                v = false;
            } else if (te) te.classList.add('hidden');

            if (!d || d.value.trim().length < 50) {
                if (de) {
                    de.textContent = 'Description must be at least 50 characters.';
                    de.classList.remove('hidden');
                }
                v = false;
            } else if (de) de.classList.add('hidden');

            if (jobType === 'fixed') {
                var b = document.getElementById('budget');
                var bv = parseFloat(b ? b.value : 0);
                if (isNaN(bv) || bv <= 0) {
                    if (be) {
                        be.textContent = 'Budget must be greater than $0.';
                        be.classList.remove('hidden');
                    }
                    v = false;
                } else if (be) be.classList.add('hidden');
            }

            if (document.querySelectorAll('input[name="skills[]"]:checked').length === 0) {
                if (se) {
                    se.textContent = 'Please select at least one skill.';
                    se.classList.remove('hidden');
                }
                v = false;
            } else if (se) se.classList.add('hidden');

            if (!v) {
                e.preventDefault();
                var f = document.querySelector('[id$="-err"]:not(.hidden)');
                if (f) f.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                return;
            }

            var btn = document.getElementById('submit-btn');
            var icon = document.getElementById('submit-icon');
            var txt = document.getElementById('submit-text');
            if (btn) {
                btn.disabled = true;
                btn.classList.add('opacity-75', 'cursor-not-allowed');
            }
            if (icon) { icon.setAttribute('data-lucide', 'loader'); icon.className = 'animate-spin w-4 h-4'; lucide.createIcons(); }
            if (txt) txt.textContent = 'Publishing...';
        });

        syncSkillCount();
        toggleJobType();
    });
</script>
<?php $conn->close(); ?>
<?php require_once __DIR__ . '/../includes/client_footer.php'; ?>