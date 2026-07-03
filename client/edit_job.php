<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: ../auth/login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$jobId = intval($_GET['id'] ?? 0);
if ($jobId <= 0) {
    header('Location: my_jobs.php');
    exit();
}

$stmt = $conn->prepare('SELECT name, profile_image FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare('SELECT * FROM jobs WHERE id = ? AND client_id = ?');
$stmt->bind_param('ii', $jobId, $userId);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    set_flash('error', 'Job not found or access denied.');
    header('Location: my_jobs.php');
    exit();
}

$stmt = $conn->prepare('SELECT skill_id FROM job_skills WHERE job_id = ?');
$stmt->bind_param('i', $jobId);
$stmt->execute();
$existingSkills = [];
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $existingSkills[] = intval($r['skill_id']);
}
$stmt->close();

$allSkills = $conn->query('SELECT id, skill_name, category FROM skills ORDER BY category, skill_name');

$errors = $_SESSION['job_errors'] ?? [];
$form = $_SESSION['job_form'] ?? [];
unset($_SESSION['job_errors'], $_SESSION['job_form']);

$prefill = [
    'title' => $form['title'] ?? $job['title'],
    'description' => $form['description'] ?? $job['description'],
    'budget' => $form['budget'] ?? $job['budget'],
];
$prefillSkills = !empty($form['skills']) ? $form['skills'] : $existingSkills;

