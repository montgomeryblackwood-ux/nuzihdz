<?php
// Get the IP to block from the request
$ip = isset($_POST['ip']) ? $_POST['ip'] : '';

// Validate input
if (empty($ip)) {
    echo json_encode(['success' => false, 'message' => 'No IP address provided']);
    exit;
}

// Load the current blocked list
$blockedFile = 'data/blocked.json';
if (!file_exists($blockedFile)) {
    file_put_contents($blockedFile, json_encode(['blocked_ips' => [], 'blocked_isps' => []]));
}

$blockedData = json_decode(file_get_contents($blockedFile), true);

// Add the IP to the blocked list if it's not already there
if (!in_array($ip, $blockedData['blocked_ips'])) {
    $blockedData['blocked_ips'][] = $ip;
    file_put_contents($blockedFile, json_encode($blockedData, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true, 'message' => 'IP ' . htmlspecialchars($ip) . ' has been blocked successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'IP ' . htmlspecialchars($ip) . ' is already blocked']);
}
?>

