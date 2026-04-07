<?php

return [
    // ═══════════════════════════════════════════════════════
    // SUCCESS MESSAGES
    // ═══════════════════════════════════════════════════════
    'success.reply_added' => 'Reply added.',
    'success.ticket_opened' => 'Ticket opened.',
    'success.contact_removed' => 'Contact removed.',
    'success.auto_renewal_toggled' => 'Auto-renewal :status for :domain.',
    'success.cancellation_request_submitted' => 'Your cancellation request has been submitted.',
    'success.upgrade_request_submitted' => 'Your upgrade request has been submitted and is pending review.',
    'success.domain_locked' => 'Domain locked successfully.',
    'success.domain_unlocked' => 'Domain unlocked successfully.',
    'success.auto_renew_toggled' => 'Auto-renew :state.',
    'success.withdrawal_request_submitted' => 'Withdrawal request of $:amount submitted successfully.',
    'success.product_added_to_cart' => 'Product added to cart successfully.',
    'success.domain_added_to_cart' => ':type for :domain added to cart.',
    'success.item_removed_from_cart' => 'Item removed from cart.',
    'success.order_placed' => 'Order #:num placed successfully. Please complete payment.',
    'success.thank_you' => 'Thank you.',
    'success.settings_updated' => 'Settings updated successfully.',
    'success.profile_updated' => 'Profile updated.',
    'success.password_changed' => 'Password changed.',
    'success.contact_created' => 'Contact created.',
    'success.2fa_enabled_successfully' => 'Two-factor authentication has been enabled.',
    'success.2fa_has_been_disabled' => 'Two-factor authentication has been disabled.',
    'success.if_an_account_exists_with_that_email_a_password_re' => 'If an account exists with that email, a password reset link has been sent.',
    'success.password_reset_successfully_please_log_in' => 'Password reset successfully. Please log in.',
    'success.your_affiliate_account_has_been_activated' => 'Your affiliate account has been activated!',
    'success.your_message_has_been_sent_we_will_get_back_to_you' => 'Your message has been sent. We will get back to you soon!',
    'success.invoice_created_please_complete_payment_to_add_fun' => 'Invoice created. Please complete payment to add funds.',
    'success.appearance_updated_successfully' => 'Appearance updated successfully.',
    'success.nameservers_updated' => 'Nameservers updated.',
    'success.logo_uploaded' => 'Logo uploaded.',
    'success.favicon_uploaded' => 'Favicon uploaded.',
    'success.logo_removed' => 'Logo removed.',
    'success.theme_deleted' => 'Theme deleted.',
    'success.whitelabel_saved' => 'White-label settings saved.',
    'success.darkmode_saved' => 'Dark mode setting saved.',

    // ═══════════════════════════════════════════════════════
    // ERROR MESSAGES
    // ═══════════════════════════════════════════════════════
    'error.message_flagged_as_spam' => 'Your message was flagged as spam. Please contact support if this is an error.',
    'error.current_password_incorrect' => 'The current password is incorrect.',
    'error.domain_not_yours' => 'This domain does not belong to your account.',
    'error.gateway_not_configured' => ':gateway is not configured yet. Please use Bank Transfer or contact support to set up :gateway.',
    'error.cart_is_empty' => 'Your cart is empty.',
    'error.login_required' => 'Please log in to complete your order.',
    'error.no_client_account_found' => 'No client account found.',
    'error.no_client_account_found_please_contact_support' => 'No client account found. Please contact support.',
    'error.no_affiliate_account_found' => 'No affiliate account found.',
    'error.you_have_no_balance_to_withdraw' => 'You have no balance to withdraw.',
    'error.client_profile_not_found' => 'Client profile not found.',
    'error.paid_invoices_cannot_be_cancelled' => 'Paid invoices cannot be cancelled.',
    'error.theme_not_found_or_invalid' => 'Theme not found or invalid.',
    'error.theme_not_found' => 'Theme not found.',
    'error.could_not_create_zip_archive' => 'Could not create ZIP archive.',
    'error.ssl_module_not_found' => 'SSL module not found for this order.',
    'error.ssl_module_not_found_short' => 'SSL module not found.',
    'error.certificate_not_yet_issued' => 'Certificate not yet issued.',
    'error.no_server_module_configured' => 'No server module configured for this product.',
    'error.no_recipient_address_configured' => 'No recipient address configured. Set System Email Address first.',
    'error.addon_not_found' => 'Addon not found.',
    'error.addon_not_active' => 'Addon is not active. Please activate it first.',

    // ═══════════════════════════════════════════════════════
    // INFO MESSAGES
    // ═══════════════════════════════════════════════════════
    'info.already_an_affiliate' => 'You are already an affiliate.',
    'info.contact_support_for_epp' => 'Contact support to retrieve your EPP code.',

    // ═══════════════════════════════════════════════════════
    // INVOICE / CART
    // ═══════════════════════════════════════════════════════
    'invoice.add_funds_description' => 'Add Funds to Account',
    'payment_method.bank_transfer' => 'Bank Transfer',
    'payment_method.paypal' => 'PayPal',
    'payment_method.credit_debit_card' => 'Credit / Debit Card',

    // ═══════════════════════════════════════════════════════
    // WHOIS
    // ═══════════════════════════════════════════════════════
    'whois.invalid_domain' => 'Invalid domain name.',
    'whois.no_server_known' => 'No WHOIS server known for .:tld. Try querying whois.iana.org manually.',
    'whois.connect_error' => 'Error: Could not connect to :server (errno=:errno: :errstr)',

    // ═══════════════════════════════════════════════════════
    // THEME MANAGER
    // ═══════════════════════════════════════════════════════
    'theme.invalid_zip' => 'Invalid ZIP file.',
    'theme.no_theme_json' => 'No theme.json found in ZIP.',
    'theme.invalid_theme_json' => 'Invalid theme.json: missing slug.',
    'theme.invalid_slug' => 'Invalid theme slug.',
    'theme.cannot_overwrite_builtin' => 'Cannot overwrite built-in themes.',
    'theme.cannot_delete_builtin' => 'Cannot delete built-in themes.',
    'theme.cannot_delete_active' => 'Cannot delete the active theme. Activate another theme first.',
    'theme.not_found' => 'Theme not found.',

    // ═══════════════════════════════════════════════════════
    // EMAIL TEST
    // ═══════════════════════════════════════════════════════
    'email.test_sent' => 'Test email sent successfully to :address.',
    'email.test_body' => 'This is a test email sent from PNLCS to verify your mail configuration is working correctly.',
    'email.test_subject' => 'PNLCS Test Email',

    // ═══════════════════════════════════════════════════════
    // VALIDATION
    // ═══════════════════════════════════════════════════════
    'validation.at_least_one_line_item' => 'At least one line item is required.',
    'validation.line_item_description_required' => 'Each line item must have a description.',
    'validation.line_item_amount_required' => 'Each line item must have an amount.',
];
