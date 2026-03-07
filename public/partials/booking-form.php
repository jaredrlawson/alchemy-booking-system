<div class="alchemy-booking-wizard">
    <div class="alc-flex-layout">
        <div class="alc-main-panel">
            
            <div id="alchemy-step-1" class="alchemy-step">
                <h2 class="alc-view-heading">Step 1: Choose Your Service</h2>
                <div id="alchemy-services-grid"></div>
            </div>

            <div id="alchemy-step-2" class="alchemy-step" style="display:none;">
                <h2 class="alc-view-heading">Step 2: Select Date & Time</h2>
                
                <div class="alc-step-container">
                    <div class="alc-scheduler-header">
                        <div class="alc-month-row">
                            <h2 id="display-month">...</h2>
                            <div class="alc-datepicker-trigger">
                                <span class="alc-calendar-label alc-pointer" onclick="triggerCalendarPicker()">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                </span>
                                <input type="date" id="alc-manual-date" onchange="handleManualDate(this.value)" class="alc-hidden-picker">
                            </div>
                        </div>
                        
                        <div class="alc-today-box">
                            <button class="alc-pill-btn" onclick="jumpToToday()">TODAY</button>
                        </div>
                    </div>

                    <div class="alc-scroller-box">
                        <button class="scroller-nav-btn" onclick="moveScroller(-1)">‹</button>
                        <div class="date-track" id="date-track"></div>
                        <button class="scroller-nav-btn" onclick="moveScroller(1)">›</button>
                    </div>

                    <div id="time-slots-container"></div>
                    
                    <div class="alc-mt-30">
                        <button class="alc-link-btn" onclick="goToStep(1)">← Back to Services</button>
                    </div>
                </div>
            </div>

            <div id="alchemy-step-3" class="alchemy-step" style="display:none;">
                <h2 class="alc-view-heading">Step 3: Your Details</h2>
                <div class="alc-form-container">
                    <div class="alc-payment-box">
                        <div class="alc-mb-15">
                            <input type="text" id="c-name" placeholder="Full Name" autocomplete="name" class="alc-form-field">
                        </div>
                        <div class="alc-mb-15">
                            <input type="email" id="c-email" placeholder="Email Address" autocomplete="email" class="alc-form-field">
                        </div>
                        <div class="alc-mb-15">
                            <input type="text" id="c-phone" placeholder="Phone Number" class="alc-form-field">
                        </div>
                        <div class="alc-mb-15">
                            <textarea id="c-notes" placeholder="Any special requests or notes?" class="alc-form-field alc-textarea"></textarea>
                        </div>
                    </div>
                    <div class="alc-form-footer alc-mt-20">
                        <button class="alc-link-btn" onclick="goToStep(2)">← Back</button>
                        <button onclick="proceedToPayment()" class="alc-btn-primary">Continue</button>
                    </div>
                </div>
            </div>

            <div id="alchemy-step-4" class="alchemy-step" style="display:none;">
                <h2 class="alc-view-heading">Step 4: Secure Payment</h2>
                <div class="alc-form-container">
                    <div class="alc-payment-box">
                        <div id="payment-element"></div>
                        <div id="payment-message" class="alc-error-msg" style="display:none;"></div>
                    </div>
                    <div class="alc-form-footer alc-mt-20">
                        <button class="alc-link-btn" onclick="goToStep(3)">← Back</button>
                        <button id="final-pay-btn" onclick="handleFinalBook()" class="alc-btn-primary">Finalize</button>
                    </div>
                </div>
            </div>
        </div>

        <aside class="alc-summary-sidebar" id="booking-summary" style="display:none;">
            <div class="alc-summary-card">
                <h3>Summary</h3>
                <div class="sum-section"><label>Service</label><p><strong id="sum-service">-</strong></p></div>
                <div class="sum-section"><label>Duration</label><p><strong id="sum-duration">-</strong></p></div>
                <div class="sum-section"><label>Appointment</label><p><strong id="sum-time">-</strong></p><p id="sum-date">-</p></div>
                <div class="alc-summary-total">
                    <span>Total</span><strong id="sum-price">$0</strong>
                </div>
                <button class="alc-link-btn alc-cancel-btn" onclick="cancelAndReset()">Cancel Booking</button>
            </div>
        </aside>
    </div>
</div>

<div id="alc-details-modal" class="alc-modal">
    <div class="alc-modal-content">
        <span class="alc-modal-close" onclick="closeDetailsModal()">&times;</span>
        <h3 id="modal-title"></h3>
        <p id="modal-desc"></p>
    </div>
</div>