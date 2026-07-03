<?php
// --- Backend PHP Logic (Example) ---
// session_start();
// if (!isset($_SESSION['client_id'])) { header("Location: login.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['job_title'] ?? '';
    $description = $_POST['job_description'] ?? '';
    $budget = $_POST['budget'] ?? 0.0;
    $skills = $_POST['skills'] ?? [];  // Array of skill IDs submitted via hidden input

    // 1. INSERT INTO jobs (client_id, title, description, budget) VALUES (?, ?, ?, ?)
    // 2. Get last_insert_id() -> $job_id
    // 3. Loop through $skills and INSERT INTO job_skills (job_id, skill_id) VALUES (?, ?)
    // 4. Redirect to dashboard
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post a Job | FreeMarket</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased">

    <nav class="bg-white shadow-sm border-b px-8 py-4 flex justify-between items-center">
        <h1 class="text-xl font-bold text-blue-600">FreeMarket <span class="text-gray-400 text-sm font-normal">| Client Dashboard</span></h1>
        <div class="flex items-center space-x-4">
            <span class="text-sm font-medium">Hello, Client</span>
            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 font-bold">C</div>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto py-12 px-4 sm:px-6">
        
        <div class="mb-8">
            <h2 class="text-3xl font-extrabold text-gray-900">Post a New Job</h2>
            <p class="mt-2 text-sm text-gray-600">Provide clear details to attract the best freelancers for your project.</p>
        </div>

        <form action="post-job.php" method="POST" class="bg-white shadow-md rounded-xl p-8 border border-gray-100">
            
            <div class="mb-6">
                <label for="job_title" class="block text-sm font-semibold text-gray-700 mb-2">Job Title <span class="text-red-500">*</span></label>
                <input type="text" id="job_title" name="job_title" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                    placeholder="e.g. Build a responsive E-commerce website in PHP">
            </div>

            <div class="mb-6">
                <label for="job_description" class="block text-sm font-semibold text-gray-700 mb-2">Project Description <span class="text-red-500">*</span></label>
                <textarea id="job_description" name="job_description" rows="6" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                    placeholder="Describe your project, deliverables, and any specific requirements..."></textarea>
                <p class="text-xs text-gray-500 mt-2 text-right">Minimum 50 characters</p>
            </div>

            <hr class="my-8 border-gray-200">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div>
                    <label for="budget" class="block text-sm font-semibold text-gray-700 mb-2">Estimated Budget (USD) <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">$</span>
                        </div>
                        <input type="number" id="budget" name="budget" min="5" step="0.01" required
                            class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                            placeholder="0.00">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Required Skills</label>
                    <div class="relative">
                        <input type="text" id="skill_search" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                            placeholder="Type a skill and press Enter...">
                        
                        <div id="selected_skills_container" class="flex flex-wrap gap-2 mt-3">
                            </div>
                        
                        <select name="skills[]" id="hidden_skills" multiple class="hidden"></select>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-4 pt-4 border-t border-gray-100">
                <button type="button" class="text-gray-600 hover:text-gray-800 font-medium px-4 py-2 rounded-lg transition">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white font-semibold px-6 py-2 rounded-lg hover:bg-blue-700 shadow-md transition">
                    Post Job Now
                </button>
            </div>

        </form>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const skillSearch = document.getElementById('skill_search');
            const container = document.getElementById('selected_skills_container');
            const hiddenSelect = document.getElementById('hidden_skills');

            // Mock database skills (In reality, fetch these via AJAX or render them in JS via PHP)
            const availableSkills = {
                'php': { id: 1, name: 'PHP' },
                'mysql': { id: 2, name: 'MySQL' },
                'tailwind': { id: 3, name: 'Tailwind CSS' },
                'react': { id: 4, name: 'React.js' },
                'design': { id: 5, name: 'UI/UX Design' }
            };

            let selectedSkills = new Set();

            skillSearch.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const inputVal = this.value.trim().toLowerCase();
                    
                    // Match input to our mock database
                    const matchedSkill = Object.values(availableSkills).find(s => s.name.toLowerCase() === inputVal);

                    if (matchedSkill && !selectedSkills.has(matchedSkill.id)) {
                        addSkillTag(matchedSkill);
                        this.value = ''; // Clear input
                    } else if (!matchedSkill && inputVal !== '') {
                        alert('Skill not found in database. Please choose an existing skill.');
                    }
                }
            });

            function addSkillTag(skill) {
                selectedSkills.add(skill.id);

                // Create visual tag
                const tag = document.createElement('span');
                tag.className = 'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800';
                tag.innerHTML = `
                    ${skill.name}
                    <button type="button" class="ml-1.5 flex-shrink-0 h-4 w-4 rounded-full inline-flex items-center justify-center text-blue-400 hover:bg-blue-200 hover:text-blue-500 focus:outline-none" onclick="removeSkill(${skill.id}, this)">
                        <span class="sr-only">Remove skill</span>
                        &times;
                    </button>
                `;
                container.appendChild(tag);

                // Add to hidden select for PHP POST submission
                const option = document.createElement('option');
                option.value = skill.id;
                option.text = skill.name;
                option.selected = true;
                option.id = `opt_${skill.id}`;
                hiddenSelect.appendChild(option);
            }

            // Expose remove function globally for the onclick handler
            window.removeSkill = function(id, buttonElement) {
                selectedSkills.delete(id);
                buttonElement.parentElement.remove(); // Remove tag UI
                document.getElementById(`opt_${id}`).remove(); // Remove from hidden select
            };
        });
    </script>
</body>
</html>