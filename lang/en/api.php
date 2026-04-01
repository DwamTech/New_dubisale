<?php

return [
    // Auth
    'user_inactive'             => 'Account is inactive. Please contact support.',
    'invalid_credentials'       => 'Invalid credentials.',
    'invalid_referral_code'     => 'Invalid referral code.',
    'phone_required'            => 'Phone number is required for registration. Email can only be used for login.',
    'invalid_delegate_code'     => 'Invalid delegate code. Please check the code and try again.',
    'login_success'             => 'Logged in successfully.',
    'register_success'          => 'Account created successfully.',
    'admin_login_success'       => 'Admin logged in successfully.',
    'account_inactive'          => 'Account is inactive.',
    'logout_success'            => 'Logged out successfully.',

    // User / Profile
    'unauthenticated'           => 'Unauthenticated.',
    'guest_not_found'           => 'Guest user not found. Please provide a valid guest_uuid.',
    'profile_fetched'           => 'Profile fetched successfully.',
    'profile_updated'           => 'Profile updated successfully.',
    'delegate_code_locked'      => 'You cannot change your delegate code once it has been set.',
    'already_representative'    => 'You are already a representative.',
    'now_representative'        => 'You are now a representative. Your code is: :code',
    'not_representative'        => 'Only representatives can access this feature.',
    'no_clients'                => 'No clients found.',
    'clients_fetched'           => 'Clients retrieved successfully.',
    'otp_created'               => 'OTP created successfully.',
    'invalid_otp'               => 'Invalid OTP.',
    'otp_verified'              => 'OTP verified successfully.',
    'user_created'              => 'User created successfully.',
    'user_updated'              => 'User updated successfully.',
    'user_deleted'              => 'User deleted successfully.',
    'user_blocked'              => 'User blocked successfully.',
    'user_unblocked'            => 'User unblocked successfully.',

    // Ads / Listings
    'ad_not_found'              => 'Ad not found.',
    'ad_already_paid'           => 'Ad already paid.',
    'no_package'                => 'No active package found. Please subscribe to a new package.',
    'rank_not_owner'            => 'You cannot modify the rank of an ad you do not own.',
    'rank_not_found'            => 'Ad not found in this category.',
    'rank_cooldown'             => 'You can boost an ad once every 24 hours. Remaining: ~:hours hour(s).',
    'rank_updated'              => 'Ad #:id has been moved to rank 1 in :section.',
    'rank_error'                => 'An error occurred while updating the rank.',

    // Subscriptions
    'subscription_exists'       => 'You already have an active paid subscription for this plan.',
    'use_package_instead'       => 'You have an active package with available balance. Use your package instead.',
    'no_prices_set'             => 'Prices for this category have not been set yet. Please contact support.',
    'subscription_created'      => 'Subscription created successfully.',

    // Favorites
    'favorite_added'            => 'Ad added to favorites.',
    'favorite_removed'          => 'Ad removed from favorites.',

    // Chat
    'cannot_message_self'       => 'You cannot send a message to yourself.',
    'message_sent'              => 'Message sent successfully.',
    'file_upload_failed'        => 'File upload failed. Please try again.',
    'message_send_error'        => 'An error occurred while sending the message.',
    'support_message_sent'      => 'Your message has been sent to support successfully.',
    'invalid_conversation'      => 'Invalid.',
    'listing_not_found'         => 'Listing not found.',

    // Notifications
    'forbidden'                 => 'Forbidden.',
    'notification_read'         => 'Notification marked as read.',

    // Cars
    'car_added'                 => 'Car added successfully.',
    'car_updated'               => 'Car updated successfully.',
    'car_deleted'               => 'Car deleted successfully.',
    'car_unauthorized'          => 'You are not authorized to modify this car.',

    // OTP Flow (login-or-register)
    'otp_required'              => 'OTP sent. Please enter the code to complete registration.',
    'otp_cooldown'              => 'Please wait :seconds seconds before resending.',
    'otp_resent'                => 'OTP resent successfully.',
    'otp_record_not_found'      => 'No OTP request found. Please start over.',
    'otp_expired'               => 'OTP has expired. Please request a new one.',
    'otp_max_attempts'          => 'Maximum attempts exceeded. Please request a new OTP.',
    'otp_invalid_code'          => 'Invalid OTP code.',
    'otp_send_failed'           => 'Failed to send OTP. Please try again later.',
    'reset_user_not_found'      => 'No account found for this phone number.',
    'reset_otp_required'        => 'OTP sent for password reset.',
    'reset_success'             => 'Password reset successfully.',

    // OTP
    'otp_sent_whatsapp'         => 'OTP sent via WhatsApp.',
    'otp_sent_sms'              => 'OTP sent via SMS.',
    'otp_verified_success'      => 'OTP verified successfully.',
    'otp_invalid'               => 'Invalid OTP.',
    'otp_failed'                => 'Failed to send OTP.',
    'otp_verify_failed'         => 'OTP verification failed.',

    // Listings
    'listing_unauthorized_edit' => 'You are not authorized to edit this listing.',
    'listing_unauthorized_del'  => 'You are not authorized to delete this listing.',
    'search_min_chars'          => 'Search keyword must be at least 2 characters.',
    'free_limit_exceeded'       => 'You have exceeded the free ads limit and the price exceeds the allowed maximum. Please subscribe to a paid plan.',
    'free_count_exceeded'       => 'You have exceeded the maximum number of free ads. Please subscribe to a paid plan.',
    'free_price_exceeded'       => 'This ad price exceeds the free ad limit. Lower the price or subscribe to a paid plan.',
    'renew_success_package'     => 'Ad renewed successfully via package.',
    'renew_success_free'        => 'Free ad renewed successfully.',
    'renew_no_balance'          => 'Insufficient balance to renew the ad. Please subscribe or purchase a package.',
    'payment_done'              => 'Payment completed successfully.',

    // Reports
    'report_submitted'          => 'Report submitted successfully.',
    'report_accepted'           => 'Report accepted and listing has been rejected.',
    'report_dismissed'          => 'Report dismissed, listing remains active.',
    'report_read'               => 'All reports for this listing marked as read.',
    'report_deleted'            => 'Report deleted successfully.',

    // FCM
    'fcm_updated'               => 'FCM token updated successfully.',
    'fcm_deleted'               => 'FCM token deleted successfully.',
    'guest_fcm_updated'         => 'Guest user found. FCM token updated successfully.',
    'guest_fcm_created'         => 'New guest user created successfully.',

    // Listings (ListingController)
    'listing_unauthorized_edit2' => 'You are not authorized to edit this listing.',
    'listing_unauthorized_del2'  => 'You are not authorized to delete this listing.',
    'listing_search_min'         => 'Search keyword must be at least 2 characters.',
    'listing_free_both'          => 'You have exceeded the free ads limit and the price exceeds the allowed maximum. Please subscribe to a paid plan or pay for a single ad and change the plan type.',
    'listing_free_count'         => 'You have exceeded the maximum number of free ads in this category. Please subscribe to a paid plan or pay for a single ad and change the plan type.',
    'listing_free_price'         => 'This ad price exceeds the free ad limit. Lower the price or subscribe to a paid plan and change the plan type.',
    'listing_no_package'         => 'You do not have an active package or sufficient balance. Payment is required to publish this ad.',
    'listing_renew_package'      => 'Ad renewed successfully via package.',
    'listing_renew_free'         => 'Free ad renewed successfully.',
    'listing_renew_no_balance'   => 'Insufficient balance in subscription or package to renew the ad. Please subscribe or purchase a package.',
    'listing_renew_free_limit'   => 'Cannot renew the ad for free.',
    'listing_renew_over_count'   => 'You have exceeded the maximum number of free ads.',
    'listing_renew_over_price'   => 'The ad price exceeds the free ad limit.',
    'listing_view_notify'        => 'Your ad has been viewed',
    'listing_view_notify_body'   => 'User #:viewer viewed your ad #:listing in :section',
    'listing_pending_admin'      => 'New listing pending review',
    'listing_pending_admin_body' => 'A new listing #:id in :section needs review and approval.',
    'listing_upload_failed'      => 'File upload failed.',
    'listing_upload_save_failed' => 'File save failed.',

    // Best Advertiser
    'best_advertiser_updated'   => 'Best advertiser updated.',
    'best_advertiser_created'   => 'Best advertiser created.',
    'best_advertiser_disabled'  => 'Best advertiser disabled.',
    'best_advertiser_limit'     => 'Maximum featured users limit reached (:limit).',
    'best_advertiser_inactive'  => 'User must be active.',
    'best_advertiser_bad_cats'  => 'Some categories do not exist.',

    // User Clients
    'user_client_not_found'     => 'Record not found for this user.',
    'user_client_exists'        => 'Record already exists for this user. Use update instead.',
    'user_client_created'       => 'User clients record created successfully.',
    'user_client_updated'       => 'Clients updated successfully.',
    'user_client_deleted'       => 'User clients record deleted successfully.',
    'user_client_added'         => 'Client added successfully.',
    'user_client_removed'       => 'Client removed successfully.',

    // Backups
    'backups_fetched'           => 'Backups retrieved successfully.',
    'backups_fetch_failed'      => 'Failed to retrieve backups.',
    'backup_created'            => 'Backup created successfully.',
    'backup_creation_failed'    => 'Failed to create backup.',
    'backup_invalid_type'       => 'Invalid backup type provided.',
    'backup_restored'           => 'Backup restored successfully.',
    'backup_restore_failed'     => 'Cannot restore this backup.',
    'backup_restore_error'      => 'An error occurred during restoration.',
    'backup_deleted'            => 'Backup deleted successfully.',
    'backup_deletion_failed'    => 'Failed to delete backup.',
    'backup_not_found'          => 'Backup not found.',
    'backup_type_required'      => 'Backup type is required.',
    'backup_type_invalid'       => 'Backup type must be one of: db, files, or full.',
    'backup_restore_confirm_required' => 'Restore confirmation is required.',
    'backup_restore_confirm_must_be_true' => 'You must confirm the restore operation.',

    // Backup Diagnostics
    'backup_diagnostics_completed' => 'Backup diagnostics completed successfully.',
    'backup_diagnostics_failed'    => 'Failed to run backup diagnostics.',
    'backup_statistics_fetched'    => 'Backup statistics retrieved successfully.',
    'backup_statistics_failed'     => 'Failed to retrieve backup statistics.',
    'backup_auto_fix_completed'    => 'Auto-fix completed successfully.',
    'backup_auto_fix_failed'       => 'Failed to run auto-fix.',
    'backup_file_not_found'        => 'Backup file not found on disk.',
    'backup_download_failed'       => 'Failed to download backup',
    'backup_history_fetched'       => 'Backup history retrieved successfully.',
    'backup_history_fetch_failed'  => 'Failed to retrieve backup history.',
    'backup_uploaded'              => 'Backup file uploaded successfully.',
    'backup_upload_failed'         => 'Failed to upload backup file.',
];
