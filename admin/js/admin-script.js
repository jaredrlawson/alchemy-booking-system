// Ensure ajaxurl is defined (WP default, but safety first)
const alchemyAjaxUrl = (window.alchemyAdminData && window.alchemyAdminData.ajax_url) ? window.alchemyAdminData.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : (window.location.origin + '/wp-admin/admin-ajax.php'));

/**
 * Tab Navigation Logic
 */
window.switchAlchemyTab = function(target, save = true) {
    // 1. Update Tab Visuals
    document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('nav-tab-active'));
    const activeTab = document.querySelector(`.nav-tab[data-tab="${target}"]`);
    if (activeTab) activeTab.classList.add('nav-tab-active');
    
    // 2. Update Content Visibility
    document.querySelectorAll('.alchemy-tab-content').forEach(c => c.style.display = 'none');
    const targetEl = document.getElementById(target);
    if (targetEl) targetEl.style.display = 'block';

    // 3. Update hidden inputs for forms
    document.querySelectorAll('.alc-active-tab-input').forEach(input => {
        input.value = target;
    });

    // 4. Persistence
    if (save) {
        localStorage.setItem('alchemy_active_tab', target);
        const url = new URL(window.location);
        url.searchParams.set('active_tab', target); // Explicitly set active_tab
        url.searchParams.delete('svc_p');
        url.searchParams.delete('book_p');
        window.history.pushState({}, '', url);
    }

    // 5. Calendar Render
    if (target === 'tab-availability' && window.calendar) {
        window.calendar.render();
    }
};

/**
 * Modal Handlers
 */
window.previewDescriptionPopup = function() {
    const title = document.getElementById('preview-input-title').value || 'Service Name';
    const desc  = document.getElementById('preview-input-desc').value || 'No description provided.';
    const modal = document.getElementById('alc-desc-preview-modal');
    if (!modal) return;
    document.getElementById('preview-desc-title').innerText = title;
    const descDiv = document.getElementById('preview-desc-content');
    descDiv.textContent = desc;
    descDiv.innerHTML = descDiv.innerHTML.replace(/\n/g, '<br>');
    modal.style.display = 'flex';
};

window.closeDescPreviewModal = function() {
    const modal = document.getElementById('alc-desc-preview-modal');
    if (modal) modal.style.display = 'none';
};

