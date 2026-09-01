# cPanel Upload Guide

This file lists the changes that should be uploaded to the host in a safe order when you are uploading files one by one through cPanel.

## Recommended upload order

### 1) New and updated models
Upload these first, because controllers depend on them:

1. `application/models/Model_return_requests.php`  
   New file.
2. `application/models/Excel_import_model.php`
3. `application/models/Model_admin.php`
4. `application/models/Model_custody.php`
5. `application/models/Model_notifications.php`
6. `application/models/Model_permissions.php`
7. `application/models/Model_store_items.php`
8. `application/models/Model_store_items_dashboard.php`

### 2) Updated controllers
Upload these after the models:

1. `application/controllers/admin/Other.php`
2. `application/controllers/Excel_import.php`
3. `application/controllers/Notifications.php`
4. `application/controllers/User.php`
5. `application/controllers/admin/Products.php`

### 3) New and updated views
Upload the new view files and then the modified views:

1. `application/views/view_admin_profile.php`  
   New file.
2. `application/views/view_user_custody.php`  
   New file.
3. `application/views/view_admin_employee.php`
4. `application/views/view_admin_home.php`
5. `application/views/view_admin_lock_track.php`
6. `application/views/view_admin_operations.php`
7. `application/views/view_admin_sidebar.php`
8. `application/views/view_header.php`
9. `application/views/view_user_lock.php`
10. `application/views/view_user_products.php`
11. `application/views/view_user_profile.php`
12. `application/views/view_user_sidebar.php`

### 4) Front-end assets
Upload these last:

1. `public/css/admin-ui-pattern.css`
2. `public/js/home.js`
3. `public/js/lock_track.js`
4. `public/js/password-field.js`
5. `public/js/script.js`

## Files that were deleted locally

- `vendor.zip`

Do not upload this file. It is not needed for deployment.

## Important notes

- There are no changes in `composer.json`.
- There are no changes in `application/libraries/` in this change set.
- The app still relies on Composer packages, especially `phpoffice/phpspreadsheet`.
- If the host already has a working `vendor/` directory, you do not need to upload it again.
- If the host does not already have `vendor/`, upload the existing `vendor/` directory once or run `composer install` on the server.
- If you upload files one by one, the safest approach is: models first, controllers second, views third, assets last.

## Short deployment checklist

1. Upload all files in the order above.
2. Confirm `vendor/` exists on the host if Composer dependencies are not already installed.
3. Clear any application cache if the host uses one.
4. Test the affected pages:
   - admin home
   - return requests
   - employee profile
   - lock track
   - employee sales pages

## Summary

This deployment is mainly PHP application code, views, and front-end assets. No library source files were changed locally, so the only dependency concern is the existing Composer `vendor/` directory, not a new package change.
