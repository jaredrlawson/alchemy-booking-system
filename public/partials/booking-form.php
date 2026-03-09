<div class="alchemy-booking-wizard">
    <div class="alc-flex-layout">
        <div class="alc-main-panel">
            
            <div class="alchemy-step alchemy-step-1">
                <h2 class="alc-view-heading">Step 1: Choose Your Service</h2>
                <div class="alchemy-services-grid"></div>
            </div>

            <div class="alchemy-step alchemy-step-2" style="display:none;">
                <h2 class="alc-view-heading">Step 2: Select Date & Time</h2>
                
                <div class="alc-step-container">
                    <div class="alc-scheduler-header">
                        <div class="alc-month-row">
                            <h2 class="display-month">...</h2>
                            <div class="alc-datepicker-trigger">
                                <span class="alc-calendar-label alc-pointer">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                </span>
                                <input type="date" class="alc-manual-date alc-hidden-picker">
                            </div>
                        </div>
                        
                        <div class="alc-today-box">
                            <button class="alc-pill-btn jump-to-today-btn">TODAY</button>
                        </div>
                    </div>

                    <div class="alc-scroller-box">
                        <button class="scroller-nav-btn nav-prev">‹</button>
                        <div class="date-track"></div>
                        <button class="scroller-nav-btn nav-next">›</button>
                    </div>

                    <div class="time-slots-container"></div>
                    
                    <div class="alc-mt-30">
                        <button class="alc-link-btn back-to-services">← Back to Services</button>
                    </div>
                </div>
            </div>

            <div class="alchemy-step alchemy-step-3" style="display:none;">
                <h2 class="alc-view-heading">Step 3: Your Details</h2>
                <div class="alc-form-container">
                    <div class="alc-payment-box">
                        <div class="alc-mb-15">
                            <input type="text" class="c-name alc-form-field" placeholder="Full Name" autocomplete="name">
                        </div>
                        <div class="alc-mb-15">
                            <input type="email" class="c-email alc-form-field" placeholder="Email Address" autocomplete="email">
                        </div>
                        <div class="alc-mb-15">
                            <input type="text" class="c-phone alc-form-field" placeholder="Phone Number">
                        </div>
                        <div class="alc-mb-15">
                            <textarea class="c-notes alc-form-field alc-textarea" placeholder="Any special requests or notes?"></textarea>
                        </div>
                    </div>
                    <div class="alc-form-footer alc-mt-20">
                        <button class="alc-link-btn back-to-step-2">← Back</button>
                        <button class="alc-btn-primary proceed-to-pay-btn">Continue</button>
                    </div>
                </div>
            </div>

            <div class="alchemy-step alchemy-step-4" style="display:none;">
                <h2 class="alc-view-heading">Step 4: Secure Payment</h2>
                <div class="alc-form-container">
                    <div class="alc-payment-box">
                        <div class="payment-element"></div>
                        <div class="payment-message" style="display:none;"></div>
                    </div>
                    <div class="alc-form-footer alc-mt-20">
                        <button class="alc-link-btn back-to-step-3">← Back</button>
                        <button class="alc-btn-primary final-pay-btn">Finalize</button>
                    </div>
                </div>
            </div>
        </div>

        <aside class="alc-summary-sidebar booking-summary" style="display:none;">
            <div class="alc-summary-card">
                <h3>Summary</h3>
                <div class="sum-section"><label>Service</label><p><strong class="sum-service">-</strong></p></div>
                <div class="sum-section"><label>Duration</label><p><strong class="sum-duration">-</strong></p></div>
                <div class="sum-section"><label>Appointment</label><p><strong class="sum-time">-</strong></p><p class="sum-date">-</p></div>
                <div class="alc-summary-total">
                    <span>Total</span><strong class="sum-price">$0</strong>
                </div>
                <button class="alc-link-btn alc-cancel-btn cancel-reset-btn">Cancel Booking</button>
            </div>
        </aside>
    </div>
</div>

<div class="alc-modal alc-details-modal">
    <div class="alc-modal-overlay close-details-modal"></div>
    <div class="alc-modal-content">
        <span class="alc-modal-close close-details-modal">&times;</span>
        <h3 class="modal-title"></h3>
        <p class="modal-desc"></p>
    </div>
</div>

<div class="alc-modal alc-alert-modal">
    <div class="alc-modal-overlay"></div> <!-- Removed close class to force button click -->
    <div class="alc-modal-content" style="text-align:center;">
        <!-- Removed close-alert-modal span (X button) -->
        <div style="font-size:40px; color:#d33; margin-bottom:15px;">✕</div>
        <h3 class="modal-alert-title" style="margin-bottom:10px;">Attention</h3>
        <p class="modal-alert-msg" style="font-size:18px; color:#555;"></p>
        <button type="button" class="alc-btn-primary close-alert-modal" style="margin-top:20px; padding:10px 30px !important; font-size:16px !important;">OK</button>
    </div>
</div>