$conn->close();

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-th-large'],
    ['key' => 'my_jobs', 'label' => 'My Jobs', 'url' => 'my_jobs.php', 'icon' => 'fa-briefcase'],
    ['key' => 'post_job', 'label' => 'Post a Job', 'url' => 'post_job.php', 'icon' => 'fa-plus-circle'],
    ['key' => 'proposals', 'label' => 'Proposals', 'url' => 'proposals.php', 'icon' => 'fa-file-alt'],
    ['key' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => 'fa-handshake'],
    ['key' => 'payment_history', 'label' => 'Payments', 'url' => 'payment_history.php', 'icon' => 'fa-credit-card'],
    ['key' => 'messages', 'label' => 'Messages', 'url' => 'messages.php', 'icon' => 'fa-comment-dots'],
];
$pageTitle = 'Edit Job';
$pageSubtitle = 'Update your job posting details';
$activePage = 'my_jobs';
$user = ['name' => $user['name'] ?? 'Client', 'profile_image' => $user['profile_image'] ?? null];
$unreadCount = $unreadMessages ?? 0;
$profileLink = 'profile.php';
require_once __DIR__ . '/../components/layout_start.php';
?>
    <?php display_flash('error'); ?>
    <?php if (!empty($errors)): ?>
    <div class="rounded-2xl p-4 bg-red-50 border border-red-200 fade-in">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0"><i class="fas fa-exclamation-triangle text-red-500 text-lg"></i></div>
            <div><p class="font-bold text-red-700 text-sm mb-1">Please fix the following errors:</p>
                <ul class="space-y-1"><?php foreach ($errors as $e): ?><li class="text-red-600 text-xs flex items-start gap-2"><i class="fas fa-circle text-[5px] mt-1.5 text-red-400 flex-shrink-0"></i><?= sanitize_string($e) ?></li><?php endforeach; ?></ul>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <form action="save_job.php" method="POST" id="job-form" class="space-y-6">
        <?= csrf_field() ?>
        <input type="hidden" name="job_id" value="<?= $jobId ?>">
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-sm fade-in">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center"><i class="fas fa-file-lines text-blue-500"></i></div>
                <div><h2 class="text-base font-bold text-gray-900">Job Information</h2><p class="text-xs text-gray-400">Provide a clear title and detailed description</p></div>
            </div>
            <div class="space-y-5">
                <div>
                    <label for="title" class="block text-sm font-semibold text-gray-700 mb-2">Job Title <span class="text-red-500">*</span></label>
                    <input type="text" id="title" name="title" required value="<?= sanitize_string($prefill['title']) ?>" placeholder="e.g. Build a responsive e-commerce website" maxlength="255" class="fld w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-gray-900 placeholder-gray-400 text-sm">
                    <p id="title-err" class="text-red-500 text-xs mt-1.5 hidden"></p>
                </div>
                <div>
                    <label for="description" class="block text-sm font-semibold text-gray-700 mb-2">Job Description <span class="text-red-500">*</span></label>
                    <textarea id="description" name="description" rows="8" required placeholder="Describe your project in detail..." class="fld w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-gray-900 placeholder-gray-400 text-sm resize-none"><?= sanitize_string($prefill['description']) ?></textarea>
                    <p id="description-err" class="text-red-500 text-xs mt-1.5 hidden"></p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-sm fade-in" style="animation-delay:.1s">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center"><i class="fas fa-dollar-sign text-emerald-500"></i></div>
                <div><h2 class="text-base font-bold text-gray-900">Budget</h2><p class="text-xs text-gray-400">Set a fixed budget for this project</p></div>
            </div>
            <div class="max-w-sm">
                <label for="budget" class="block text-sm font-semibold text-gray-700 mb-2">Budget Amount (USD) <span class="text-red-500">*</span></label>
                <div class="relative">
                    <div class="absolute left-4 top-1/2 -translate-y-1/2 flex items-center justify-center w-6 h-6 rounded-lg bg-emerald-100"><i class="fas fa-dollar-sign text-emerald-600 text-xs"></i></div>
                    <input type="number" id="budget" name="budget" required step="0.01" min="0.01" value="<?= sanitize_string($prefill['budget']) ?>" placeholder="0.00" class="fld w-full bg-gray-50 border border-gray-200 rounded-xl pl-12 pr-4 py-3 text-gray-900 placeholder-gray-400 text-sm">
                </div>
                <p id="budget-err" class="text-red-500 text-xs mt-1.5 hidden"></p>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-sm fade-in" style="animation-delay:.2s">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 border-b border-gray-50 pb-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600"><i class="fas fa-layer-group text-lg"></i></div>
                    <div><h2 class="text-base font-bold text-gray-900">Required Skills</h2><p class="text-xs text-gray-400">Select the skills needed for this project.</p></div>
                </div>
                <span id="skill-count" class="bg-gray-100 text-gray-400 text-xs font-semibold px-3 py-1.5 rounded-full border border-gray-200/60 transition-all">0 Selected</span>
            </div>
            <div class="relative mb-6">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" id="skill-search" placeholder="Type to filter skills..." class="fld w-full bg-gray-50 border border-gray-200 rounded-xl pl-11 pr-4 py-2.5 text-gray-900 placeholder-gray-400 text-sm">
            </div>
            <div class="space-y-5 max-h-[500px] overflow-y-auto pr-2" id="skills-container">
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
                <div class="skill-category border border-gray-100 rounded-xl bg-slate-50/40 p-4" data-cat="<?= sanitize_string(strtolower($cat)) ?>">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-1.5 h-3 rounded-full bg-blue-600"></span>
                        <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider"><?= sanitize_string($cat) ?> <span class="text-[11px] text-gray-400 font-normal normal-case ml-1">(<?= count($list) ?>)</span></h3>
                    </div>
                    <div class="flex flex-wrap gap-2.5">
                        <?php foreach ($list as $skill):
                            $isSelected = in_array($skill['id'], $prefillSkills); ?>
                        <label class="skill-tag inline-flex items-center gap-2 border border-gray-200 rounded-xl px-3.5 py-2 text-xs text-gray-600 bg-white shadow-sm cursor-pointer select-none transition-all hover:border-blue-500 hover:bg-blue-50/10 <?= $isSelected ? 'skill-tag-active' : '' ?>" data-id="<?= $skill['id'] ?>" data-name="<?= sanitize_string(strtolower($skill['skill_name'])) ?>">
                            <input type="checkbox" name="skills[]" value="<?= $skill['id'] ?>" class="hidden" <?= $isSelected ? 'checked' : '' ?>>
                            <span class="w-1.5 h-1.5 rounded-full <?= $isSelected ? 'bg-blue-500 shadow shadow-blue-400' : 'bg-gray-300' ?> transition-all dot-indicator"></span>
                            <span class="font-medium text-gray-700"><?= sanitize_string($skill['skill_name']) ?></span>
                            <i class="fas fa-check text-[9px] text-blue-600 bg-blue-100/80 p-0.5 rounded-md transition-all ml-0.5 check-icon <?= $isSelected ? '' : 'hidden' ?>"></i>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach;
                else: ?>
                <div class="text-center py-8 text-gray-400 italic text-xs border border-dashed border-gray-200 rounded-xl">No skills available.</div>
                <?php endif; ?>
            </div>
            <div id="skills-empty" class="hidden border border-dashed border-gray-200 rounded-xl p-8 text-center bg-gray-50/50">
                <i class="fas fa-search text-gray-300 text-2xl mb-2"></i>
                <p class="text-xs text-gray-500 font-medium">No skills match your search.</p>
            </div>
            <p id="skills-err" class="text-red-500 text-xs mt-3 hidden"></p>
        </div>
        <div class="flex items-center justify-end gap-4 fade-in" style="animation-delay:.4s">
            <a href="my_jobs.php" class="px-6 py-3 border border-gray-200 hover:border-gray-300 text-gray-600 hover:text-gray-900 rounded-xl text-sm font-semibold transition-all">Cancel</a>
            <button type="submit" id="submit-btn" class="btn-grad px-8 py-3 text-white font-bold rounded-xl text-sm shadow-lg shadow-blue-500/25 flex items-center gap-2">
                <i class="fas fa-save" id="submit-icon"></i><span id="submit-text">Update Job</span>
            </button>
        </div>
    </form>
