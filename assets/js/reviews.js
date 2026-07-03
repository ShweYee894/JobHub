/**
 * reviews.js
 * Interactive star rating component, AJAX submission, and UI feedback.
 * Used by client/reviews.php and freelancer/reviews.php.
 */

const ratingLabels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
let currentRating = 0;

/**
 * Set the selected rating (on click).
 */
function setRating(rating) {
    currentRating = rating;
    document.getElementById('modalRating').value = rating;
    document.getElementById('ratingLabel').textContent = ratingLabels[rating] || 'Select a rating';
    updateStarDisplay(rating);
}

/**
 * Preview rating on hover.
 */
function hoverRating(rating) {
    updateStarDisplay(rating);
    document.getElementById('ratingLabel').textContent = ratingLabels[rating] || '';
}

/**
 * Reset hover to the selected rating.
 */
function resetHover() {
    updateStarDisplay(currentRating);
    document.getElementById('ratingLabel').textContent = currentRating > 0 ? ratingLabels[currentRating] : 'Select a rating';
}

/**
 * Update the visual state of stars.
 */
function updateStarDisplay(rating) {
    const stars = document.querySelectorAll('#starRating .star-btn');
    stars.forEach((btn, index) => {
        const starIndex = index + 1;
        if (starIndex <= rating) {
            btn.classList.remove('text-gray-300');
            btn.classList.add('text-amber-400');
        } else {
            btn.classList.remove('text-amber-400');
            btn.classList.add('text-gray-300');
        }
    });
}

/**
 * Reset stars to empty state.
 */
function resetStarDisplay() {
    currentRating = 0;
    const stars = document.querySelectorAll('#starRating .star-btn');
    stars.forEach(btn => {
        btn.classList.remove('text-amber-400');
        btn.classList.add('text-gray-300');
    });
}

// ── Character count for textarea ───────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const textarea = document.getElementById('modalComment');
    const charCount = document.getElementById('charCount');
    if (textarea && charCount) {
        textarea.addEventListener('input', () => {
            charCount.textContent = textarea.value.length;
        });
    }
});

// ── Form submission via AJAX ───────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('reviewForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const rating = parseInt(document.getElementById('modalRating').value, 10);
        const comment = document.getElementById('modalComment').value.trim();
        const contractId = document.getElementById('modalContractId').value;
        const commentError = document.getElementById('commentError');
        const submitBtn = document.getElementById('submitReviewBtn');
        const submitText = document.getElementById('submitBtnText');
        const submitSpinner = document.getElementById('submitSpinner');

        // Client-side validation
        let valid = true;

        if (rating < 1 || rating > 5) {
            document.getElementById('ratingLabel').textContent = 'Please select a rating';
            document.getElementById('ratingLabel').classList.add('text-red-500');
            valid = false;
        } else {
            document.getElementById('ratingLabel').classList.remove('text-red-500');
        }

        if (!comment) {
            commentError.classList.remove('hidden');
            valid = false;
        } else {
            commentError.classList.add('hidden');
        }

        if (!valid) return;

        // Disable button and show spinner
        submitBtn.disabled = true;
        submitText.textContent = 'Submitting...';
        submitSpinner.classList.remove('hidden');

        try {
            const formData = new FormData(form);
            const response = await fetch('/finalproject/api/reviews_api.php?action=submit', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success) {
                // Close review modal, show success
                closeReviewModal();
                document.getElementById('successModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            } else {
                // Show error message
                alert(data.message || 'Failed to submit review. Please try again.');
            }
        } catch (err) {
            console.error('Review submission error:', err);
            alert('A network error occurred. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitText.textContent = 'Submit Review';
            submitSpinner.classList.add('hidden');
        }
    });
});
