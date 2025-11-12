<?php
// Get the ISP to block from the request
$isp = isset($_POST['isp']) ? $_POST['isp'] : '';

// Validate input
if (empty($isp)) {
    echo json_encode(['success' => false, 'message' => 'No ISP provided']);
    exit;
}

// Load the current blocked list
$blockedFile = 'data/blocked.json';
if (!file_exists($blockedFile)) {
    file_put_contents($blockedFile, json_encode(['blocked_ips' => [], 'blocked_isps' => []]));
}

$blockedData = json_decode(file_get_contents($blockedFile), true);

// Add the ISP to the blocked list if it's not already there
if (!in_array($isp, $blockedData['blocked_isps'])) {
    $blockedData['blocked_isps'][] = $isp;
    file_put_contents($blockedFile, json_encode($blockedData, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true, 'message' => 'ISP ' . htmlspecialchars($isp) . ' has been blocked successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'ISP ' . htmlspecialchars($isp) . ' is already blocked']);
}
?>