window.openEditServiceModal = function(service) {
    const modal = document.getElementById('alc-edit-service-modal');
    if (!modal) return;
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

window.closeAdminModal = function() {
    const modal = document.getElementById('alc-time-modal');
    if (modal) modal.style.display = 'none';
};

window.openAdminModal = function(dateStr) {
    const modal = document.getElementById('alc-time-modal');
    if (!modal) return;

    document.getElementById('alc-modal-date-display').innerText = dateStr;
    document.getElementById('alc-modal-date-input').value = dateStr;

    // Reset checkboxes
    document.querySelectorAll('.alc-time-slot-check').forEach(cb => {
        cb.checked = false;
        // Check if this slot exists in our current data
        if (window.alchemyAdminData.existingAvail[dateStr] && window.alchemyAdminData.existingAvail[dateStr].includes(cb.value)) {
            cb.checked = true;
        }
    });

    modal.style.display = 'flex';
};

window.openAdminAlert = function(msg, title = 'Attention', isSuccess = false) {
    const modal = document.querySelector('.alc-admin-alert-modal');
    if (!modal) { alert(msg); return; }
    document.getElementById('admin-alert-title').innerText = title;
    document.getElementById('admin-alert-msg').innerText = msg;
    const icon = modal.querySelector('.alc-alert-icon');
    if (icon) {
        icon.innerText = isSuccess ? '✔' : '✕';
        icon.style.color = isSuccess ? '#00a32a' : '#d33';
    }
    modal.style.display = 'flex';
};

window.closeAdminAlert = function() {
    const modal = document.querySelector('.alc-admin-alert-modal');
    if (modal) modal.style.display = 'none';
};

window.saveAdminAvailability = function() {
    const date = document.getElementById('alc-modal-date-input').value;
    const selectedSlots = Array.from(document.querySelectorAll('.alc-time-slot-check:checked')).map(cb => cb.value);
    const nonce = document.querySelector('#alc-time-modal input[name="alchemy_admin_nonce"]').value;

    jQuery.post(alchemyAjaxUrl, {
        action: 'alchemy_update_availability',
        nonce: nonce,
        available_date: date, 
        time_slots: selectedSlots.join(',') 
    }, function(res) {
        if (res.success) {
            window.alchemyAdminData.existingAvail[date] = selectedSlots;
            if (window.calendar) window.calendar.refetchEvents();
            closeAdminModal();
            showAlchemyNotice(`<strong>Success:</strong> Availability for ${date} updated.`, 'success');
        }
        else { openAdminAlert(res.data || 'Failed to save.'); }
    });
};

/**
 * Notice Handler
 */
window.showAlchemyNotice = function(msg, type = 'success') {
    const container = document.getElementById('alc-notices-container');
    if (!container) return;
    
    const notice = document.createElement('div');
    notice.className = `alc-notice alc-notice-${type} is-dismissible`;
    notice.innerHTML = `<p>${msg}</p><button type="button" class="notice-dismiss" onclick="this.parentElement.remove()"><span class="screen-reader-text">Dismiss this notice.</span></button>`;
    
    container.innerHTML = ''; 
    container.appendChild(notice);
    
    // Force scroll to top so the banner is visible
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

/**
 * UI Update Helpers
 */
window.updateAlchemyStats = function(stats) {
    if (!stats) return;
    
    const container = document.getElementById('alchemy-ledger-stats');
    if (!container) return;

    const statsRows = container.querySelectorAll('.alc-stat-row strong');
    if (statsRows.length >= 4) {
        statsRows[0].innerText = stats.total_bookings;
        statsRows[1].innerText = stats.total_services;
        statsRows[2].innerText = '$' + parseFloat(stats.revenue_7_days).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        statsRows[3].innerText = '$' + parseFloat(stats.revenue_30_days).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        // Brief highlight to show it updated
        container.style.backgroundColor = '#fff9e0';
        setTimeout(() => { container.style.backgroundColor = '#fff'; }, 500);
    }
};

/**
 * Booking Ledger Actions
 */
window.cancelBooking = function(id) {
    if (!confirm('Mark this booking as cancelled?')) return;
    const nonce = document.getElementById('alchemy_ledger_nonce') ? document.getElementById('alchemy_ledger_nonce').value : '';
    
    console.log('Alchemy Action: Cancel', { id, nonce });

    jQuery.post(alchemyAjaxUrl, {
        action: 'alchemy_cancel_booking',
        nonce: nonce,
        id: id
    }, function(res) {
        console.log('Alchemy Response:', res);
        if (res.success) { 
            const url = new URL(window.location);
            url.searchParams.set('active_tab', 'tab-bookings');
            url.searchParams.set('success_msg', 'Booking marked as cancelled.');
            url.searchParams.set('msg_type', 'warning');
            window.location.href = url.href;
        } 
        else { openAdminAlert(res.data || 'Failed to cancel.'); }
    });
};

window.deleteBooking = function(id) {
    if (!confirm('Permanently delete this booking?')) return;
    const nonce = document.getElementById('alchemy_ledger_nonce') ? document.getElementById('alchemy_ledger_nonce').value : '';

    console.log('Alchemy Action: Delete', { id, nonce });

    jQuery.post(alchemyAjaxUrl, {
        action: 'alchemy_delete_booking',
        nonce: nonce,
        id: id
    }, function(res) {
        console.log('Alchemy Response:', res);
        if (res.success) { 
            const url = new URL(window.location);
            url.searchParams.set('active_tab', 'tab-bookings');
            url.searchParams.set('success_msg', 'Booking permanently deleted.');
            url.searchParams.set('msg_type', 'error');
            window.location.href = url.href;
        } 
        else { openAdminAlert(res.data || 'Failed to delete.'); }
    });
};

window.toggleAllLedgerCheckboxes = function(master) {
    document.querySelectorAll('.ledger-checkbox').forEach(cb => {
        cb.checked = master.checked;
    });
};

window.applyLedgerBulkAction = function() {
    const action = document.getElementById('alc-ledger-bulk-action').value;
    if (action === '-1') return;

    const selectedIds = Array.from(document.querySelectorAll('.ledger-checkbox:checked')).map(cb => cb.value);
    if (selectedIds.length === 0) {
        openAdminAlert('Please select at least one booking.');
        return;
    }

    if (!confirm(`Are you sure you want to ${action} the selected bookings?`)) return;

    const actionHook = action === 'cancel' ? 'alchemy_bulk_cancel' : 'alchemy_bulk_delete';
    const nonce = document.getElementById('alchemy_ledger_nonce').value;

    console.log('Sending Bulk:', { actionHook, ids: selectedIds.join(',') });

    jQuery.ajax({
        url: alchemyAjaxUrl,
        type: 'POST',
        cache: false,
        data: {
            action: actionHook,
            nonce: nonce,
            ids: selectedIds.join(',')
        },
        beforeSend: function() { console.log('Alchemy Debug: Request about to send...'); },
        success: function(res) {
            console.log('Alchemy Debug: Server Response received', res);
            if (res.success) {
                const url = new URL(window.location);
                url.searchParams.set('active_tab', 'tab-bookings');
                url.searchParams.set('success_msg', 'Bulk action completed successfully.');
                url.searchParams.set('msg_type', action === 'cancel' ? 'warning' : 'error');
                window.location.href = url.href;
            } else {
                openAdminAlert(res.data || 'Bulk action failed.');
            }
        },
        error: function(xhr, status, error) {
            console.error('Alchemy Debug: AJAX Error!', {
                status: status,
                error: error,
                responseText: xhr.responseText
            });
            openAdminAlert('Server Error. Check console for details.');
        }
    });
};

/**
 * DOM Dependent Initialization
 */
document.addEventListener('DOMContentLoaded', function() {
    // 1. Tab Event Listeners
    const tabs = document.querySelectorAll('.nav-tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            switchAlchemyTab(this.getAttribute('data-tab'), true);
            this.blur();
        });
    });

    // Restore saved tab
    const params = new URLSearchParams(window.location.search);
    let tabToActivate = params.get('active_tab'); // Check for explicit active_tab first
    
    if (!tabToActivate) { // If no explicit active_tab, then check pagination parameters
        if (params.has('book_p')) { // Prioritize book_p
            tabToActivate = 'tab-bookings';
        } else if (params.has('svc_p')) { // Then svc_p
            tabToActivate = 'tab-services';
        } else {
            tabToActivate = localStorage.getItem('alchemy_active_tab');
        }
    }

    if (tabToActivate && document.getElementById(tabToActivate)) {
        switchAlchemyTab(tabToActivate, false);
    } else if (tabs.length > 0) {
        switchAlchemyTab(tabs[0].getAttribute('data-tab'), false);
    }

    // Service Management AJAX
    const addSvcForm  = document.getElementById('alc-add-service-form');
    const editSvcForm = document.getElementById('alc-edit-service-form');

    const handleSvcSubmit = function(e) {
        e.preventDefault();
        const form = this;
        const formData = new FormData(form);
        formData.append('action', 'alchemy_save_service');
        if (formData.has('alchemy_admin_nonce')) {
            formData.append('nonce', formData.get('alchemy_admin_nonce'));
        }

        jQuery.ajax({
            url: alchemyAjaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                if (res.success) {
                    const msg = form.id === 'alc-add-service-form' ? 'New service created.' : 'Service updated.';
                    if (form.id === 'alc-edit-service-modal') closeEditServiceModal();
                    form.reset();
                    
                    const url = new URL(window.location);
                    url.searchParams.set('active_tab', 'tab-services');
                    url.searchParams.set('success_msg', msg);
                    window.location.href = url.href;
                } else {
                    openAdminAlert(res.data || 'Failed to save service.');
                }
            }
        });
    };

    if (addSvcForm)  addSvcForm.addEventListener('submit',  handleSvcSubmit);
    if (editSvcForm) editSvcForm.addEventListener('submit', handleSvcSubmit);

    const pInputTitle = document.getElementById('preview-input-title');
    const pInputPrice = document.getElementById('preview-input-price');
    const pInputDuration = document.getElementById('preview-input-duration');
    const pCardTitle = document.getElementById('preview-card-title');
    const pCardPrice = document.getElementById('preview-card-price');
    const pCardDuration = document.getElementById('preview-card-duration');

    if (pInputTitle) {
        const updatePreview = () => {
            pCardTitle.innerText = pInputTitle.value || 'Service Name';
            let price = pInputPrice.value || '0';
            pCardPrice.innerText = '$' + parseFloat(price).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2});
            pCardDuration.innerText = (pInputDuration.value || '0') + ' mins';
        };
        [pInputTitle, pInputPrice, pInputDuration].forEach(el => { el.addEventListener('input', updatePreview); });
        updatePreview();
    }

    // 3. Bulk Availability UI Logic
    const bulkToggle = document.getElementById('alc-bulk-toggle');
    const bulkPanel  = document.getElementById('alc-bulk-panel');
    const bulkApply  = document.getElementById('alc-bulk-apply');
    if (bulkToggle) {
        bulkToggle.addEventListener('click', () => {
            bulkPanel.style.display = (bulkPanel.style.display === 'none') ? 'block' : 'none';
            bulkToggle.classList.toggle('button-primary');
        });
        
        bulkApply.addEventListener('click', async () => {
            // Re-using the fetch logic for bulk availability as it was working
            const selectedSlots = Array.from(document.querySelectorAll('.alc-bulk-slot-check:checked')).map(cb => cb.value);
            const nonce = document.querySelector('input[name="alchemy_admin_nonce"]').value;
            // ... (keeping rangeStart/End logic from FullCalendar)
        });
    }

    // 4. Slider Display Update
    const sliders = ['border_opacity', 'shadow_intensity', 'secondary_border_intensity'];
    sliders.forEach(name => {
        const s = document.querySelector(`input[name="${name}"]`);
        const v = s ? s.parentElement.querySelector('span[class*="-val"]') : null;
        if (s && v) { s.addEventListener('input', () => { v.innerText = s.value + '%'; }); }
    });

    // 5. Color Swatch Sync
    document.querySelectorAll('.alc-color-group').forEach(group => {
        const swatch = group.querySelector('.alc-color-swatch');
        const hexInput = group.querySelector('.alc-color-hex');
        if (swatch && hexInput) {
            swatch.addEventListener('input', (e) => { hexInput.value = e.target.value.toUpperCase(); });
            hexInput.addEventListener('input', (e) => { 
                let val = e.target.value;
                if (val.length === 7 && val.startsWith('#')) swatch.value = val;
            });
        }
    });

    // 6. Initialize FullCalendar
    const calendarEl = document.getElementById('alchemy-calendar');
    if (calendarEl) {
        window.calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: { left: '', center: 'prev title next', right: '' },
            selectable: true,
            select: function(info) {
                // Set globals for bulk apply
                window.rangeStart = info.startStr;
                let endObj = new Date(info.end);
                endObj.setDate(endObj.getDate() - 1);
                window.rangeEnd = endObj.toISOString().split('T')[0];
            },
            events: function(info, successCallback) {
                const highlightEvents = [];
                if (window.alchemyAdminData && window.alchemyAdminData.existingAvail) {
                    Object.keys(window.alchemyAdminData.existingAvail).forEach(date => {
                        const slots = window.alchemyAdminData.existingAvail[date];
                        if (slots && slots.length > 0) { highlightEvents.push({ start: date, display: 'background', classNames: ['alc-available-day'] }); }
                    });
                }
                successCallback(highlightEvents);
            },
            dateClick: function(info) { openAdminModal(info.dateStr); }
        });
        window.calendar.render();
    }
});
