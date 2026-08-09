<?php
/**
 * Reusable Search Bar Component — matches index.php navbar design
 *
 * Usage: <?php include __DIR__ . '/includes/search_bar.php'; ?>
 *
 * Variables (set before including):
 *   $search_id        — unique id prefix (default: 'sb')
 *   $compact          — true for navbar, false for page-level (default: true)
 *   $preselect        — 'talent' or 'jobs' (default: auto-detect by role)
 */

if (!isset($search_id))  $search_id = 'sb';
if (!isset($compact))    $compact = true;

if (!isset($preselect)) {
    $role = current_role();
    $preselect = ($role === 'client') ? 'talent' : 'jobs';
}

$input_id        = $search_id . '-search';
$dropdown_id     = $search_id . 'SearchDropdown';
$btn_id          = $search_id . 'SearchDropdownBtn';
$type_id         = $search_id . '-type';
$suggestions_id  = $search_id . '-suggestions';

$base = '/jobhub';
$role = current_role();
if ($role === 'client') {
    $talentUrl = "$base/client/search_talent.php";
    $jobsUrl   = "$base/client/browse_jobs.php";
} elseif ($role === 'freelancer') {
    $talentUrl = "$base/freelancer/search_talent.php";
    $jobsUrl   = "$base/freelancer/browse_jobs.php";
} else {
    $talentUrl = "$base/browse_freelancers.php";
    $jobsUrl   = "$base/browse_jobs.php";
}
$search_urls = ['talent' => $talentUrl, 'jobs' => $jobsUrl];

if ($compact):
?>
<div class="relative flex items-center bg-surface border border-gray-200 overflow-visible" style="border-radius:4px">
  <div class="flex items-center gap-2 px-3 py-2">
    <i data-lucide="search" class="w-4 h-4 text-gray-400"></i>
    <input id="<?= $input_id ?>" type="text" placeholder="Search" autocomplete="off"
      data-base-urls='<?= json_encode($search_urls) ?>'
      data-default-type="<?= htmlspecialchars($preselect) ?>"
      class="bg-transparent w-32 sm:w-44 text-charcoal placeholder-gray-400 outline-none text-[13px]" />
  </div>
  <div class="w-px h-5 bg-gray-200"></div>
  <button id="<?= $btn_id ?>" type="button" class="flex items-center gap-1.5 px-3 py-2 cursor-pointer hover:bg-gray-50 transition-colors">
    <span id="<?= $type_id ?>" class="text-[12px] font-medium text-gray-600"><?= $preselect === 'talent' ? 'Talent' : 'Jobs' ?></span>
    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
  </button>
  <div id="<?= $dropdown_id ?>" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-md shadow-lg z-50 overflow-hidden">
    <button type="button" class="w-full text-left px-4 py-2.5 text-[13px] text-gray-600 hover:bg-gray-50 transition-colors font-medium" data-type="talent">Talent</button>
    <button type="button" class="w-full text-left px-4 py-2.5 text-[13px] text-gray-600 hover:bg-gray-50 transition-colors font-medium" data-type="jobs">Jobs</button>
  </div>
  <div id="<?= $suggestions_id ?>" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-md shadow-lg z-50 max-h-80 overflow-y-auto"></div>
