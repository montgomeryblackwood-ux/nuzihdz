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

// Get the block type and value from the request
$blockType = isset($_POST['block_type']) ? $_POST['block_type'] : '';
$blockValue = isset($_POST['block_value']) ? $_POST['block_value'] : '';

// Validate input
if (empty($blockType) || empty($blockValue) || !in_array($blockType, ['ip', 'isp'])) {
  echo json_encode(['success' => false, 'message' => 'Invalid block parameters']);
  exit;
}

// Load the current blocked list
$blockedFile = $baseDir . 'data/blocked.json';
if (!file_exists($blockedFile)) {
  file_put_contents($blockedFile, json_encode(['blocked_ips' => [], 'blocked_isps' => []]));
}

$blockedData = json_decode(file_get_contents($blockedFile), true);

// Add the new value to the appropriate list if it's not already there
if ($blockType === 'ip') {
  if (!in_array($blockValue, $blockedData['blocked_ips'])) {
      $blockedData['blocked_ips'][] = $blockValue;
  } else {
      echo json_encode(['success' => false, 'message' => 'IP is already blocked']);
      exit;
  }
} else { // isp
  if (!in_array($blockValue, $blockedData['blocked_isps'])) {
      $blockedData['blocked_isps'][] = $blockValue;
  } else {
      echo json_encode(['success' => false, 'message' => 'ISP is already blocked']);
      exit;
  }
}

// Save the updated blocked list
if (file_put_contents($blockedFile, json_encode($blockedData, JSON_PRETTY_PRINT))) {
    // Return success response
    echo json_encode([
      'success' => true, 
      'message' => ($blockType === 'ip' ? 'IP' : 'ISP') . ' ' . htmlspecialchars($blockValue) . ' has been blocked successfully',
      'timestamp' => time()
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update blocked list']);
}
?>

