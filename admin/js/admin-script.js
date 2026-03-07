document.addEventListener('DOMContentLoaded', function() {
    // 1. Tab Navigation Logic
    const tabs = document.querySelectorAll('.nav-tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            const target = this.getAttribute('data-tab');
            
            // 1. Update Tab Visuals
            document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('nav-tab-active'));
            this.classList.add('nav-tab-active');
            
            // 2. Update Content Visibility
            document.querySelectorAll('.alchemy-tab-content').forEach(c => c.style.display = 'none');
            const targetEl = document.getElementById(target);
            if (targetEl) targetEl.style.display = 'block';

            // 3. Update all hidden inputs so forms know which tab we are on
            document.querySelectorAll('.alc-active-tab-input').forEach(input => {
                input.value = target;
            });

            // 4. Re-render calendar if needed
            if (target === 'tab-availability' && window.calendar) {
                window.calendar.render();
            }
        });
    });

    // 2. Live Service Preview Logic
    const pInputTitle = document.getElementById('preview-input-title');
    const pInputDesc = document.getElementById('preview-input-desc');
    const pInputPrice = document.getElementById('preview-input-price');
    const pInputDuration = document.getElementById('preview-input-duration');

    const pCardTitle = document.getElementById('preview-card-title');
    const pCardDesc = document.getElementById('preview-card-desc');
    const pCardPrice = document.getElementById('preview-card-price');
    const pCardDuration = document.getElementById('preview-card-duration');

    if (pInputTitle) {
        const updatePreview = () => {
            pCardTitle.innerText = pInputTitle.value || 'Service Name';
            pCardDesc.innerText = pInputDesc.value || 'Your service description will appear here...';
            pCardPrice.innerText = '$' + (pInputPrice.value || '0');
            pCardDuration.innerText = (pInputDuration.value || '0') + ' mins';
        };

        [pInputTitle, pInputDesc, pInputPrice, pInputDuration].forEach(el => {
            el.addEventListener('input', updatePreview);
        });
    }

    // 3. Color Picker & Hex Sync
    document.querySelectorAll('.alc-color-group').forEach(group => {
        const swatch = group.querySelector('.alc-color-swatch');
        const hexInput = group.querySelector('.alc-color-hex');

        if (swatch && hexInput) {
            swatch.addEventListener('input', () => {
                hexInput.value = swatch.value.toUpperCase();
            });
            hexInput.addEventListener('input', () => {
                const val = hexInput.value;
                if (/^#[0-9A-F]{6}$/i.test(val)) {
                    swatch.value = val;
                }
            });
        }
    });

    // 4. Slider Display Update
    const opacitySlider = document.querySelector('input[name="border_opacity"]');
    const opacityVal = document.querySelector('.alc-slider-val');
    if (opacitySlider && opacityVal) {
        opacitySlider.addEventListener('input', () => {
            opacityVal.innerText = opacitySlider.value + '%';
        });
    }

    const shadowSlider = document.querySelector('input[name="shadow_intensity"]');
    const shadowVal = document.querySelector('.alc-slider-val-shadow');
    if (shadowSlider && shadowVal) {
        shadowSlider.addEventListener('input', () => {
            shadowVal.innerText = shadowSlider.value + '%';
        });
    }

    const secondarySlider = document.querySelector('input[name="secondary_border_intensity"]');
    const secondaryVal = document.querySelector('.alc-slider-val-secondary');
    if (secondarySlider && secondaryVal) {
        secondarySlider.addEventListener('input', () => {
            secondaryVal.innerText = secondarySlider.value + '%';
        });
    }

    // 5. Initialize FullCalendar
    const calendarEl = document.getElementById('alchemy-calendar');
    if (calendarEl) {
        window.calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: '',
                center: 'prev title next',
                right: ''
            },
            selectable: true,
            events: function(info, successCallback) {
                const highlightEvents = [];
                if (window.alchemyAdminData && window.alchemyAdminData.existingAvail) {
                    Object.keys(window.alchemyAdminData.existingAvail).forEach(date => {
                        const slots = window.alchemyAdminData.existingAvail[date];
                        if (slots && slots.length > 0) {
                            highlightEvents.push({
                                start: date,
                                display: 'background',
                                classNames: ['alc-available-day'] 
                            });
                        }
                    });
                }
                successCallback(highlightEvents);
            },
            dateClick: function(info) {
                openAdminModal(info.dateStr);
            }
        });
        window.calendar.render();
    }
});

/**
 * Service Edit Modal Handlers
 * Triggered by the PHP-rendered buttons in the Service table
 */
window.openEditServiceModal = function(service) {
    const modal = document.getElementById('alc-edit-service-modal');
    if (!modal) return;

    // Populate the hidden form fields inside the modal
    document.getElementById('edit-svc-id').value = service.id;
    document.getElementById('edit-svc-title').value = service.title;
    document.getElementById('edit-svc-category').value = service.category || 'General';
    document.getElementById('edit-svc-price').value = service.price;
    document.getElementById('edit-svc-duration').value = service.duration;
    document.getElementById('edit-svc-description').value = service.description || '';
    
    modal.style.display = 'flex';
};

window.closeEditServiceModal = function() {
    const modal = document.getElementById('alc-edit-service-modal');
    if (modal) modal.style.display = 'none';
};

/**
 * Availability Modal Logic (Time Slots)
 */
function openAdminModal(dateStr) {
    const modal = document.getElementById('alc-time-modal');
    const label = document.getElementById('modal-date-label');
    const input = document.getElementById('target_date_input');
    const list = document.getElementById('slots-checkbox-list');

    if (!modal) return;

    label.innerText = "Syncing Slots for: " + dateStr;
    input.value = dateStr;
    list.innerHTML = '<p style="text-align:center; padding:20px;">Loading Ledger Data...</p>';

    setTimeout(() => {
        label.innerText = "Manage Services: " + dateStr;
        list.innerHTML = '';
        const savedSlots = (window.alchemyAdminData && window.alchemyAdminData.existingAvail[dateStr]) || [];

        if (window.alchemyAdminData && window.alchemyAdminData.globalSlots) {
            window.alchemyAdminData.globalSlots.forEach(slot => {
                const isChecked = savedSlots.includes(slot) ? 'checked' : '';
                const item = document.createElement('div');
                item.className = 'slot-checkbox-item';
                item.style.marginBottom = '10px';
                item.innerHTML = `
                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:5px; border-bottom:1px solid #fafafa;">
                        <input type="checkbox" name="slots[]" value="${slot}" ${isChecked}>
                        <span style="font-size:14px;">${slot}</span>
                    </label>
                `;
                list.appendChild(item);
            });
        } else {
            list.innerHTML = '<p style="color:red;">No global slots defined in Settings.</p>';
        }
    }, 150);

    modal.style.display = 'flex';
}

window.closeAdminModal = function() {
    const modal = document.getElementById('alc-time-modal');
    if (modal) modal.style.display = 'none';
};