</div>
<?php else: ?>
<div class="relative w-full">
  <form method="GET" action="<?= $search_urls[$preselect] ?>" onsubmit="return false;" id="<?= $search_id ?>-form">
    <div class="flex items-center bg-surface border border-gray-200" style="border-radius:4px">
      <div class="flex items-center gap-2 flex-1 px-4 py-3">
        <i data-lucide="search" class="w-5 h-5 text-gray-400"></i>
        <input id="<?= $input_id ?>" type="text" placeholder="Search for talent or jobs..." autocomplete="off"
          data-base-urls='<?= json_encode($search_urls) ?>'
          data-default-type="<?= htmlspecialchars($preselect) ?>"
          class="bg-transparent w-full text-charcoal placeholder-gray-400 outline-none text-[14px]" />
      </div>
      <div class="w-px h-6 bg-gray-200"></div>
      <button id="<?= $btn_id ?>" type="button" class="flex items-center gap-1.5 px-4 py-3 cursor-pointer hover:bg-gray-50 transition-colors">
        <span id="<?= $type_id ?>" class="text-[13px] font-medium text-gray-600"><?= $preselect === 'talent' ? 'Talent' : 'Jobs' ?></span>
        <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
      </button>
      <div id="<?= $dropdown_id ?>" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-md shadow-lg z-50 overflow-hidden">
        <button type="button" class="w-full text-left px-4 py-2.5 text-[13px] text-gray-600 hover:bg-gray-50 transition-colors font-medium" data-type="talent">Talent</button>
        <button type="button" class="w-full text-left px-4 py-2.5 text-[13px] text-gray-600 hover:bg-gray-50 transition-colors font-medium" data-type="jobs">Jobs</button>
      </div>
      <div id="<?= $suggestions_id ?>" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-md shadow-lg z-50 max-h-80 overflow-y-auto"></div>
    </div>
  </form>
</div>
<?php endif; ?>

<script>
(function() {
  const id = <?= json_encode($search_id) ?>;
  const urls = <?= json_encode($search_urls) ?>;
  const defaultType = <?= json_encode($preselect) ?>;
  let currentType = defaultType;
  let debounceTimer = null;

  const dd = document.getElementById(id + 'SearchDropdown');
  const btn = document.getElementById(id + 'SearchDropdownBtn');
  const label = document.getElementById(id + '-type');
  const input = document.getElementById(id + '-search');
  const suggestionsBox = document.getElementById(id + '-suggestions');
  const form = document.getElementById(id + '-form');

  if (btn && dd) {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      dd.classList.toggle('hidden');
    });
    dd.querySelectorAll('[data-type]').forEach(function(opt) {
      opt.addEventListener('click', function() {
        currentType = this.dataset.type;
        if (label) label.textContent = currentType === 'talent' ? 'Talent' : 'Jobs';
        dd.classList.add('hidden');
        if (form) form.action = urls[currentType];
        if (input) input.placeholder = currentType === 'talent' ? 'Search for talent...' : 'Search for jobs...';
        input.focus();
      });
    });
  }

  if (!input || !suggestionsBox) return;

  input.addEventListener('input', function() {
    var q = this.value.trim();
    clearTimeout(debounceTimer);
    if (q.length < 2) { suggestionsBox.classList.add('hidden'); suggestionsBox.innerHTML = ''; return; }
    debounceTimer = setTimeout(function() {
      var endpoint = currentType === 'talent' ? '/jobhub/api/talent_search_api.php' : '/jobhub/api/job_search_api.php';
      fetch(endpoint + '?q=' + encodeURIComponent(q) + '&limit=5')
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (!data.success || !data.results || data.results.length === 0) {
            suggestionsBox.classList.add('hidden'); suggestionsBox.innerHTML = ''; return;
          }
          suggestionsBox.innerHTML = data.results.map(function(r) {
            return '<a href="' + r.url + '" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition border-b border-gray-50 last:border-0">'
              + (r.image ? '<img src="' + r.image + '" class="w-8 h-8 rounded-full object-cover flex-shrink-0" />' : '<div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></div>')
              + '<div class="min-w-0"><div class="text-sm font-medium text-gray-900 truncate">' + r.title + '</div>'
              + '<div class="text-xs text-gray-500 truncate">' + (r.subtitle || '') + '</div></div></a>';
          }).join('');
          suggestionsBox.classList.remove('hidden');
        })
        .catch(function() { suggestionsBox.classList.add('hidden'); });
    }, 300);
  });

  input.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      var q = this.value.trim();
      if (q) window.location.href = urls[currentType] + '?search=' + encodeURIComponent(q);
    }
    if (e.key === 'Escape') suggestionsBox.classList.add('hidden');
  });

  document.addEventListener('click', function(e) {
    if (!suggestionsBox.contains(e.target) && e.target !== input) suggestionsBox.classList.add('hidden');
    if (dd && !btn.contains(e.target)) dd.classList.add('hidden');
  });
})();
</script>
