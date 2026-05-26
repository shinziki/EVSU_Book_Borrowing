<?php
/**
 * One-time migration: create staff_permissions table.
 * Visit once in the browser, then restrict or delete this file.
 */
require_once 'config/db_connect.php';
require_once 'config/functions.php';

ensureStaffPermissionsTable();

echo '<h1>Migration complete</h1>';
echo '<p>The <code>staff_permissions</code> table is ready. Admins can configure permissions per staff in Settings → Staff Accounts.</p>';
echo '<p><a href="settings.php?tab=staff">Go to Staff Accounts</a></p>';