<script>
function syncSkillCount(){var c=document.querySelectorAll('input[name="skills[]"]:checked').length,b=document.getElementById('skill-count'),e=document.getElementById('skills-err');if(b){if(c>0){b.textContent=c+' Skill'+(c===1?'':'s')+' Selected';b.className='bg-blue-600 text-white text-xs font-bold px-3 py-1.5 rounded-full shadow-sm shadow-blue-500/25 transition-all';if(e)e.classList.add('hidden')}else{b.textContent='0 Selected';b.className='bg-gray-100 text-gray-400 text-xs font-semibold px-3 py-1.5 rounded-full border border-gray-200/60 transition-all'}}}
document.addEventListener('DOMContentLoaded',function(){
document.querySelectorAll('input[name="skills[]"]').forEach(function(cb){cb.addEventListener('change',function(){var tag=this.closest('.skill-tag');if(!tag)return;var chk=tag.querySelector('.check-icon'),dot=tag.querySelector('.dot-indicator');tag.classList.toggle('skill-tag-active',this.checked);if(chk)chk.classList.toggle('hidden',!this.checked);if(dot){dot.className=this.checked?'w-1.5 h-1.5 rounded-full bg-blue-500 shadow shadow-blue-400 transition-all dot-indicator':'w-1.5 h-1.5 rounded-full bg-gray-300 transition-all dot-indicator'}syncSkillCount()})});
var search=document.getElementById('skill-search');if(search)search.addEventListener('input',function(){var q=this.value.toLowerCase().trim(),vis=0;document.querySelectorAll('.skill-category').forEach(function(cat){var catVis=0;cat.querySelectorAll('.skill-tag').forEach(function(tag){var n=tag.getAttribute('data-name')||'';if(n.indexOf(q)!==-1){tag.style.display='';catVis++;vis++}else{tag.style.display='none'}});cat.style.display=catVis===0?'none':''});document.getElementById('skills-empty').classList.toggle('hidden',vis>0)});
var form=document.getElementById('job-form');if(form)form.addEventListener('submit',function(e){var v=true,t=document.getElementById('title'),d=document.getElementById('description'),b=document.getElementById('budget'),te=document.getElementById('title-err'),de=document.getElementById('description-err'),be=document.getElementById('budget-err'),se=document.getElementById('skills-err');
if(!t||t.value.trim().length<10){if(te){te.textContent='Title must be at least 10 characters.';te.classList.remove('hidden')}v=false}else if(te)te.classList.add('hidden');
if(!d||d.value.trim().length<50){if(de){de.textContent='Description must be at least 50 characters.';de.classList.remove('hidden')}v=false}else if(de)de.classList.add('hidden');
var bv=parseFloat(b?b.value:0);if(isNaN(bv)||bv<=0){if(be){be.textContent='Budget must be greater than $0.';be.classList.remove('hidden')}v=false}else if(be)be.classList.add('hidden');
if(document.querySelectorAll('input[name="skills[]"]:checked').length===0){if(se){se.textContent='Please select at least one skill.';se.classList.remove('hidden')}v=false}else if(se)se.classList.add('hidden');
if(!v){e.preventDefault();var f=document.querySelector('[id$="-err"]:not(.hidden)');if(f)f.scrollIntoView({behavior:'smooth',block:'center'});return}
var btn=document.getElementById('submit-btn'),icon=document.getElementById('submit-icon'),txt=document.getElementById('submit-text');if(btn){btn.disabled=true;btn.classList.add('opacity-75','cursor-not-allowed')}if(icon)icon.className='fas fa-spinner fa-spin';if(txt)txt.textContent='Updating...'});
syncSkillCount()});
</script>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
