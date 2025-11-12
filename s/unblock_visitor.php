<?php
// Set cache-busting headers to prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
header("Content-Type: application/json");

// Fix the path issue by adding a check for the current directory
$baseDir = '';
if (!file_exists('data/blocked.json') && file_exists('../data/blocked.json')) {
    $baseDir = '../';
}

// Get the unblock type and value from the request
$unblockType = isset($_POST['unblock_type']) ? $_POST['unblock_type'] : '';
$unblockValue = isset($_POST['unblock_value']) ? $_POST['unblock_value'] : '';

// Validate input
if (empty($unblockType) || empty($unblockValue) || !in_array($unblockType, ['ip', 'isp'])) {
  echo json_encode(['success' => false, 'message' => 'Invalid unblock parameters']);
  exit;
}

// Load the current blocked list
$blockedFile = $baseDir . 'data/blocked.json';
if (!file_exists($blockedFile)) {
  echo json_encode(['success' => false, 'message' => 'Blocked list file not found']);
  exit;
}

$blockedData = json_decode(file_get_contents($blockedFile), true);

// Remove the value from the appropriate list
if ($unblockType === 'ip') {
  $key = array_search($unblockValue, $blockedData['blocked_ips']);
  if ($key !== false) {
      array_splice($blockedData['blocked_ips'], $key, 1);
  } else {
      echo json_encode(['success' => false, 'message' => 'IP address not found in blocked list']);
      exit;
  }
} else { // isp
  $key = array_search($unblockValue, $blockedData['blocked_isps']);
  if ($key !== false) {
      array_splice($blockedData['blocked_isps'], $key, 1);
  } else {
      echo json_encode(['success' => false, 'message' => 'ISP not found in blocked list']);
      exit;
  }
}

// Save the updated blocked list
if (file_put_contents($blockedFile, json_encode($blockedData, JSON_PRETTY_PRINT))) {
  echo json_encode([
      'success' => true, 
      'message' => ($unblockType === 'ip' ? 'IP' : 'ISP') . ' ' . htmlspecialchars($unblockValue) . ' has been unblocked successfully',
      'timestamp' => time()
  ]);
} else {
  echo json_encode(['success' => false, 'message' => 'Failed to update blocked list']);
}
?>

