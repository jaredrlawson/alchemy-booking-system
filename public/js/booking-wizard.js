let booking = { id: null, title: '', price: '', duration: '', date: '', dateISO: '', time: '' };
let stripe, elements;

document.addEventListener('DOMContentLoaded', async function() {
    initServices();
    renderContinuousDates();
    if (typeof Stripe !== 'undefined' && alchemyData.stripePubKey) {
        stripe = Stripe(alchemyData.stripePubKey);
    }
    
    // Auto-Adopt Theme Font if enabled
    if (alchemyData.inheritFont === '1') {
        setTimeout(syncThemeStyles, 150); 
    } else {
        const wizard = document.querySelector('.alchemy-booking-wizard');
        if (wizard) wizard.classList.add('alc-revealed');
    }

    // Restore Month Update on Scroll
    const track = document.getElementById('date-track');
    if (track) {
        track.addEventListener('scroll', updateMonthOnScroll);
    }
});

function syncThemeStyles() {
    const refHead = document.querySelector('.entry-title, #content h1, #content h2, article h1, article h2, h1, h2, h3, .elementor-heading-title');
    const refBtn = document.querySelector('.elementor-button, .btn, button:not(.alchemy-booking-wizard button), .wp-block-button__link');
    const refBody = document.body;
    const fontRef = refHead || refBtn || refBody;
    if (fontRef) {
        const styles = window.getComputedStyle(fontRef);
        const fontFam = styles.fontFamily;
        const btnStyles = refBtn ? window.getComputedStyle(refBtn) : styles;
        const themeBg = btnStyles.backgroundColor;
        const themeRadius = btnStyles.borderRadius;
        const btnText = alchemyData.buttonText || '#ffffff';
        try {
            const finalRadius = alchemyData.borderRadius + 'px';
            let currentHeadColor = alchemyData.headingColor || '#111111';
            const harvestedColor = window.getComputedStyle(refHead || document.body).color;
            const rgb = harvestedColor.match(/\d+/g);
            if (rgb && rgb.length >= 3) {
                const r = parseInt(rgb[0]), g = parseInt(rgb[1]), b = parseInt(rgb[2]);
                const brightness = (r * 299 + g * 587 + b * 114) / 1000;
                if (brightness < 240 && harvestedColor !== 'transparent') {
                    currentHeadColor = harvestedColor;
                }
            }
            const btnColor = alchemyData.buttonColor || '#111111';
            const hoverColor = alchemyData.hoverColor || '#c5a000';
            const selectColor = alchemyData.selectedDayColor || '#c5a000';
            const secInt = (alchemyData.secondaryIntensity || 100) / 100;
            const rgbaBlack = `rgba(0,0,0,${secInt})`;
            const css = `
                .alchemy-booking-wizard *:not(.dashicons), .alchemy-booking-wizard input, .alchemy-booking-wizard textarea, .alchemy-booking-wizard select, .alchemy-booking-wizard button, .alchemy-booking-wizard label, .alchemy-booking-wizard span:not(.dashicons), .alchemy-booking-wizard p, .alchemy-booking-wizard h2, .alchemy-booking-wizard h3 { font-family: ${fontFam} !important; }
                .alchemy-booking-wizard .alc-payment-box { border-radius: 16px !important; border: 1px solid #ddd !important; }
                .alchemy-booking-wizard .btn-book, .alchemy-booking-wizard .alc-btn-primary, .alchemy-booking-wizard .btn-details, .alchemy-booking-wizard .alc-pill-btn, .alchemy-booking-wizard .time-slot-btn, .alchemy-booking-wizard .alchemy-service-card, .alchemy-booking-wizard .alc-form-container, .alchemy-booking-wizard .alc-step-container, .alchemy-booking-wizard .alc-summary-sidebar, .alchemy-booking-wizard .alc-summary-card { border-radius: ${finalRadius} !important; }
                .alchemy-booking-wizard .btn-book, .alchemy-booking-wizard .alc-btn-primary { background-color: ${btnColor} !important; font-size: 16px !important; color: ${btnText} !important; border: 1px solid ${btnColor} !important; }
                .alchemy-booking-wizard .btn-book:hover, .alchemy-booking-wizard .alc-btn-primary:hover { background-color: ${hoverColor} !important; border-color: ${hoverColor} !important; }
                .alchemy-booking-wizard .btn-details, .alchemy-booking-wizard .alc-pill-btn, .alchemy-booking-wizard .time-slot-btn, .alchemy-booking-wizard .alc-form-field { border: 1px solid ${rgbaBlack} !important; color: #000 !important; background: transparent !important; }
                .alchemy-booking-wizard .alc-form-field, .alchemy-booking-wizard textarea { border-radius: 0px !important; font-weight: 400 !important; }
                .alchemy-booking-wizard .btn-details, .alchemy-booking-wizard .alc-pill-btn, .alchemy-booking-wizard .time-slot-btn { font-weight: 700 !important; }
                .alchemy-booking-wizard .btn-details:hover, .alchemy-booking-wizard .alc-pill-btn:hover, .alchemy-booking-wizard .time-slot-btn:hover { border-color: ${hoverColor} !important; color: ${hoverColor} !important; }
                .alchemy-booking-wizard .date-day-item.selected { color: ${selectColor} !important; border-bottom-color: ${selectColor} !important; }
                .alchemy-booking-wizard .alc-category-heading, .alchemy-booking-wizard .alc-view-heading, .alchemy-booking-wizard .alc-month-row h2, .alchemy-booking-wizard .alc-summary-card h3, .alchemy-booking-wizard .alc-summary-total, .alchemy-booking-wizard .alc-summary-total span, .alchemy-booking-wizard .card-price, .alchemy-booking-wizard .alc-available-times-head { color: ${currentHeadColor} !important; }
                .alchemy-booking-wizard .alc-category-heading { border-bottom-color: ${currentHeadColor} !important; }
                .alchemy-booking-wizard .alchemy-service-card, .alchemy-booking-wizard .alc-form-container, .alchemy-booking-wizard .alc-step-container, .alchemy-booking-wizard .alc-summary-card { border-color: var(--alc-border) !important; }
                .alchemy-booking-wizard input::placeholder, .alchemy-booking-wizard textarea::placeholder { color: #999 !important; opacity: 1; }
            `;
            const head = document.head || document.getElementsByTagName('head')[0];
            const style = document.createElement('style');
            style.textContent = css;
            head.appendChild(style);
        } catch (e) { console.error("Alchemy Sync Error:", e); }
    }
    const wizard = document.querySelector('.alchemy-booking-wizard');
    if (wizard) wizard.classList.add('alc-revealed');
}

