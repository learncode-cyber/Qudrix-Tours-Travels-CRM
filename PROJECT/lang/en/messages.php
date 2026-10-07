<?php

/**
 * MASTER_PROJECT_AUDIT.md P1 (i18n): starter set of app-facing strings used
 * in API responses/notifications. This is a foundation, not a complete
 * translation of every string in the codebase — most controller responses
 * still return hardcoded English JSON messages (e.g. AgentController,
 * VendorController from the P0 batch). Wiring every response message
 * through __('messages.key') is real remaining work, tracked in STATUS.md.
 */
return [
    'booking_confirmed' => 'Your booking has been confirmed.',
    'payment_received' => 'Payment received. Thank you.',
    'installment_due_soon' => 'An installment payment is due soon.',
    'installment_overdue' => 'An installment payment is overdue.',
    'visa_status_updated' => 'Your visa application status has been updated.',
    'documents_required' => 'Additional documents are required for your application.',
    'mahram_verification_pending' => 'Mahram relationship verification is pending for this traveler.',
    'room_assignment_updated' => 'Room assignment has been updated.',
];
