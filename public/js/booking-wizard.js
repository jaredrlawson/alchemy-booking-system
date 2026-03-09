/**
 * Alchemy Booking Wizard JS
 * Extremely robust Singleton pattern for Stripe and single-execution Redirect handling.
 */

// Global Singleton State
window.alchemyStripeInstance = null;
window.alchemyStripeElements = null;
window.alchemyStripePaymentElement = null;
window.alchemyLastPrice = null;
window.alchemyRedirectProcessed = false;
window.alchemyWizardInstances = [];

class AlchemyWizard {
    constructor(container) {
        this.container = container;
        this.booking = { id: null, title: '', price: '', duration: '', date: '', dateISO: '', time: '' };
        this.isStripeLoading = false;
        this.isFinalizing = false;
        this.isFinalized = false;
        
        this.init();
        window.alchemyWizardInstances.push(this);
        
        // Hide on success of ANY wizard
        window.addEventListener('alchemy-booking-success', (e) => {
            if (e.detail.instance !== this) {
                const wrapper = this.container.closest('.alchemy-booking-wizard-wrapper') || this.container;
                wrapper.style.display = 'none';
            }
        });
    }

    init() {
        const q = (s) => this.container.querySelector(s);
        this.initServices();
        
        q('.proceed-to-pay-btn')?.addEventListener('click', () => this.proceedToPayment());
        q('.final-pay-btn')?.addEventListener('click', () => this.handleFinalBook());
        q('.cancel-reset-btn')?.addEventListener('click', () => this.cancelAndReset());
        
        // Restore Back Button Functionality
        this.container.querySelectorAll('.back-to-step-1, .back-to-services').forEach(btn => btn.onclick = () => this.goToStep(1));
        this.container.querySelectorAll('.back-to-step-2').forEach(btn => btn.onclick = () => this.goToStep(2));
        this.container.querySelectorAll('.back-to-step-3').forEach(btn => btn.onclick = () => this.goToStep(3));

        // Global Modal Close Handlers (Robust delegation)
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('close-details-modal')) this.closeDetailsModal();
            if (e.target.classList.contains('close-alert-modal')) this.closeAlertModal();
        });
        
        const calendarLabel = q('.alc-calendar-label');
        const manualPicker = q('.alc-manual-date');
        calendarLabel?.addEventListener('click', () => manualPicker?.showPicker());
        manualPicker?.addEventListener('change', (e) => this.handleManualDate(e.target.value));
        if (q('.date-track')) q('.date-track').addEventListener('scroll', () => this.updateMonthOnScroll());
    }

    async initServices() {
        const grid = this.container.querySelector('.alchemy-services-grid');
        if (!grid) return;
        try {
            const res = await fetch(alchemyData.root + 'services', { headers: { 'X-WP-Nonce': alchemyData.nonce } });
            const services = await res.json();
            this.renderServices(services);
        } catch (e) { grid.innerHTML = '<p>Error loading services.</p>'; }
    }

    renderServices(services) {
        const grid = this.container.querySelector('.alchemy-services-grid');
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
                card.innerHTML = `
                    <div class="card-header-row"><h3>${this.sanitize(s.title)}</h3><span class="card-price">$${s.price}</span></div>
                    <div class="card-duration"><span class="dashicons dashicons-clock"></span> ${s.duration} mins</div>
                    <div class="card-footer">
                        <button class="btn-details">Details</button>
                        <button class="btn-book"><span class="dashicons dashicons-clock"></span> Book Now</button>
                    </div>
                `;
                subGrid.appendChild(card);
                card.querySelector('.btn-details').onclick = () => this.openDetailsModal(s.title, s.description);
                card.querySelector('.btn-book').onclick = () => this.selectService(s.id, s.title, s.price, s.duration);
            });
            grid.appendChild(subGrid);
        });
    }

    selectService(id, title, price, duration) {
        this.booking.id = id; this.booking.title = title; this.booking.price = price; this.booking.duration = duration;
        this.updateSummary();
        this.goToStep(2);
    }

    goToStep(step) {
        this.container.querySelectorAll('.alchemy-step').forEach(s => s.style.display = 'none');
        const target = this.container.querySelector('.alchemy-step-' + step);
        if (target) target.style.display = 'block';

        const summary = this.container.querySelector('.booking-summary');
        if (summary) {
            const shouldHide = (step < 2 || step === 'success' || this.isFinalizing || this.isFinalized);
            summary.style.display = shouldHide ? 'none' : 'block';
        }

        if (step === 2) this.refreshAvailability();

        const isMobile = window.innerWidth <= 900;
        const scrollOffset = isMobile ? -20 : 60; // Server standard
        const offset = this.container.getBoundingClientRect().top + window.scrollY - scrollOffset;
        window.scrollTo({ top: offset, behavior: 'smooth' });
    }

    renderContinuousDates() {
        const track = this.container.querySelector('.date-track');
        if (!track) return;
        track.innerHTML = '';
        const today = new Date();
        today.setHours(0,0,0,0);
        this.updateMonthDisplay(today);

        for (let i = 0; i < 30; i++) {
            const d = new Date();
            d.setDate(today.getDate() + i);
            const dateISO = this.getISODateString(d);
            const dayName = d.toLocaleDateString('en-US', { weekday: 'short' });
            const dayNum  = d.getDate();

            const isAvailable = alchemyData.availableDates && alchemyData.availableDates[dateISO];
            const availClass = isAvailable ? 'is-available' : 'is-unavailable';
            const selectedClass = (this.booking.dateISO === dateISO) ? 'selected' : '';

            const item = document.createElement('div');
            item.className = `date-day-item ${availClass} ${selectedClass}`;
            item.dataset.date = dateISO;
            item.onclick = () => {
                if (isAvailable) {
                    this.selectDate(dateISO, d.toLocaleDateString('en-US', { weekday: 'short', month: 'long', day: 'numeric', year: 'numeric' }), item);
                }
            };
            item.innerHTML = `<span class="day-name">${dayName}</span><strong class="day-num">${dayNum}</strong>`;
            track.appendChild(item);
        }
        
        if (!this.booking.dateISO) {
            const firstAvail = track.querySelector('.is-available');
            if (firstAvail) {
                setTimeout(() => {
                    firstAvail.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }, 400);
            }
        }
    }

    selectDate(iso, label, element) {
        this.booking.dateISO = iso; this.booking.date = label;
        this.container.querySelectorAll('.date-day-item').forEach(el => el.classList.remove('selected'));
        if (element) element.classList.add('selected');

        const slots = alchemyData.availableDates[iso] || [];
        const container = this.container.querySelector('.time-slots-container');
        if (!container) return;
        
        let html = `<h3 class="alc-available-times-head">Available Times</h3><div class="alc-time-grid">`;
        slots.forEach(slot => { 
            const sel = (this.booking.time === slot) ? 'selected' : ''; 
            html += `<div class="time-slot-btn ${sel}">${slot}</div>`; 
        });
        html += '</div>';
        container.innerHTML = html;
        
        container.querySelectorAll('.time-slot-btn').forEach(btn => {
            btn.onclick = () => this.selectTime(btn.innerText, btn);
        });
        this.updateSummary();
    }

    selectTime(time, element) {
        this.booking.time = time;
        this.container.querySelectorAll('.time-slot-btn').forEach(el => el.classList.remove('selected'));
        if (element) element.classList.add('selected');
        this.updateSummary();
        setTimeout(() => this.goToStep(3), 300);
    }

    jumpToToday() {
        const today = new Date();
        const dateISO = this.getISODateString(today);
        const track = this.container.querySelector('.date-track');
        const targetEl = track ? track.querySelector(`.date-day-item[data-date="${dateISO}"]`) : null;
        if (targetEl) {
            track.scrollTo({ left: targetEl.offsetLeft - (track.offsetWidth / 2) + (targetEl.offsetWidth / 2), behavior: 'smooth' });
            if (targetEl.classList.contains('is-available')) {
                this.selectDate(dateISO, today.toLocaleDateString('en-US', { weekday: 'short', month: 'long', day: 'numeric', year: 'numeric' }), targetEl);
            }
        }
    }

    handleManualDate(val) {
        const d = new Date(val + 'T00:00:00');
        const iso = val;
        const label = d.toLocaleDateString('en-US', { weekday: 'short', month: 'long', day: 'numeric', year: 'numeric' });
        if (alchemyData.availableDates && alchemyData.availableDates[iso]) {
            this.selectDate(iso, label, null);
        } else { this.showAlert("No availability for the selected date."); }
    }

    async refreshAvailability() {
        try {
            const res = await fetch(alchemyData.root + 'availability', { headers: { 'X-WP-Nonce': alchemyData.nonce } });
            if (res.ok) {
                alchemyData.availableDates = await res.json();
                this.renderContinuousDates();
            }
        } catch (e) { console.error("Sync failed", e); }
    }

    updateSummary() {
        const mapping = { 'sum-service': this.booking.title, 'sum-duration': this.booking.duration ? this.booking.duration + ' mins' : '-', 'sum-time': this.booking.time, 'sum-date': this.booking.date, 'sum-price': this.booking.price ? '$' + this.booking.price : '$0' };
        for (const [cls, val] of Object.entries(mapping)) {
            const el = this.container.querySelector('.' + cls);
            if (el) el.innerText = val || '-';
        }
    }

    async proceedToPayment() {
        const name = this.container.querySelector('.c-name').value;
        const email = this.container.querySelector('.c-email').value;
        if (!name || !email) { this.showAlert("Please provide your name and email."); return; }
        
        await this.initStripe();
        this.goToStep(4);
    }

    async initStripe() {
        if (typeof Stripe === 'undefined' || !alchemyData.stripePubKey || this.isStripeLoading) return;
        
        if (window.alchemyStripeElements && window.alchemyLastPrice === this.booking.price) {
            const container = this.container.querySelector('.payment-element');
            if (container) {
                container.innerHTML = '';
                window.alchemyStripePaymentElement.mount(container);
            }
            return;
        }

        this.isStripeLoading = true;
        window.alchemyLastPrice = this.booking.price;

        const stripeObj = window.alchemyStripeInstance || (window.alchemyStripeInstance = Stripe(alchemyData.stripePubKey));
        const container = this.container.querySelector('.payment-element');
        if (!container) { this.isStripeLoading = false; return; }
        
        container.innerHTML = this.getLoaderHTML('Loading Secure Payment...');
        
        try {
            if (window.alchemyStripePaymentElement) {
                try { window.alchemyStripePaymentElement.destroy(); } catch(e) {}
            }

            const response = await fetch(alchemyData.root + 'create-intent', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': alchemyData.nonce },
                body: JSON.stringify({ amount: this.booking.price })
            });
            const { clientSecret } = await response.json();
            
            window.alchemyStripeElements = stripeObj.elements({ clientSecret });
            window.alchemyStripePaymentElement = window.alchemyStripeElements.create('payment');
            
            container.innerHTML = '';
            window.alchemyStripePaymentElement.mount(container);
        } catch (e) { 
            container.innerHTML = '<p style="color:red;">Error connecting to Stripe.</p>'; 
            window.alchemyLastPrice = null;
        } finally {
            this.isStripeLoading = false;
        }
    }

    async handleFinalBook() {
        const btn = this.container.querySelector('.final-pay-btn');
        if (!btn || !window.alchemyStripeElements || this.isFinalizing) return;
        this.isFinalizing = true;

        const sidebar = this.container.closest('.alc-flex-layout')?.querySelector('.booking-summary');
        if (sidebar) sidebar.style.display = 'none';

        const stripeObj = window.alchemyStripeInstance;
        
        localStorage.setItem('alchemy_pending_booking', JSON.stringify({
            booking: this.booking,
            name: this.container.querySelector('.c-name').value,
            email: this.container.querySelector('.c-email').value,
            phone: this.container.querySelector('.c-phone').value,
            notes: this.container.querySelector('.c-notes').value,
            timestamp: Date.now()
        }));

        const { error, paymentIntent } = await stripeObj.confirmPayment({
            elements: window.alchemyStripeElements,
            confirmParams: { return_url: window.location.href },
            redirect: 'if_required'
        });

        if (error) {
            this.showAlert(error.message, 'Payment Error');
            this.isFinalizing = false;
            btn.disabled = false; btn.innerText = 'Finalize';
            if (sidebar) sidebar.style.display = 'block';
        } else if (paymentIntent && paymentIntent.status === 'succeeded') {
            await this.finalizeBookingOnServer(paymentIntent.id);
        }
    }

    async finalizeBookingOnServer(intentId) {
        const sidebar = this.container.closest('.alc-flex-layout')?.querySelector('.booking-summary');
        if (sidebar) sidebar.style.display = 'none';

        if (window.alchemyStripePaymentElement) {
            try { window.alchemyStripePaymentElement.destroy(); } catch(e) {}
            window.alchemyStripePaymentElement = null;
            window.alchemyStripeElements = null;
        }

        const rawData = localStorage.getItem('alchemy_pending_booking');
        const data = rawData ? JSON.parse(rawData) : {
            booking: this.booking,
            name: this.container.querySelector('.c-name').value,
            email: this.container.querySelector('.c-email').value,
            phone: this.container.querySelector('.c-phone').value,
            notes: this.container.querySelector('.c-notes').value
        };

        const res = await fetch(alchemyData.root + 'save-booking', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': alchemyData.nonce },
            body: JSON.stringify({
                service_id: data.booking.id,
                payment_intent_id: intentId,
                name: data.name,
                email: data.email,
                phone: data.phone,
                date: data.booking.date + ' ' + data.booking.time,
                notes: data.notes
            })
        });

        if (res.ok) {
            localStorage.removeItem('alchemy_pending_booking');
            window.dispatchEvent(new CustomEvent('alchemy-booking-success', { detail: { instance: this } }));
            setTimeout(() => this.showSuccess(), 4000);
        } else {
            const errData = await res.json();
            this.showAlert(errData.message || 'Error saving booking.', 'Booking Error');
            this.isFinalizing = false;
            const btn = this.container.querySelector('.final-pay-btn');
            if (btn) { btn.disabled = false; btn.innerText = 'Finalize'; }
            if (sidebar) sidebar.style.display = 'block';
        }
    }

    showSuccess() {
        this.isFinalized = true;
        const sidebar = this.container.closest('.alc-flex-layout')?.querySelector('.booking-summary');
        if (sidebar) sidebar.style.display = 'none';

        this.container.innerHTML = `
            <div style="text-align:center; padding:60px 20px; background:white; border-radius:var(--alc-radius, 12px); border:1px solid #eee; width:100%;">
                <div style="font-size:60px; color:#28a745; margin-bottom:20px;">✔</div>
                <h2 style="font-size:32px; margin-bottom:15px; color:var(--alc-head, #111);">Booking Confirmed!</h2>
                <p style="font-size:20px; color:#666;">Thank you. Your appointment for <strong>${this.booking.title}</strong> has been secured.</p>
                <div style="margin-top:25px;">
                    <button class="alc-pill-btn" onclick="window.location.reload()">Book Another</button>
                </div>
            </div>
        `;

        const isMobile = window.innerWidth <= 900;
        const scrollOffset = isMobile ? 90 : 190; 
        const offset = this.container.getBoundingClientRect().top + window.scrollY - scrollOffset;
        window.scrollTo({ top: offset, behavior: 'smooth' });
    }

    getLoaderHTML(text = 'Verifying payment...') {
        const style = alchemyData.loaderStyle || 'aura';
        let html = `<div class="alc-loader-wrapper">`;
        if (style === 'aura') {
            html += `<div class="alc-aura-text">${text}</div><div class="alc-dots"><span>.</span><span>.</span><span>.</span></div>`;
        } else if (style === 'scan') {
            html += `<div class="alc-scan-container"><div class="alc-scan-line"></div><div class="alc-aura-text" style="text-shadow:none;">${text}</div></div>`;
        } else if (style === 'flask') {
            html += `<div class="alc-flask-icon"><div class="alc-flask-fill"></div></div><div class="alc-aura-text" style="text-shadow:none;">${text}</div>`;
        } else {
            html += `<div class="alc-spinner"></div><p>${text}</p>`;
        }
        html += `</div>`;
        return html;
    }

    updateMonthOnScroll() {
        const track = this.container.querySelector('.date-track');
        const items = track.querySelectorAll('.date-day-item');
        const trackRect = track.getBoundingClientRect();
        let targetItem = items[0];
        let minDiff = Infinity;
        items.forEach(item => {
            const itemRect = item.getBoundingClientRect();
            const diff = Math.abs((itemRect.left + itemRect.width/2) - (trackRect.left + trackRect.width/2));
            if (diff < minDiff) { minDiff = diff; targetItem = item; }
        });
        if (targetItem) this.updateMonthDisplay(new Date(targetItem.dataset.date + 'T00:00:00'));
    }

    updateMonthDisplay(date) {
        const el = this.container.querySelector('.display-month');
        if (el) el.innerText = date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    }

    openDetailsModal(title, desc) {
        const modal = document.querySelector('.alc-details-modal');
        if (!modal) return;
        modal.querySelector('.modal-title').innerText = title;
        modal.querySelector('.modal-desc').innerHTML = desc ? desc.replace(/\n/g, '<br>') : '<em>No description provided.</em>';
        modal.style.display = 'flex';
    }

    closeDetailsModal() {
        const modal = document.querySelector('.alc-details-modal');
        if (modal) modal.style.display = 'none';
    }

    showAlert(msg, title = 'Attention') {
        const modal = document.querySelector('.alc-alert-modal');
        if (!modal) { alert(msg); return; }
        modal.querySelector('.modal-alert-title').innerText = title;
        modal.querySelector('.modal-alert-msg').innerText = msg;
        modal.style.display = 'flex';
    }

    closeAlertModal() {
        const modal = document.querySelector('.alc-alert-modal');
        if (modal) modal.style.display = 'none';
    }

    cancelAndReset() {
        this.booking = { id: null, title: '', price: '', duration: '', date: '', dateISO: '', time: '' };
        this.updateSummary();
        this.goToStep(1);
    }

    getISODateString(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    sanitize(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

/**
 * Global Theme Sync
 */
function syncThemeStyles() {
    const refHead = document.querySelector('.entry-title, #content h1, #content h2, article h1, article h2, h1, h2, h3, .elementor-heading-title');
    const refBtn = document.querySelector('.elementor-button, .btn, button:not(.alchemy-booking-wizard button), .wp-block-button__link');
    const fontRef = refHead || refBtn || document.body;
    
    if (fontRef) {
        const styles = window.getComputedStyle(fontRef);
        
        // Priority 1: User selection from dashboard. Priority 2: Harvested site font.
        let finalFont = alchemyData.buttonFont && alchemyData.buttonFont !== 'inherit' ? alchemyData.buttonFont : styles.fontFamily;
        
        try {
            const finalRadius = (alchemyData.borderRadius || '12') + 'px';
            const btnText = alchemyData.buttonText || '#ffffff';
            let currentHeadColor = alchemyData.headingColor || '#111111';
            let btnColor = alchemyData.buttonColor || '#111111';
            let hoverColor = alchemyData.hoverColor || '#c5a000';
            const selectColor = alchemyData.selectedDayColor || '#c5a000';
            const rgbaBlack = `rgba(0,0,0,${(alchemyData.secondaryIntensity || 100) / 100})`;

            if (alchemyData.useTheme === '1') {
                const harvestedHead = window.getComputedStyle(refHead || document.body).color;
                if (harvestedHead) currentHeadColor = harvestedHead;
                if (refBtn) {
                    const harvestedBtn = window.getComputedStyle(refBtn).backgroundColor;
                    if (harvestedBtn && harvestedBtn !== 'rgba(0, 0, 0, 0)') btnColor = harvestedBtn;
                }
            }

            const css = `
                .alchemy-booking-wizard *:not(.dashicons) { font-family: ${finalFont} !important; }
                :root {
                    --alc-gold: ${btnColor};
                    --alc-hover: ${hoverColor};
                    --alc-head: ${currentHeadColor};
                    --alc-selected: ${selectColor};
                    --alc-btn-text: ${btnText};
                    --alc-radius: ${finalRadius};
                }
                .alchemy-booking-wizard .btn-book, 
                .alchemy-booking-wizard .alc-btn-primary { background-color: ${btnColor} !important; color: ${btnText} !important; border: 1px solid ${btnColor} !important; font-size: 20px !important; }
                .alchemy-booking-wizard .btn-book:hover, 
                .alchemy-booking-wizard .alc-btn-primary:hover { background-color: ${hoverColor} !important; border-color: ${hoverColor} !important; color: ${btnText} !important; }
                .alchemy-booking-wizard .btn-details { font-size: 20px !important; }
                .alchemy-booking-wizard .btn-details, .alchemy-booking-wizard .alc-pill-btn, .alchemy-booking-wizard .time-slot-btn, .alchemy-booking-wizard .alc-form-field { border: 1px solid ${rgbaBlack} !important; color: #000 !important; background: transparent !important; }
                .alchemy-booking-wizard .btn-details:hover, .alchemy-booking-wizard .alc-pill-btn:hover, .alchemy-booking-wizard .time-slot-btn:hover { border-color: ${hoverColor} !important; color: ${hoverColor} !important; background: transparent !important; }
                .alchemy-booking-wizard .date-day-item.selected { color: ${selectColor} !important; border-bottom-color: ${selectColor} !important; }
                .alchemy-booking-wizard .alc-category-heading, .alchemy-booking-wizard .alc-view-heading, .alchemy-booking-wizard .alc-month-row h2, .alchemy-booking-wizard .alc-summary-card h3, .alchemy-booking-wizard .alc-summary-total, .alchemy-booking-wizard .alc-summary-total span, .alchemy-booking-wizard .card-price, .alchemy-booking-wizard .alc-available-times-head { color: ${currentHeadColor} !important; }
            `;
            const style = document.createElement('style');
            style.id = 'alchemy-dynamic-theme-styles';
            style.textContent = css;
            document.getElementById('alchemy-dynamic-theme-styles')?.remove();
            document.head.appendChild(style);
            document.querySelectorAll('.alchemy-booking-wizard').forEach(el => el.classList.add('alc-revealed'));
        } catch (e) { console.error("Alchemy Sync Error:", e); }
    }
}

/**
 * Global Redirect Handler
 */
async function handleGlobalStripeRedirect() {
    const params = new URLSearchParams(window.location.search);
    const intentId = params.get('payment_intent');
    const status = params.get('redirect_status');

    if (intentId && status === 'succeeded' && !window.alchemyRedirectProcessed) {
        window.alchemyRedirectProcessed = true;
        const processingLock = `alc_processing_${intentId}`;
        if (localStorage.getItem(processingLock)) return;
        localStorage.setItem(processingLock, 'true');

        const rawData = localStorage.getItem('alchemy_pending_booking');
        if (!rawData) return;
        const data = JSON.parse(rawData);
        const wizard = window.alchemyWizardInstances[0];
        if (wizard) {
            wizard.isFinalizing = true;
            wizard.booking = data.booking;
            const q = (s) => wizard.container.querySelector(s);
            if (q('.c-name')) q('.c-name').value = data.name;
            if (q('.c-email')) q('.c-email').value = data.email;
            if (q('.c-phone')) q('.c-phone').value = data.phone;
            if (q('.c-notes')) q('.c-notes').value = data.notes;
            
            wizard.goToStep(4);
            q('.alchemy-step-4').innerHTML = wizard.getLoaderHTML('Verifying payment...');
            window.history.replaceState({}, document.title, window.location.pathname);
            
            setTimeout(async () => {
                await wizard.finalizeBookingOnServer(intentId);
                setTimeout(() => localStorage.removeItem(processingLock), 5000);
            }, 2000);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Always run syncThemeStyles to apply custom dashboard colors
    setTimeout(syncThemeStyles, 150);
    document.querySelectorAll('.alchemy-booking-wizard').forEach(el => new AlchemyWizard(el));
    handleGlobalStripeRedirect();
});