async function initServices() {
    const grid = document.getElementById('alchemy-services-grid');
    if (!grid) return;
    grid.innerHTML = '<div class="alc-loader-container"><div class="alc-spinner"></div><p>Loading Services...</p></div>';
    try {
        const res = await fetch(alchemyData.root + 'services', { headers: { 'X-WP-Nonce': alchemyData.nonce } });
        const services = await res.json();
        renderServices(services);
    } catch (e) { grid.innerHTML = '<p>Error loading services.</p>'; }
}

function renderServices(services) {
    const grid = document.getElementById('alchemy-services-grid');
    if (!grid || !services) return;
    grid.innerHTML = '';
    const categories = {};
    services.forEach(s => { const cat = s.category || 'General'; if (!categories[cat]) categories[cat] = []; categories[cat].push(s); });
    Object.keys(categories).sort().forEach(catName => {
        const sectionHeader = document.createElement('h3');
        sectionHeader.className = 'alc-category-heading';
        sectionHeader.innerText = catName;
        grid.appendChild(sectionHeader);
        const subGrid = document.createElement('div');
        subGrid.className = 'alchemy-card-grid alc-category-grid';
        categories[catName].forEach(s => {
            const card = document.createElement('div');
            card.className = 'alchemy-service-card';
            const safeTitle = (s.title || '').replace(/'/g, "\\'");
            const safeDesc = (s.description || '').replace(/'/g, "\\'").replace(/\n/g, '<br>');
            card.innerHTML = `
                <div class="card-header-row"><h3>${sanitize(s.title)}</h3><span class="card-price">$${s.price}</span></div>
                <div class="card-duration"><span class="dashicons dashicons-clock"></span> ${s.duration} mins</div>
                <div class="card-footer">
                    <button class="btn-details" id="btn-det-${s.id}">Details</button>
                    <button class="btn-book" onclick="selectService(${s.id}, '${safeTitle}', '${s.price}', '${s.duration}')">Book Now</button>
                </div>
            `;
            subGrid.appendChild(card);
            const detBtn = card.querySelector(`#btn-det-${s.id}`);
            if (detBtn) { detBtn.addEventListener('click', () => openDetailsModal(s.title, s.description)); }
        });
        grid.appendChild(subGrid);
    });
}

function moveScroller(dir) {
    const track = document.getElementById('date-track');
    if (!track) return;
    const firstItem = track.querySelector('.date-day-item');
    const step = firstItem ? (firstItem.offsetWidth + 20) : 300;
    track.scrollBy({ left: dir * step, behavior: 'smooth' });
}

window.goToStep = function(step) {
    document.querySelectorAll('.alchemy-step').forEach(s => s.style.display = 'none');
    const target = document.getElementById('alchemy-step-' + step);
    if (target) target.style.display = 'block';
    const summary = document.getElementById('booking-summary');
    if (summary) summary.style.display = (step >= 2) ? 'block' : 'none';
    if (step === 2) {
        refreshAvailability(); 
    }
    const wizard = document.querySelector('.alchemy-booking-wizard');
    if (wizard) { 
        const isMobile = window.innerWidth <= 900;
        const scrollOffset = isMobile ? -20 : 60;
        const offset = wizard.getBoundingClientRect().top + window.scrollY - scrollOffset; 
        window.scrollTo({ top: offset, behavior: 'smooth' }); 
    }
};

window.selectService = function(id, title, price, duration) { 
    booking.id = id; booking.title = title; booking.price = price; booking.duration = duration; 
    updateSummary(); 
    goToStep(2); 
};

function getISODateString(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function renderContinuousDates() {
    const track = document.getElementById('date-track');
    if (!track) return;
    track.innerHTML = '';
    const today = new Date();
    today.setHours(0,0,0,0);
    
    // Initial Month Set
    updateMonthDisplay(today);

    for (let i = 0; i < 30; i++) {
        const d = new Date();
        d.setDate(today.getDate() + i);
        const dateISO = getISODateString(d);
        const dayName = d.toLocaleDateString('en-US', { weekday: 'short' });
        const dayNum  = d.getDate();
        const label   = d.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' });

        const isAvailable = alchemyData.availableDates && alchemyData.availableDates[dateISO];
        const availClass = isAvailable ? 'is-available' : 'is-unavailable';
        const selectedClass = (booking.dateISO === dateISO) ? 'selected' : '';

        const item = document.createElement('div');
        item.className = `date-day-item ${availClass} ${selectedClass}`;
        item.dataset.date = dateISO;
        item.onclick = function() {
            if (isAvailable) {
                selectDate(dateISO, d.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric', month: 'long' }), item);
            }
        };

        item.innerHTML = `<span class="day-name">${dayName}</span><strong class="day-num">${dayNum}</strong>`;
        track.appendChild(item);
    }
    
    // Auto-scroll to first available
    const firstAvail = track.querySelector('.is-available');
    if (firstAvail && !booking.dateISO) {
        setTimeout(() => {
            firstAvail.click();
            firstAvail.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }, 100);
    }
}

function updateMonthOnScroll() {
    const track = document.getElementById('date-track');
    if (!track) return;
    const items = track.querySelectorAll('.date-day-item');
    const trackRect = track.getBoundingClientRect();
    
    // Find the first item that is significantly visible from the left
    let targetItem = items[0];
    for (const item of items) {
        const rect = item.getBoundingClientRect();
        if (rect.left >= trackRect.left - 10) {
            targetItem = item;
            break;
        }
    }

    if (targetItem && targetItem.dataset.date) {
        const d = new Date(targetItem.dataset.date + 'T00:00:00');
        updateMonthDisplay(d);
    }
}

function updateMonthDisplay(date) {
    const monthLabel = document.getElementById('display-month');
    if (monthLabel) monthLabel.innerText = date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
}

function selectDate(iso, label, element) {
    booking.dateISO = iso; booking.date = label;
    document.querySelectorAll('.date-day-item').forEach(el => el.classList.remove('selected'));
    if (element) element.classList.add('selected');

    const slots = alchemyData.availableDates[iso] || [];
    const container = document.getElementById('time-slots-container');
    if (!container) return;
    
    let html = `<h3 class="alc-available-times-head">Available Times</h3><div class="alc-time-grid">`;
    slots.forEach(slot => { 
        const sel = (booking.time === slot) ? 'selected' : ''; 
        html += `<div class="time-slot-btn ${sel}" onclick="selectTime('${slot}', this)">${slot}</div>`; 
    });
    html += '</div>';
    container.innerHTML = html;
    updateSummary();
}

window.selectTime = function(time, element) { 
    booking.time = time; 
    document.querySelectorAll('.time-slot-btn').forEach(el => el.classList.remove('selected')); 
    if (element) element.classList.add('selected'); 
    updateSummary(); 
    setTimeout(() => goToStep(3), 300); 
};

window.jumpToToday = function() {
    const today = new Date(); 
    const dateISO = getISODateString(today);
    const track = document.getElementById('date-track');
    const targetEl = track ? track.querySelector(`.date-day-item[data-date="${dateISO}"]`) : null;
    
    if (targetEl) {
        const scrollPos = targetEl.offsetLeft - (track.offsetWidth / 2) + (targetEl.offsetWidth / 2);
        track.scrollTo({ left: scrollPos, behavior: 'smooth' });
        if (targetEl.classList.contains('is-available')) {
            selectDate(dateISO, today.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric', month: 'long' }), targetEl);
        }
    } else if (track) {
        track.scrollTo({ left: 0, behavior: 'smooth' });
    }
};

async function refreshAvailability() {
    try {
        const res = await fetch(alchemyData.root + 'availability', {
            headers: { 'X-WP-Nonce': alchemyData.nonce }
        }); 
        if (res.ok) {
            const latestAvail = await res.json();
            alchemyData.availableDates = latestAvail;
            renderContinuousDates();
        }
    } catch (e) { console.error("Sync failed", e); }
}

function updateSummary() {
    const ids = { 'sum-service': booking.title, 'sum-duration': booking.duration ? booking.duration + ' mins' : '-', 'sum-time': booking.time, 'sum-date': booking.date, 'sum-price': booking.price ? '$' + booking.price : '$0' };
    for (const [id, val] of Object.entries(ids)) { const el = document.getElementById(id); if (el) el.innerText = val || '-'; }
}

window.openDetailsModal = function(title, desc) {
    const modal = document.getElementById('alc-details-modal');
    if (!modal) return;
    document.getElementById('modal-title').innerText = title;
    document.getElementById('modal-desc').innerHTML = desc;
    modal.style.display = 'flex';
};

window.closeDetailsModal = function() { const modal = document.getElementById('alc-details-modal'); if (modal) modal.style.display = 'none'; };

window.proceedToPayment = function() { if (!document.getElementById('c-name').value || !document.getElementById('c-email').value) { alert("Please provide your name and email."); return; } initStripe(); goToStep(4); };

async function initStripe() {
    if (!stripe) return;
    const container = document.getElementById('payment-element');
    if (!container) return;
    container.innerHTML = '<p>Loading Secure Payment...</p>';
    try {
        const response = await fetch(alchemyData.root + 'create-intent', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': alchemyData.nonce }, body: JSON.stringify({ amount: booking.price }) });
        const { clientSecret } = await response.json();
        elements = stripe.elements({ clientSecret });
        const paymentElement = elements.create('payment');
        container.innerHTML = '';
        paymentElement.mount('#payment-element');
    } catch (e) { container.innerHTML = '<p style="color:red;">Error connecting to Stripe.</p>'; }
}

async function handleFinalBook() {
    const btn = document.getElementById('final-pay-btn');
    if (!btn) return;
    btn.disabled = true; btn.innerText = 'Processing...';

    const { error, paymentIntent } = await stripe.confirmPayment({ 
        elements, 
        confirmParams: { return_url: window.location.href }, 
        redirect: 'if_required' 
    });

    if (error) {
        alert(error.message);
        btn.disabled = false;
        btn.innerText = 'Finalize';
    } else if (paymentIntent && paymentIntent.status === 'succeeded') {
        const res = await fetch(alchemyData.root + 'save-booking', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': alchemyData.nonce },
            body: JSON.stringify({ 
                service_id: booking.id, 
                payment_intent_id: paymentIntent.id,
                name: document.getElementById('c-name').value, 
                email: document.getElementById('c-email').value, 
                phone: document.getElementById('c-phone').value, 
                date: booking.date + ' ' + booking.time, 
                notes: document.getElementById('c-notes').value 
            })
        });
        if (res.ok) { 
            document.getElementById('alchemy-step-4').innerHTML = `<div style="text-align:center; padding:40px;"><div style="font-size:50px; color:#28a745; margin-bottom:20px;">✔</div><h2>Booking Confirmed!</h2><p>Thank you. Your appointment for <strong>${booking.title}</strong> has been secured.</p></div>`; 
        } else {
            const errData = await res.json();
            alert(errData.message || 'Error saving booking. Please contact support.');
            btn.disabled = false;
            btn.innerText = 'Finalize';
        }
    }
}

window.triggerCalendarPicker = function() { const picker = document.getElementById('alc-manual-date'); if (picker) picker.showPicker(); };
window.handleManualDate = function(val) {
    const d = new Date(val + 'T00:00:00'); const iso = val; const label = d.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric', month: 'long' });
    if (alchemyData.availableDates && alchemyData.availableDates[iso]) { selectDate(iso, label, null); } else { alert("No availability for the selected date."); }
};
function cancelAndReset() { booking = { id: null, title: '', price: '', duration: '', date: '', dateISO: '', time: '' }; updateSummary(); goToStep(1); }
function sanitize(str) { if (!str) return ''; const temp = document.createElement('div'); temp.textContent = str; return temp.innerHTML; }
