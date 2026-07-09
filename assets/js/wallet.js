/**
 * Wallet JavaScript
 * Handles top-up modal, AJAX processing, and balance updates.
 */

// ── Top Up Modal ──────────────────────────────────────────────────────
function openTopUpModal() {
    document.getElementById('topUpModal').classList.remove('hidden');
    document.getElementById('topUpAmount').value = '';
    document.getElementById('topUpAmount').focus();
    // Reset quick amount buttons
    document.querySelectorAll('.quick-amount-btn').forEach(function(btn) {
        btn.classList.remove('active');
    });
}

function closeTopUpModal() {
    document.getElementById('topUpModal').classList.add('hidden');
}

// ── Quick Amount Selection ────────────────────────────────────────────
function setQuickAmount(amount) {
    var input = document.getElementById('topUpAmount');
    input.value = amount.toFixed(2);

    // Update active state
    document.querySelectorAll('.quick-amount-btn').forEach(function(btn) {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
}

// ── Amount Validation ─────────────────────────────────────────────────
function validateTopUpAmount(input) {
    var val = parseFloat(input.value);
    if (input.value === '') return;

    // Remove quick amount active state when typing
    document.querySelectorAll('.quick-amount-btn').forEach(function(btn) {
        btn.classList.remove('active');
    });

    if (val < 10) {
        input.setCustomValidity('Minimum amount is $10.00');
    } else if (val > 10000) {
        input.setCustomValidity('Maximum amount is $10,000.00');
    } else {
        input.setCustomValidity('');
    }
}

// ── Payment Method Selection ──────────────────────────────────────────
function selectPaymentMethod(element, method) {
    if (element.classList.contains('opacity-50')) return;

    document.querySelectorAll('.payment-method-option').forEach(function(opt) {
        opt.classList.remove('selected');
        var circle = opt.querySelector('.rounded-full');
        if (circle && !circle.classList.contains('bg-blue-500')) {
            circle.innerHTML = '';
        }
    });

    element.classList.add('selected');
    var circle = element.querySelector('.rounded-full');
    if (circle) {
        circle.innerHTML = '<div class="w-2.5 h-2.5 rounded-full bg-blue-500"></div>';
    }

    document.getElementById('paymentMethod').value = method;
}

// ── Process Top Up ────────────────────────────────────────────────────
async function processTopUp(e) {
    e.preventDefault();

    var amount = parseFloat(document.getElementById('topUpAmount').value);
    var paymentMethod = document.getElementById('paymentMethod').value;
    var btn = document.getElementById('topUpBtn');
    var btnText = document.getElementById('topUpBtnText');

    // Client-side validation
    if (!amount || amount < 10) {
        showToast('error', 'Minimum top-up amount is $10.00.');
        return false;
    }
    if (amount > 10000) {
        showToast('error', 'Maximum top-up amount is $10,000.00.');
        return false;
    }
    if (paymentMethod !== 'demo_wallet') {
        showToast('error', 'Only Demo Wallet payment is currently available.');
        return false;
    }

    btn.disabled = true;
    btnText.textContent = 'Processing...';
    showLoading();

    try {
        var fd = new FormData();
        fd.append('amount', amount.toFixed(2));
        fd.append('payment_method', paymentMethod);
        fd.append('csrf_token', CSRF_TOKEN);

        var r = await fetch(BASE_URL + '/ajax/update_wallet.php', {
            method: 'POST',
            body: fd
        });
        var j = await r.json();

        hideLoading();

        if (j.success) {
            closeTopUpModal();

            // Update balance displays
            updateBalanceDisplay(j.new_balance);

            // Show success modal
            document.getElementById('successAmount').textContent = '+$' + amount.toFixed(2);
            document.getElementById('successBalance').textContent = '$' + parseFloat(j.new_balance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('successModal').classList.remove('hidden');
        } else {
            showToast('error', j.message);
        }
    } catch (err) {
        hideLoading();
        showToast('error', 'Network error. Please try again.');
    }

    btn.disabled = false;
    btnText.textContent = 'Continue';
    return false;
}

// ── Update Balance Display ────────────────────────────────────────────
function updateBalanceDisplay(newBalance) {
    // Update hero balance
    var heroBalance = document.getElementById('walletBalance');
    if (heroBalance) {
        animateCounter(heroBalance, newBalance);
    }

    // Update stat cards
    var statCards = document.querySelectorAll('.wallet-amount');
    if (statCards.length > 0) {
        // Update first stat card (Current Balance)
        animateCounter(statCards[0], newBalance);
    }

    // Update modal current balance
    var modalBalance = document.getElementById('modalCurrentBalance');
    if (modalBalance) {
        modalBalance.textContent = '$' + parseFloat(newBalance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Update navbar wallet balance
    var navBalance = document.getElementById('navWalletBalance');
    if (navBalance) {
        navBalance.textContent = '$' + parseFloat(newBalance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
}

// ── Animate Counter ───────────────────────────────────────────────────
function animateCounter(element, targetValue) {
    var formatted = '$' + parseFloat(targetValue).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    element.textContent = formatted;
}

// ── Success Modal ─────────────────────────────────────────────────────
function closeSuccessModal() {
    document.getElementById('successModal').classList.add('hidden');
}

// ── Toast Notification ────────────────────────────────────────────────
function showToast(type, message) {
    var colors = {
        success: 'bg-emerald-500',
        error: 'bg-red-500',
        info: 'bg-blue-500'
    };
    var icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle'
    };
    var t = document.createElement('div');
    t.className = 'fixed top-4 right-4 z-[70] flex items-center gap-3 px-5 py-3 rounded-xl text-white text-sm font-semibold shadow-2xl ' + colors[type] + ' transition-all transform translate-x-full';
    t.innerHTML = '<i class="fas ' + icons[type] + '"></i> ' + message;
    document.body.appendChild(t);
    requestAnimationFrame(function() {
        t.classList.remove('translate-x-full');
    });
    setTimeout(function() {
        t.classList.add('translate-x-full');
        setTimeout(function() {
            t.remove();
        }, 300);
    }, 3500);
}

// ── Loading Overlay ───────────────────────────────────────────────────
function showLoading() {
    document.getElementById('loadingOverlay').classList.remove('hidden');
}

function hideLoading() {
    document.getElementById('loadingOverlay').classList.add('hidden');
}
