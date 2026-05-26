<?php
/**
 * One-time migration: add role column to admins table.
 * Visit this file once in the browser, then delete or restrict access.
 */
require_once 'config/db_connect.php';
require_once 'config/functions.php';

ensureAdminRoleColumn();

echo '<h1>Migration complete</h1>';
echo '<p>The <code>role</code> column is ready on the <code>admins</code> table. Existing accounts default to <strong>admin</strong>.</p>';
echo '<p><a href="login.php">Go to login</a></p>';
