<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set cache-busting headers to prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
header("Content-Type: application/json");

// Function to check and fix file permissions if needed
function ensureWritablePermissions($file) {
  if (file_exists($file) && !is_writable($file)) {
      // Try to change permissions to make file writable
      if (!chmod($file, 0666)) {
          return false;
      }
  }
  return true;
}

// Function to check and fix directory permissions if needed
function ensureWritableDirectoryPermissions($dir) {
  if (file_exists($dir) && !is_writable($dir)) {
      // Try to change permissions to make directory writable
      if (!chmod($dir, 0777)) {
          return false;
      }
  }
  return true;
}

// Fix the path issue by adding a check for the current directory
$baseDir = '';
if (!file_exists('data') && file_exists('../data')) {
  $baseDir = '../';
}

// Update the file paths to use the base directory
$logFile = $baseDir . 'data/visitor_log.txt';
$visitorsJsonFile = $baseDir . 'data/visitors.json';
$blockedJsonFile = $baseDir . 'data/blocked.json';
$dashDir = $baseDir . 'dash';

// Ensure data directory is writable
if (!file_exists($baseDir . 'data')) {
  if (!mkdir($baseDir . 'data', 0777, true)) {
      echo json_encode(['success' => false, 'message' => 'Failed to create data directory. Check permissions.']);
      exit;
  }
} else if (!ensureWritableDirectoryPermissions($baseDir . 'data')) {
  echo json_encode(['success' => false, 'message' => 'Data directory is not writable. Please check permissions.']);
  exit;
}

// Clear the visitor log file
if (file_exists($logFile)) {
  ensureWritablePermissions($logFile);
  if (file_put_contents($logFile, '') === false) {
      echo json_encode(['success' => false, 'message' => 'Failed to clear visitor_log.txt. Check file permissions.']);
      exit;
  }
}

// Clear visitors.json if it exists
if (file_exists($visitorsJsonFile)) {
  ensureWritablePermissions($visitorsJsonFile);
  if (file_put_contents($visitorsJsonFile, '[]') === false) {
      echo json_encode(['success' => false, 'message' => 'Failed to clear visitors.json. Check file permissions.']);
      exit;
  }
}

// Reset blocked.json to empty lists but keep the structure
if (file_exists($blockedJsonFile)) {
  ensureWritablePermissions($blockedJsonFile);
  $empty_blocked = [
      'blocked_ips' => [],
      'blocked_isps' => []
  ];
  
  if (file_put_contents($blockedJsonFile, json_encode($empty_blocked, JSON_PRETTY_PRINT)) === false) {
      echo json_encode(['success' => false, 'message' => 'Failed to reset blocked.json']);
      exit;
  }
}

// Return success message with timestamp to prevent caching
echo json_encode([
  'success' => true, 
  'message' => 'All visitor logs have been cleared successfully.',
  'timestamp' => time()
]);
?>

