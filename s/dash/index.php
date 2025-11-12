<?php
// Start session if needed
session_start();

// Set base directory for file paths
$baseDir = '../';

// Load blocked IPs and ISPs
$blocked_data = [];
if (file_exists($baseDir . 'data/blocked.json')) {
    $blocked_data = json_decode(file_get_contents($baseDir . 'data/blocked.json'), true);
} else {
    $blocked_data = ['blocked_ips' => [], 'blocked_isps' => []];
    file_put_contents($baseDir . 'data/blocked.json', json_encode($blocked_data, JSON_PRETTY_PRINT));
}

// Load visitor logs
$log_file = $baseDir . 'data/visitor_log.txt';
$logs = [];
$unique_ip_logs = []; // Array to store only the most recent entry for each IP

if (file_exists($log_file)) {
    $log_content = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($log_content as $line) {
        $decoded = json_decode($line, true);
        if ($decoded) {
            $logs[] = $decoded;
            
            // Store only the most recent entry for each IP
            $unique_ip_logs[$decoded['ip']] = $decoded;
        }
    }
}

// Convert the associative array back to a simple array
$unique_ip_logs = array_values($unique_ip_logs);

// Sort by timestamp (most recent first)
usort($unique_ip_logs, function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

// Count stats
$total_visitors = count($logs);
$unique_visitors = count($unique_ip_logs);
$bots = 0;
$humans = 0;
$mobiles = 0;
$proxies = 0;
$hostings = 0;
$unique_countries = [];
$ios_count = 0;
$android_count = 0;

foreach ($unique_ip_logs as $log) {
    if (isset($log['is_bot']) && $log['is_bot']) {
        $bots++;
    } else {
        $humans++;
    }
    
    if (isset($log['is_mobile']) && $log['is_mobile']) {
        $mobiles++;
    }
    
    if (isset($log['is_proxy']) && $log['is_proxy']) {
        $proxies++;
    }
    
    if (isset($log['is_hosting']) && $log['is_hosting']) {
        $hostings++;
    }
    
    if (!empty($log['country']) && !in_array($log['country'], $unique_countries)) {
        $unique_countries[] = $log['country'];
    }
    
    // Count iOS and Android devices
    $os = strtolower($log['os'] ?? '');
    if (strpos($os, 'iphone') !== false || strpos($os, 'ipad') !== false || strpos($os, 'ipod') !== false || strpos($os, 'ios') !== false) {
        $ios_count++;
    } elseif (strpos($os, 'android') !== false) {
        $android_count++;
    }
}

// Get last visit time
$last_visit = !empty($logs) ? end($logs)['timestamp'] : 'N/A';

// Function to get OS icon
function getOSIcon($os) {
    $os = strtolower($os);
    
    // Mobile devices - iOS
    if (strpos($os, 'iphone') !== false) {
        return '<i class="fab fa-apple os-icon" style="color: #000;"></i>';
    } elseif (strpos($os, 'ipad') !== false) {
        return '<i class="fas fa-tablet-alt os-icon" style="color: #000;"></i>';
    } elseif (strpos($os, 'ipod') !== false) {
        return '<i class="fas fa-music os-icon" style="color: #000;"></i>';
    } elseif (strpos($os, 'ios') !== false) {
        return '<i class="fab fa-apple os-icon" style="color: #000;"></i>';
    }
    // Mobile devices - Android
    elseif (strpos($os, 'android') !== false) {
        return '<i class="fab fa-android os-icon" style="color: #A4C639;"></i>';
    } elseif (strpos($os, 'blackberry') !== false) {
        return '<i class="fab fa-blackberry os-icon" style="color: #000;"></i>';
    } elseif (strpos($os, 'mobile') !== false) {
        return '<i class="fas fa-mobile-alt os-icon" style="color: #6B7280;"></i>';
    }
    
    // Desktop OS
    elseif (strpos($os, 'windows 10') !== false || strpos($os, 'windows nt 10') !== false) {
        return '<i class="fab fa-windows os-icon" style="color: #0078D7;"></i>';
    } elseif (strpos($os, 'windows 11') !== false) {
        return '<i class="fab fa-windows os-icon" style="color: #0078D7;"></i>';
    } elseif (strpos($os, 'windows') !== false) {
        return '<i class="fab fa-windows os-icon" style="color: #0078D7;"></i>';
    } elseif (strpos($os, 'mac') !== false) {
        return '<i class="fab fa-apple os-icon" style="color: #999;"></i>';
    } elseif (strpos($os, 'ubuntu') !== false) {
        return '<i class="fab fa-ubuntu os-icon" style="color: #E95420;"></i>';
    } elseif (strpos($os, 'debian') !== false) {
        return '<i class="fab fa-debian os-icon" style="color: #A80030;"></i>';
    } elseif (strpos($os, 'fedora') !== false) {
        return '<i class="fab fa-fedora os-icon" style="color: #294172;"></i>';
    } elseif (strpos($os, 'centos') !== false) {
        return '<i class="fab fa-centos os-icon" style="color: #9CCD2A;"></i>';
    } elseif (strpos($os, 'linux') !== false) {
        return '<i class="fab fa-linux os-icon" style="color: #FCC624;"></i>';
    }
    
    // Default
    else {
        return '<i class="fas fa-desktop os-icon" style="color: #6B7280;"></i>';
    }
}

// Function to determine connection type
function getConnectionType($visitor) {
    if (isset($visitor['is_proxy']) && $visitor['is_proxy']) {
        return ['badge-proxy', 'VPN/PROXY', 'fa-shield-alt'];
    } elseif (isset($visitor['is_hosting']) && $visitor['is_hosting']) {
        return ['badge-hosting', 'HOSTING', 'fa-server'];
    } elseif (isset($visitor['is_mobile']) && $visitor['is_mobile']) {
        // For mobile, we'll randomly assign a connection type since the API doesn't provide this
        $mobile_connections = [
            ['badge-5g', '5G', 'fa-signal'],
            ['badge-4g', '4G', 'fa-signal'],
            ['badge-3g', '3G', 'fa-signal'],
            ['badge-wifi', 'WIFI', 'fa-wifi']
        ];
        
        // Use the IP as a seed for consistent results
        $seed = crc32($visitor['ip']) % count($mobile_connections);
        return $mobile_connections[$seed];
    } else {
        // For desktop, assume either WiFi or satellite
        $desktop_connections = [
            ['badge-wifi', 'WIFI', 'fa-wifi'],
            ['badge-satellite', 'SATELLITE', 'fa-satellite-dish']
        ];
        
        // Use the IP as a seed for consistent results
        $seed = crc32($visitor['ip']) % count($desktop_connections);
        return $desktop_connections[$seed];
    }
}

// Function to check if IP is blocked
function isIPBlocked($ip, $blocked_data) {
    return in_array($ip, $blocked_data['blocked_ips']);
}

// Function to check if ISP is blocked
function isISPBlocked($isp, $blocked_data) {
    return in_array($isp, $blocked_data['blocked_isps']);
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'block_ip':
            $ip = $_POST['ip'] ?? '';
            if (empty($ip)) {
                echo json_encode(['success' => false, 'message' => 'No IP provided']);
                exit;
            }
            
            if (!in_array($ip, $blocked_data['blocked_ips'])) {
                $blocked_data['blocked_ips'][] = $ip;
                file_put_contents($baseDir . 'data/blocked.json', json_encode($blocked_data, JSON_PRETTY_PRINT));
                echo json_encode(['success' => true, 'message' => 'IP ' . htmlspecialchars($ip) . ' has been blocked successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'IP is already blocked']);
            }
            exit;
            
        case 'block_isp':
            $isp = $_POST['isp'] ?? '';
            if (empty($isp)) {
                echo json_encode(['success' => false, 'message' => 'No ISP provided']);
                exit;
            }
            
            if (!in_array($isp, $blocked_data['blocked_isps'])) {
                $blocked_data['blocked_isps'][] = $isp;
                file_put_contents($baseDir . 'data/blocked.json', json_encode($blocked_data, JSON_PRETTY_PRINT));
                echo json_encode(['success' => true, 'message' => 'ISP ' . htmlspecialchars($isp) . ' has been blocked successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'ISP is already blocked']);
            }
            exit;
            
        case 'unblock_ip':
            $ip = $_POST['ip'] ?? '';
            if (empty($ip)) {
                echo json_encode(['success' => false, 'message' => 'No IP provided']);
                exit;
            }
            
            $key = array_search($ip, $blocked_data['blocked_ips']);
            if ($key !== false) {
                array_splice($blocked_data['blocked_ips'], $key, 1);
                file_put_contents($baseDir . 'data/blocked.json', json_encode($blocked_data, JSON_PRETTY_PRINT));
                echo json_encode(['success' => true, 'message' => 'IP ' . htmlspecialchars($ip) . ' has been unblocked successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'IP is not blocked']);
            }
            exit;
            
        case 'unblock_isp':
            $isp = $_POST['isp'] ?? '';
            if (empty($isp)) {
                echo json_encode(['success' => false, 'message' => 'No ISP provided']);
                exit;
            }
            
            $key = array_search($isp, $blocked_data['blocked_isps']);
            if ($key !== false) {
                array_splice($blocked_data['blocked_isps'], $key, 1);
                file_put_contents($baseDir . 'data/blocked.json', json_encode($blocked_data, JSON_PRETTY_PRINT));
                echo json_encode(['success' => true, 'message' => 'ISP ' . htmlspecialchars($isp) . ' has been unblocked successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'ISP is not blocked']);
            }
            exit;
            
        case 'clear_visits':
            // Clear the visitor log file
            if (file_exists($baseDir . 'data/visitor_log.txt')) {
                if (file_put_contents($baseDir . 'data/visitor_log.txt', '') === false) {
                    echo json_encode(['success' => false, 'message' => 'Failed to clear visitor_log.txt']);
                    exit;
                }
            }
            
            // Clear visitors.json if it exists
            if (file_exists($baseDir . 'data/visitors.json')) {
                if (file_put_contents($baseDir . 'data/visitors.json', '[]') === false) {
                    echo json_encode(['success' => false, 'message' => 'Failed to clear visitors.json']);
                    exit;
                }
            }
            
            // Reset blocked.json to empty lists but keep the structure
            $empty_blocked = [
                'blocked_ips' => [],
                'blocked_isps' => []
            ];
            
            if (file_put_contents($baseDir . 'data/blocked.json', json_encode($empty_blocked, JSON_PRETTY_PRINT)) === false) {
                echo json_encode(['success' => false, 'message' => 'Failed to reset blocked.json']);
                exit;
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'All visitor logs have been cleared successfully.',
                'timestamp' => time()
            ]);
            exit;
            
        case 'check_new_visitors':
            $lastId = $_GET['lastId'] ?? '';
            $newVisitors = [];
            
            if (file_exists($baseDir . 'data/visitor_log.txt')) {
                $log_content = file($baseDir . 'data/visitor_log.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($log_content as $line) {
                    $visitor = json_decode($line, true);
                    if ($visitor && (!$lastId || $visitor['id'] > $lastId)) {
                        $newVisitors[] = $visitor;
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'visitors' => $newVisitors,
                'timestamp' => time()
            ]);
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Tracking Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="../favicon.gif" type="image/gif">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --primary-dark: #4338ca;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #1f2937;
            --light: #f9fafb;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --gray-dark: #374151;
            --border-radius: 0.5rem;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --gradient-blue: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            --gradient-green: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --gradient-red: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            --gradient-orange: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --gradient-purple: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            color: var(--dark);
            line-height: 1.5;
            padding: 1rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .title i {
            color: var(--primary);
        }

        .refresh-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray);
            flex-wrap: wrap;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-blue);
        }

        .stat-title {
            font-size: 0.875rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .stat-icon {
            float: right;
            font-size: 1.5rem;
            color: var(--primary-light);
            opacity: 0.8;
        }

        /* Visitor cards grid */
        .visitors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .visitor-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }

        .visitor-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .visitor-card.bot-visitor::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-red);
        }

        .visitor-card.human-visitor::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-green);
        }

        .visitor-card.proxy-visitor::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-orange);
        }

        .visitor-card-header {
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--gray-light);
        }

        .visitor-time {
            font-size: 0.875rem;
            color: var(--gray);
        }

        .visitor-ip {
            font-weight: 600;
            color: var(--dark);
            font-family: monospace;
            font-size: 1rem;
            word-break: break-all;
        }

        .visitor-card-body {
            padding: 1rem;
        }

        .visitor-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .visitor-info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .visitor-info-label {
            font-size: 0.75rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .visitor-info-value {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--dark);
            word-break: break-word;
        }

        .visitor-location {
            grid-column: span 2;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .visitor-location .flag-icon {
            width: 24px;
            height: auto;
            border-radius: 2px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .visitor-location-text {
            font-size: 0.875rem;
            color: var(--dark);
        }

        .visitor-card-footer {
            padding: 0.75rem 1rem;
            background-color: #f9fafb;
            border-top: 1px solid var(--gray-light);
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .badge-bot {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .badge-human {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .badge-mobile {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .badge-proxy {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--info);
        }

        .badge-hosting {
            background-color: rgba(107, 114, 128, 0.1);
            color: var(--gray-dark);
        }

        .badge-5g {
            background-color: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }

        .badge-4g {
            background-color: rgba(79, 70, 229, 0.1);
            color: #4f46e5;
        }

        .badge-3g {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .badge-wifi {
            background-color: rgba(14, 165, 233, 0.1);
            color: #0ea5e9;
        }

        .badge-satellite {
            background-color: rgba(168, 85, 247, 0.1);
            color: #a855f7;
        }

        .os-icon {
            font-size: 1.25rem;
            width: 1.5rem;
            text-align: center;
            vertical-align: middle;
        }

        .no-data {
            padding: 3rem;
            text-align: center;
            color: var(--gray);
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .new-visitor {
            animation: highlight 2s ease-out;
        }

        @keyframes highlight {
            0% { background-color: rgba(79, 70, 229, 0.1); }
            100% { background-color: white; }
        }

        .footer {
            text-align: center;
            margin-top: 2rem;
            padding: 1rem;
            color: var(--gray);
            font-size: 0.875rem;
        }

        .notification-toggle {
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color 0.2s;
        }

        .notification-toggle:hover {
            background-color: var(--primary-dark);
        }

        .notification-toggle i {
            font-size: 1rem;
        }

        /* Toast notification */
        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1000;
        }

        .toast {
            background-color: white;
            border-left: 4px solid var(--primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            max-width: 400px;
            width: 100%;
            position: relative;
            overflow: hidden;
            animation: slideIn 0.3s ease-out;
        }

        .toast::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            width: 100%;
            background-color: var(--primary-light);
            animation: toast-timer 5s linear forwards;
        }

        @keyframes toast-timer {
            0% {
                width: 100%;
            }
            100% {
                width: 0%;
            }
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .toast-icon {
            font-size: 1.25rem;
            color: var(--primary);
            margin-top: 0.25rem;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .toast-message {
            font-size: 0.75rem;
            color: var(--gray-dark);
            line-height: 1.5;
        }

        .toast-message p {
            margin: 0.25rem 0;
        }

        .toast-close {
            color: var(--gray);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            margin-top: 0.25rem;
        }

        .toast-flag {
            width: 20px;
            height: auto;
            vertical-align: middle;
            border-radius: 2px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            margin-right: 0.25rem;
        }

        /* Notification popup */
        .notification-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            padding: 1.5rem;
            z-index: 1001;
            max-width: 500px;
            width: 90%;
            display: none;
        }

        .notification-popup.show {
            display: block;
            animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .notification-popup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .notification-popup-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notification-popup-close {
            color: var(--gray);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.25rem;
        }

        .notification-popup-body {
            margin-bottom: 1rem;
        }

        .notification-popup-footer {
            display: flex;
            justify-content: flex-end;
        }

        .notification-popup-button {
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .notification-popup-button:hover {
            background-color: var(--primary-dark);
        }

        .notification-popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
        }

        .notification-popup-overlay.show {
            display: block;
        }

        .notification-info-grid {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.5rem 1rem;
            margin: 1rem 0;
        }

        .notification-info-label {
            font-weight: 500;
            color: var(--gray-dark);
        }

        .notification-info-value {
            color: var(--dark);
            word-break: break-word;
        }

        @keyframes popIn {
            0% {
                transform: translate(-50%, -50%) scale(0.8);
                opacity: 0;
            }
            100% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .refresh-info {
                width: 100%;
                justify-content: space-between;
            }
            
            .visitors-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .visitor-info-grid {
                grid-template-columns: 1fr;
            }
            
            .visitor-location {
                grid-column: span 1;
            }
            
            .refresh-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            
            .notification-toggle, .clear-visits-button {
                width: 100%;
                justify-content: center;
            }
            
            .volume-control {
                width: 100%;
                justify-content: space-between;
                margin-left: 0;
            }
            
            .toast {
                max-width: calc(100% - 2rem);
            }
        }

        .volume-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: 1rem;
        }

        .volume-control i {
            color: var(--primary);
        }

        .volume-control input[type="range"] {
            width: 80px;
            height: 4px;
            -webkit-appearance: none;
            background: var(--gray-light);
            border-radius: 2px;
            outline: none;
        }

        .volume-control input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 12px;
            height: 12px;
            background: var(--primary);
            border-radius: 50%;
            cursor: pointer;
        }

        .volume-control input[type="range"]::-moz-range-thumb {
            width: 12px;
            height: 12px;
            background: var(--primary);
            border-radius: 50%;
            cursor: pointer;
            border: none;
        }

        .card-header {
            padding: 1rem 1.5rem;
            background-color: white;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-title i {
            color: var(--primary);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .pagination-button {
            background-color: white;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .pagination-button:hover {
            background-color: var(--gray-light);
        }

        .pagination-button.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .connection-icon {
            font-size: 1rem;
            margin-right: 0.25rem;
        }

        .clear-visits-button {
            background-color: var(--danger);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color 0.2s;
            margin-left: 0.5rem;
        }

        .clear-visits-button:hover {
            background-color: #dc2626;
        }

        .clear-visits-button i {
            font-size: 1rem;
        }

        /* Confirmation dialog */
        .confirmation-dialog {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            padding: 1.5rem;
            z-index: 1002;
            max-width: 400px;
            width: 90%;
            display: none;
        }

        .confirmation-dialog.show {
            display: block;
            animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .confirmation-dialog-header {
            margin-bottom: 1rem;
            font-weight: 600;
            font-size: 1.125rem;
            color: var(--dark);
        }

        .confirmation-dialog-body {
            margin-bottom: 1.5rem;
            color: var(--gray-dark);
        }

        .confirmation-dialog-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .confirmation-dialog-button {
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .confirmation-dialog-button-cancel {
            background-color: var(--gray-light);
            color: var(--gray-dark);
            border: none;
        }

        .confirmation-dialog-button-cancel:hover {
            background-color: var(--gray);
            color: white;
        }

        .confirmation-dialog-button-confirm {
            background-color: var(--danger);
            color: white;
            border: none;
        }

        .confirmation-dialog-button-confirm:hover {
            background-color: #dc2626;
        }

        /* Block options */
        .visitor-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .block-button {
            background-color: var(--danger);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            transition: background-color 0.2s;
        }

        .block-button:hover {
            background-color: #dc2626;
        }

        .block-button i {
            font-size: 0.75rem;
        }

        .block-dropdown {
            position: relative;
            display: inline-block;
        }

        .block-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: white;
            min-width: 160px;
            box-shadow: var(--shadow-lg);
            border-radius: var(--border-radius);
            z-index: 1;
            margin-top: 0.25rem;
        }

        .block-dropdown-content a {
            color: var(--dark);
            padding: 0.5rem 1rem;
            text-decoration: none;
            display: block;
            font-size: 0.875rem;
            transition: background-color 0.2s;
        }

        .block-dropdown-content a:hover {
            background-color: var(--gray-light);
        }

        .block-dropdown:hover .block-dropdown-content {
            display: block;
        }

        .blocked-badge {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        .blocked-badge i {
            margin-right: 0.25rem;
        }

        /* Blocked list section */
        .blocked-list-section {
            margin-top: 2rem;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .blocked-list-header {
            padding: 1rem 1.5rem;
            background-color: white;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .blocked-list-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .blocked-list-title i {
            color: var(--danger);
        }

        .blocked-list-body {
            padding: 1rem 1.5rem;
        }

        .blocked-list-empty {
            text-align: center;
            padding: 2rem;
            color: var(--gray);
        }

        .blocked-list-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }

        .blocked-item {
            background-color: var(--gray-light);
            border-radius: var(--border-radius);
            padding: 0.5rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .blocked-item-text {
            font-family: monospace;
            font-size: 0.875rem;
            word-break: break-all;
        }

        .blocked-item-remove {
            color: var(--danger);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .blocked-item-remove:hover {
            opacity: 1;
        }

        .blocked-tabs {
            display: flex;
            border-bottom: 1px solid var(--gray-light);
            margin-bottom: 1rem;
        }

        .blocked-tab {
            padding: 0.5rem 1rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }

        .blocked-tab.active {
            border-bottom-color: var(--primary);
            color: var(--primary);
            font-weight: 500;
        }

        .blocked-tab:hover:not(.active) {
            border-bottom-color: var(--gray-light);
            background-color: rgba(0, 0, 0, 0.02);
        }

        .blocked-tab-content {
            display: none;
        }

        .blocked-tab-content.active {
            display: block;
        }

        /* Logout button */
        .logout-button {
            background-color: var(--gray);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color 0.2s;
            margin-left: auto;
        }

        .logout-button:hover {
            background-color: var(--gray-dark);
        }

        .logout-button i {
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .logout-button {
                margin-left: 0;
                width: 100%;
                justify-content: center;
            }
        }

        .denied-badge {
            display: inline-flex;
            align-items: center;
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        .denied-badge i {
            margin-right: 0.25rem;
        }

        /* Animation styles */
        .stat-card-pulse {
            animation: pulse 1s cubic-bezier(0.4, 0, 0.6, 1);
        }

        @keyframes pulse {
            0%, 100% {
                background-color: white;
            }
            50% {
                background-color: rgba(79, 70, 229, 0.1);
            }
        }

        .new-visitor {
            animation: highlight 2s ease-out;
            position: relative;
            overflow: hidden;
        }

        .new-visitor::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(79, 70, 229, 0) 0%, rgba(79, 70, 229, 0.1) 50%, rgba(79, 70, 229, 0) 100%);
            animation: shine 2s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes shine {
            0% {
                transform: translateX(-100%);
            }
            100% {
                transform: translateX(100%);
            }
        }
    </style>
</head>
<body>
  <div class="container">
      <div class="header">
          <h1 class="title"><i class="fas fa-chart-line"></i> Visitor Tracking Dashboard</h1>
          <div class="refresh-info">
              <button id="notificationToggle" class="notification-toggle">
                  <i class="fas fa-bell"></i> Notifications: <span id="notificationStatus">On</span>
              </button>
              <button id="clearVisitsBtn" class="clear-visits-button">
                  <i class="fas fa-trash-alt"></i> Clear Visits
              </button>
              <div class="volume-control">
                  <i class="fas fa-volume-up"></i>
                  <input type="range" id="volumeSlider" min="0" max="100" value="80">
              </div>
              <a href="../index.php?action=logout" class="logout-button">
                  <i class="fas fa-sign-out-alt"></i> Logout
              </a>
          </div>
      </div>
      
      <div class="stats-grid">
          <div class="stat-card">
              <i class="fas fa-users stat-icon"></i>
              <div class="stat-title">Total Visits</div>
              <div class="stat-value"><?php echo $total_visitors; ?></div>
          </div>
          <div class="stat-card">
              <i class="fas fa-user-check stat-icon"></i>
              <div class="stat-title">Unique Visitors</div>
              <div class="stat-value"><?php echo $unique_visitors; ?></div>
          </div>
          <div class="stat-card">
              <i class="fas fa-globe stat-icon"></i>
              <div class="stat-title">Countries</div>
              <div class="stat-value"><?php echo count($unique_countries); ?></div>
          </div>
          <div class="stat-card">
              <i class="fas fa-robot stat-icon"></i>
              <div class="stat-title">Bots / Humans</div>
              <div class="stat-value"><?php echo $bots; ?> / <?php echo $humans; ?></div>
          </div>
          <div class="stat-card">
              <i class="fab fa-apple stat-icon"></i>
              <div class="stat-title">iOS Devices</div>
              <div class="stat-value"><?php echo $ios_count; ?></div>
          </div>
          <div class="stat-card">
              <i class="fab fa-android stat-icon"></i>
              <div class="stat-title">Android Devices</div>
              <div class="stat-value"><?php echo $android_count; ?></div>
          </div>
      </div>
      
      <div class="card-header">
          <h2 class="card-title"><i class="fas fa-users"></i> Unique Visitors</h2>
          <div class="refresh-info">
              <i class="fas fa-sync-alt"></i> Auto-refresh in <span id="countdown">60</span>s
          </div>
      </div>
      
      <?php if (empty($unique_ip_logs)): ?>
          <div class="no-data">
              <i class="fas fa-info-circle fa-2x mb-3"></i>
              <p>No visitor data available yet</p>
          </div>
      <?php else: ?>
          <div class="visitors-grid">
              <?php foreach ($unique_ip_logs as $index => $log): 
                  $visitorClass = isset($log['is_bot']) && $log['is_bot'] ? 'bot-visitor' : (isset($log['is_proxy']) && $log['is_proxy'] ? 'proxy-visitor' : 'human-visitor');
                  $connectionType = getConnectionType($log);
                  $ip_blocked = isIPBlocked($log['ip'], $blocked_data);
                  $isp_blocked = isset($log['isp']) && isISPBlocked($log['isp'], $blocked_data);
              ?>
              <div class="visitor-card <?php echo $visitorClass; ?> <?php echo $index === 0 ? 'new-visitor' : ''; ?>" data-id="<?php echo htmlspecialchars($log['id'] ?? ''); ?>">
                  <div class="visitor-card-header">
                      <div class="visitor-ip">
                          <?php echo htmlspecialchars($log['ip']); ?>
                          <?php if ($ip_blocked): ?>
                              <span class="blocked-badge"><i class="fas fa-ban"></i> Blocked</span>
                          <?php endif; ?>
                      </div>
                      <div class="visitor-time"><?php echo htmlspecialchars(date('H:i:s', strtotime($log['timestamp']))); ?></div>
                  </div>
                  <div class="visitor-card-body">
                      <div class="visitor-location">
                          <?php if (!empty($log['country_code']) && $log['country_code'] !== 'xx'): ?>
                              <img src="https://flagcdn.com/32x24/<?php echo strtolower($log['country_code']); ?>.png" 
                                   class="flag-icon" alt="<?php echo htmlspecialchars($log['country']); ?> flag">
                          <?php else: ?>
                              <i class="fas fa-flag flag-icon"></i>
                          <?php endif; ?>
                          <div class="visitor-location-text">
                              <?php 
                              $location = [];
                              if (!empty($log['country'])) $location[] = $log['country'];
                              if (!empty($log['region']) && $log['region'] !== 'Unknown') $location[] = $log['region'];
                              if (!empty($log['city']) && $log['city'] !== 'Unknown') $location[] = $log['city'];
                              echo htmlspecialchars(implode(', ', $location));
                              
                              // Check if country is allowed based on configuration
                              $config = [];
                              if (file_exists($baseDir . 'data/config.json')) {
                                  $config = json_decode(file_get_contents($baseDir . 'data/config.json'), true);
                              }
                              $country_mode = $config['country_mode'] ?? 'allow_all';
                              $allowed_countries = $config['allowed_countries'] ?? [];
                              
                              $isCountryAllowed = true;
                              if ($country_mode !== 'allow_all' && !empty($log['country_code']) && $log['country_code'] !== 'xx') {
                                  if ($country_mode === 'allow_selected') {
                                      $isCountryAllowed = in_array(strtolower($log['country_code']), $allowed_countries);
                                  } else if ($country_mode === 'block_selected') {
                                      $isCountryAllowed = !in_array(strtolower($log['country_code']), $allowed_countries);
                                  }
                              }
                              
                              if (!$isCountryAllowed): 
                              ?>
                                  <span class="denied-badge"><i class="fas fa-ban"></i> Denied</span>
                              <?php endif; ?>
                          </div>
                      </div>
                      <div class="visitor-info-grid">
                          <div class="visitor-info-item">
                              <div class="visitor-info-label">Device</div>
                              <div class="visitor-info-value">
                                  <?php echo getOSIcon($log['os']); ?>
                                  <?php echo htmlspecialchars($log['os'] ?? 'Unknown'); ?>
                              </div>
                          </div>
                          <div class="visitor-info-item">
                              <div class="visitor-info-label">ISP</div>
                              <div class="visitor-info-value">
                                  <i class="fas fa-network-wired"></i>
                                  <?php echo htmlspecialchars($log['isp'] ?? 'Unknown'); ?>
                                  <?php if ($isp_blocked): ?>
                                      <span class="blocked-badge"><i class="fas fa-ban"></i> Blocked</span>
                                  <?php endif; ?>
                              </div>
                          </div>
                          <div class="visitor-info-item">
                              <div class="visitor-info-label">Date</div>
                              <div class="visitor-info-value">
                                  <i class="far fa-calendar-alt"></i>
                                  <?php echo htmlspecialchars(date('Y-m-d', strtotime($log['timestamp']))); ?>
                              </div>
                          </div>
                      </div>
                      <div class="visitor-actions">
                          <div class="block-dropdown">
                              <button class="block-button">
                                  <i class="fas fa-ban"></i> Block
                              </button>
                              <div class="block-dropdown-content">
                                  <a href="#" class="block-ip" data-ip="<?php echo htmlspecialchars($log['ip']); ?>">
                                      <i class="fas fa-ban"></i> Block IP
                                  </a>
                                  <?php if (!empty($log['isp'])): ?>
                                  <a href="#" class="block-isp" data-isp="<?php echo htmlspecialchars($log['isp']); ?>">
                                      <i class="fas fa-ban"></i> Block ISP
                                  </a>
                                  <?php endif; ?>
                              </div>
                          </div>
                      </div>
                  </div>
                  <div class="visitor-card-footer">
                      <?php if (isset($log['is_bot']) && $log['is_bot']): ?>
                          <span class="badge badge-bot"><i class="fas fa-robot"></i> BOT</span>
                      <?php else: ?>
                          <span class="badge badge-human"><i class="fas fa-user"></i> HUMAN</span>
                      <?php endif; ?>
                      
                      <span class="badge <?php echo $connectionType[0]; ?>">
                          <i class="fas <?php echo $connectionType[2]; ?> connection-icon"></i> 
                          <?php echo $connectionType[1]; ?>
                      </span>
                      
                      <?php if (isset($log['is_mobile']) && $log['is_mobile']): ?>
                          <span class="badge badge-mobile"><i class="fas fa-mobile-alt"></i> MOBILE</span>
                      <?php endif; ?>
                      
                      <?php if (isset($log['is_hosting']) && $log['is_hosting']): ?>
                          <span class="badge badge-hosting"><i class="fas fa-server"></i> HOSTING</span>
                      <?php endif; ?>
                  </div>
              </div>
              <?php endforeach; ?>
          </div>
          
          <div class="pagination">
              <button class="pagination-button" id="prevPage" disabled><i class="fas fa-chevron-left"></i></button>
              <button class="pagination-button active">1</button>
              <button class="pagination-button" id="nextPage" disabled><i class="fas fa-chevron-right"></i></button>
          </div>
      <?php endif; ?>
      
      <!-- Blocked List Section -->
      <div class="blocked-list-section">
          <div class="blocked-list-header">
              <h2 class="blocked-list-title"><i class="fas fa-ban"></i> Blocked Visitors</h2>
          </div>
          <div class="blocked-list-body">
              <div class="blocked-tabs">
                  <div class="blocked-tab active" data-tab="blocked-ips">Blocked IPs</div>
                  <div class="blocked-tab" data-tab="blocked-isps">Blocked ISPs</div>
              </div>
              
              <div class="blocked-tab-content active" id="blocked-ips">
                  <?php if (empty($blocked_data['blocked_ips'])): ?>
                      <div class="blocked-list-empty">No IP addresses have been blocked yet</div>
                  <?php else: ?>
                      <div class="blocked-list-grid">
                          <?php foreach ($blocked_data['blocked_ips'] as $blocked_ip): ?>
                              <div class="blocked-item">
                                  <span class="blocked-item-text"><?php echo htmlspecialchars($blocked_ip); ?></span>
                                  <button class="blocked-item-remove" data-type="ip" data-value="<?php echo htmlspecialchars($blocked_ip); ?>">
                                      <i class="fas fa-times"></i>
                                  </button>
                              </div>
                          <?php endforeach; ?>
                      </div>
                  <?php endif; ?>
              </div>
              
              <div class="blocked-tab-content" id="blocked-isps">
                  <?php if (empty($blocked_data['blocked_isps'])): ?>
                      <div class="blocked-list-empty">No ISPs have been blocked yet</div>
                  <?php else: ?>
                      <div class="blocked-list-grid">
                          <?php foreach ($blocked_data['blocked_isps'] as $blocked_isp): ?>
                              <div class="blocked-item">
                                  <span class="blocked-item-text"><?php echo htmlspecialchars($blocked_isp); ?></span>
                                  <button class="blocked-item-remove" data-type="isp" data-value="<?php echo htmlspecialchars($blocked_isp); ?>">
                                      <i class="fas fa-times"></i>
                                  </button>
                              </div>
                          <?php endforeach; ?>
                      </div>
                  <?php endif; ?>
              </div>
          </div>
      </div>
      
      <div class="footer">
          <p>Last updated: <?php echo date('Y-m-d H:i:s'); ?></p>
          <p>Visitor Tracking System &copy; <?php echo date('Y'); ?></p>
      </div>
  </div>
  
  <!-- Toast notification container -->
  <div id="toastContainer" class="toast-container"></div>
  
  <!-- Notification popup and overlay -->
  <div id="notificationPopupOverlay" class="notification-popup-overlay"></div>
  <div id="notificationPopup" class="notification-popup">
      <div class="notification-popup-header">
          <div class="notification-popup-title">
              <i class="fas fa-bell"></i> New Visitor Detected
          </div>
          <button id="notificationPopupClose" class="notification-popup-close">
              <i class="fas fa-times"></i>
          </button>
      </div>
      <div class="notification-popup-body">
          <div id="notificationPopupContent"></div>
      </div>
      <div class="notification-popup-footer">
          <button id="notificationPopupButton" class="notification-popup-button">OK</button>
      </div>
  </div>

  <!-- Custom notification sound -->
  <audio id="notificationSound" preload="auto">
      <source src="../assets/sounds/notification.mp3" type="audio/mpeg">
  </audio>

  <!-- Confirmation dialog for clearing visits -->
  <div id="confirmationDialog" class="confirmation-dialog">
      <div class="confirmation-dialog-header">
          <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i> Confirm Action
      </div>
      <div class="confirmation-dialog-body">
          Are you sure you want to clear all visitor logs? This action cannot be undone.
      </div>
      <div class="confirmation-dialog-footer">
          <button id="cancelClearBtn" class="confirmation-dialog-button confirmation-dialog-button-cancel">Cancel</button>
          <button id="confirmClearBtn" class="confirmation-dialog-button confirmation-dialog-button-confirm">Clear All</button>
      </div>
  </div>

  <script>
      // Store the current visitor IDs and count
      let currentVisitorIds = <?php echo json_encode(array_column($logs, 'id')); ?>;
      let notificationsEnabled = true; // Enabled by default
      let lastVisitorCount = <?php echo $total_visitors; ?>;
      let lastCheckTime = new Date();
      
      // Pagination variables
      const itemsPerPage = 10;
      let currentPage = 1;
      let totalPages = Math.ceil(<?php echo count($unique_ip_logs); ?> / itemsPerPage);
      
      // Set of notified IPs to prevent duplicate notifications
      let notifiedIPs = new Set();
      
      // Update notification status display
      document.getElementById('notificationStatus').textContent = notificationsEnabled ? 'On' : 'Off';
      
      // Countdown timer for refresh
      let seconds = 60;
      const countdownElement = document.getElementById('countdown');
      
      function updateCountdown() {
          seconds--;
          countdownElement.textContent = seconds;
          if (seconds > 0) {
              setTimeout(updateCountdown, 1000);
          } else {
              // When countdown reaches 0, refresh the page
              window.location.reload();
          }
      }
      
      // Function to show toast notification
      function showToast(title, message, icon = 'fa-bell', playSound = false) {
          const toastContainer = document.getElementById('toastContainer');
          const toast = document.createElement('div');
          toast.className = 'toast';
          toast.innerHTML = `
          <div class="toast-icon">
              <i class="fas ${icon}"></i>
          </div>
          <div class="toast-content">
              <div class="toast-title">${title}</div>
              <div class="toast-message">${message}</div>
          </div>
          <button class="toast-close">
              <i class="fas fa-times"></i>
          </button>
          `;
      
          toastContainer.appendChild(toast);
      
          // Play sound if enabled
          if (playSound && notificationsEnabled) {
              playNotificationSound();
          }
      
          // Auto remove after 5 seconds
          setTimeout(() => {
              toast.style.opacity = '0';
              setTimeout(() => {
                  toast.remove();
              }, 300);
          }, 5000);
      
          // Close button
          toast.querySelector('.toast-close').addEventListener('click', () => {
              toast.style.opacity = '0';
              setTimeout(() => {
                  toast.remove();
              }, 300);
          });
      }
      
      // Function to play notification sound with error handling
      function playNotificationSound() {
          const volume = document.getElementById('volumeSlider').value / 100;
          const sound = document.getElementById('notificationSound');
          sound.volume = volume;
          sound.currentTime = 0;
          
          const playPromise = sound.play();
          if (playPromise !== undefined) {
              playPromise.catch(error => {
                  console.log("Autoplay prevented, will play on next user interaction:", error);
                  
                  // Add a one-time click handler to play sound on next user interaction
                  const playOnInteraction = function() {
                      sound.play().catch(e => console.log("Still couldn't play sound:", e));
                      document.removeEventListener('click', playOnInteraction);
                  };
                  document.addEventListener('click', playOnInteraction, { once: true });
              });
          }
      }
      
      // Function to show notification popup
      function showNotificationPopup(visitor) {
          // Create location string
          const location = [];
          if (visitor.country) location.push(visitor.country);
          if (visitor.region && visitor.region !== 'Unknown') location.push(visitor.region);
          if (visitor.city && visitor.city !== 'Unknown') location.push(visitor.city);
          const locationStr = location.join(', ') || 'Unknown location';
      
          // Get flag HTML
          let flagHtml = '';
          if (visitor.country_code && visitor.country_code !== 'xx') {
              flagHtml = `<img src="https://flagcdn.com/32x24/${visitor.country_code.toLowerCase()}.png" class="toast-flag" alt="${visitor.country} flag">`;
          } else {
              flagHtml = '<i class="fas fa-flag toast-flag"></i>';
          }
      
          // Get visitor type
          const visitorType = visitor.is_bot ? 'Bot' : 'Human';
          const visitorTypeIcon = visitor.is_bot ? 'fa-robot' : 'fa-user';
      
          // Get OS icon
          let osIcon = getOSIconHTML(visitor.os || '');
      
          // Determine connection type
          const connectionType = getConnectionTypeFromVisitor(visitor);
      
          // Update popup title
          document.querySelector('.notification-popup-title').innerHTML = `
          <i class="fas ${visitorTypeIcon}"></i> New ${visitorType} Visitor
          `;
      
          // Update popup content
          document.getElementById('notificationPopupContent').innerHTML = `
          <div class="notification-info-grid">
              <div class="notification-info-label">IP Address:</div>
              <div class="notification-info-value">${visitor.ip}</div>
          
              <div class="notification-info-label">Location:</div>
              <div class="notification-info-value">${flagHtml} ${locationStr}</div>
          
              <div class="notification-info-label">Device:</div>
              <div class="notification-info-value">${osIcon} ${visitor.os || 'Unknown'}</div>
          
              <div class="notification-info-label">ISP:</div>
              <div class="notification-info-value">
                  <i class="fas fa-network-wired"></i> ${visitor.isp || 'Unknown'}
              </div>
          
              <div class="notification-info-label">Time:</div>
              <div class="notification-info-value">${visitor.timestamp}</div>
          
              <div class="notification-info-label">Connection:</div>
              <div class="notification-info-value">
                  <i class="fas ${connectionType[2]}"></i> ${connectionType[1]}
              </div>
          </div>
          `;
      
          // Show popup and overlay with animation
          const popup = document.getElementById('notificationPopup');
          const overlay = document.getElementById('notificationPopupOverlay');
          
          popup.classList.add('show');
          overlay.classList.add('show');
          
          // Play notification sound
          if (notificationsEnabled) {
              playNotificationSound();
          }
          
          // Also show a toast notification
          showToast('New Visitor', `${visitorType} from ${locationStr}`, visitorTypeIcon, false);
          
          // Add a new visitor card to the grid without full page reload
          addNewVisitorCard(visitor);
      }
      
      // Helper function to get OS icon HTML
      function getOSIconHTML(os) {
          os = os.toLowerCase();
          
          // Mobile devices - iOS
          if (os.includes('iphone')) {
              return '<i class="fab fa-apple os-icon" style="color: #000;"></i>';
          } else if (os.includes('ipad')) {
              return '<i class="fas fa-tablet-alt os-icon" style="color: #000;"></i>';
          } else if (os.includes('ipod')) {
              return '<i class="fas fa-music os-icon" style="color: #000;"></i>';
          } else if (os.includes('ios')) {
              return '<i class="fab fa-apple os-icon" style="color: #000;"></i>';
          }
          // Mobile devices - Android
          else if (os.includes('android')) {
              return '<i class="fab fa-android os-icon" style="color: #A4C639;"></i>';
          } else if (os.includes('blackberry')) {
              return '<i class="fab fa-blackberry os-icon" style="color: #000;"></i>';
          } else if (os.includes('mobile')) {
              return '<i class="fas fa-mobile-alt os-icon" style="color: #6B7280;"></i>';
          }
          
          // Desktop OS
          else if (os.includes('windows 10') || os.includes('windows nt 10')) {
              return '<i class="fab fa-windows os-icon" style="color: #0078D7;"></i>';
          } else if (os.includes('windows 11')) {
              return '<i class="fab fa-windows os-icon" style="color: #0078D7;"></i>';
          } else if (os.includes('windows')) {
              return '<i class="fab fa-windows os-icon" style="color: #0078D7;"></i>';
          } else if (os.includes('mac')) {
              return '<i class="fab fa-apple os-icon" style="color: #999;"></i>';
          } else if (os.includes('ubuntu')) {
              return '<i class="fab fa-ubuntu os-icon" style="color: #E95420;"></i>';
          } else if (os.includes('debian')) {
              return '<i class="fab fa-debian os-icon" style="color: #A80030;"></i>';
          } else if (os.includes('fedora')) {
              return '<i class="fab fa-fedora os-icon" style="color: #294172;"></i>';
          } else if (os.includes('centos')) {
              return '<i class="fab fa-centos os-icon" style="color: #9CCD2A;"></i>';
          } else if (os.includes('linux')) {
              return '<i class="fab fa-linux os-icon" style="color: #FCC624;"></i>';
          }
          
          // Default
          return '<i class="fas fa-desktop os-icon" style="color: #6B7280;"></i>';
      }
      
      // Helper function to get connection type from visitor data
      function getConnectionTypeFromVisitor(visitor) {
          if (visitor.is_proxy) {
              return ['badge-proxy', 'VPN/PROXY', 'fa-shield-alt'];
          } else if (visitor.is_hosting) {
              return ['badge-hosting', 'HOSTING', 'fa-server'];
          } else if (visitor.is_mobile) {
              // For mobile, we'll randomly assign a connection type
              const mobileConnections = [
                  ['badge-5g', '5G', 'fa-signal'],
                  ['badge-4g', '4G', 'fa-signal'],
                  ['badge-3g', '3G', 'fa-signal'],
                  ['badge-wifi', 'WIFI', 'fa-wifi']
              ];
              
              // Use the IP as a seed for consistent results
              const seed = Math.abs(visitor.ip.split('.').reduce((a, b) => a + parseInt(b), 0)) % mobileConnections.length;
              return mobileConnections[seed];
          } else {
              // For desktop, assume either WiFi or satellite
              const desktopConnections = [
                  ['badge-wifi', 'WIFI', 'fa-wifi'],
                  ['badge-satellite', 'SATELLITE', 'fa-satellite-dish']
              ];
              
              // Use the IP as a seed for consistent results
              const seed = Math.abs(visitor.ip.split('.').reduce((a, b) => a + parseInt(b), 0)) % desktopConnections.length;
              return desktopConnections[seed];
          }
      }
      
      // Function to add a new visitor card to the grid
      function addNewVisitorCard(visitor) {
          const visitorGrid = document.querySelector('.visitors-grid');
          if (!visitorGrid) return;
          
          // Remove "no data" message if it exists
          const noData = document.querySelector('.no-data');
          if (noData) {
              noData.remove();
          }
          
          // Create visitor card
          const visitorClass = visitor.is_bot ? 'bot-visitor' : (visitor.is_proxy ? 'proxy-visitor' : 'human-visitor');
          const connectionType = getConnectionTypeFromVisitor(visitor);
          
          const card = document.createElement('div');
          card.className = `visitor-card ${visitorClass} new-visitor`;
          card.dataset.id = visitor.id || '';
          
          // Format the time
          const time = new Date(visitor.timestamp).toTimeString().split(' ')[0];
          
          // Create flag HTML
          let flagHtml = '';
          if (visitor.country_code && visitor.country_code !== 'xx') {
              flagHtml = `<img src="https://flagcdn.com/32x24/${visitor.country_code.toLowerCase()}.png" class="flag-icon" alt="${visitor.country} flag">`;
          } else {
              flagHtml = '<i class="fas fa-flag flag-icon"></i>';
          }
          
          // Create location string
          const location = [];
          if (visitor.country) location.push(visitor.country);
          if (visitor.region && visitor.region !== 'Unknown') location.push(visitor.region);
          if (visitor.city && visitor.city !== 'Unknown') location.push(visitor.city);
          const locationStr = location.join(', ') || 'Unknown location';
          
          // Get OS icon
          const osIcon = getOSIconHTML(visitor.os || '');
          
          card.innerHTML = `
              <div class="visitor-card-header">
                  <div class="visitor-ip">
                      ${visitor.ip}
                  </div>
                  <div class="visitor-time">${time}</div>
              </div>
              <div class="visitor-card-body">
                  <div class="visitor-location">
                      ${flagHtml}
                      <div class="visitor-location-text">
                          ${locationStr}
                      </div>
                  </div>
                  <div class="visitor-info-grid">
                      <div class="visitor-info-item">
                          <div class="visitor-info-label">Device</div>
                          <div class="visitor-info-value">
                              ${osIcon}
                              ${visitor.os || 'Unknown'}
                          </div>
                      </div>
                      <div class="visitor-info-item">
                          <div class="visitor-info-label">ISP</div>
                          <div class="visitor-info-value">
                              <i class="fas fa-network-wired"></i>
                              ${visitor.isp || 'Unknown'}
                          </div>
                      </div>
                      <div class="visitor-info-item">
                          <div class="visitor-info-label">Date</div>
                          <div class="visitor-info-value">
                              <i class="far fa-calendar-alt"></i>
                              ${new Date(visitor.timestamp).toLocaleDateString()}
                          </div>
                      </div>
                  </div>
                  <div class="visitor-actions">
                      <div class="block-dropdown">
                          <button class="block-button">
                              <i class="fas fa-ban"></i> Block
                          </button>
                          <div class="block-dropdown-content">
                              <a href="#" class="block-ip" data-ip="${visitor.ip}">
                                  <i class="fas fa-ban"></i> Block IP
                              </a>
                              ${visitor.isp ? `
                              <a href="#" class="block-isp" data-isp="${visitor.isp}">
                                  <i class="fas fa-ban"></i> Block ISP
                              </a>
                              ` : ''}
                          </div>
                      </div>
                  </div>
              </div>
              <div class="visitor-card-footer">
                  ${visitor.is_bot ? 
                      `<span class="badge badge-bot"><i class="fas fa-robot"></i> BOT</span>` : 
                      `<span class="badge badge-human"><i class="fas fa-user"></i> HUMAN</span>`
                  }
                  
                  <span class="badge ${connectionType[0]}">
                      <i class="fas ${connectionType[2]} connection-icon"></i> 
                      ${connectionType[1]}
                  </span>
                  
                  ${visitor.is_mobile ? 
                      `<span class="badge badge-mobile"><i class="fas fa-mobile-alt"></i> MOBILE</span>` : ''
                  }
                  
                  ${visitor.is_hosting ? 
                      `<span class="badge badge-hosting"><i class="fas fa-server"></i> HOSTING</span>` : ''
                  }
              </div>
          `;
          
          // Add to the beginning of the grid
          if (visitorGrid.firstChild) {
              visitorGrid.insertBefore(card, visitorGrid.firstChild);
          } else {
              visitorGrid.appendChild(card);
          }
          
          // Update pagination
          updatePagination();
      }
      
      // Function to update pagination
      function updatePagination() {
          const visitorCards = document.querySelectorAll('.visitor-card');
          totalPages = Math.ceil(visitorCards.length / itemsPerPage);
          
          // Update pagination buttons
          document.getElementById('prevPage').disabled = currentPage <= 1;
          document.getElementById('nextPage').disabled = currentPage >= totalPages;
          
          // Show/hide cards based on current page
          visitorCards.forEach((card, index) => {
              const shouldShow = index >= (currentPage - 1) * itemsPerPage && index < currentPage * itemsPerPage;
              card.style.display = shouldShow ? 'block' : 'none';
          });
      }
      
      // Function to check for new visitors
      function checkForNewVisitors() {
          fetch('?action=check_new_visitors&lastId=' + (currentVisitorIds.length > 0 ? currentVisitorIds[currentVisitorIds.length - 1] : ''), {
              headers: {
                  'Cache-Control': 'no-cache, no-store, must-revalidate',
                  'Pragma': 'no-cache',
                  'Expires': '0'
              }
          })
          .then(response => response.json())
          .then(data => {
              if (data.success && data.visitors && data.visitors.length > 0) {
                  // Process new visitors
                  data.visitors.forEach(visitor => {
                      // Check if this is a new visitor AND we haven't notified about this IP yet
                      if (!currentVisitorIds.includes(visitor.id) && !notifiedIPs.has(visitor.ip)) {
                          // Add this visitor to our current list
                          currentVisitorIds.push(visitor.id);
                          
                          // Add this IP to the notified set to prevent duplicate notifications
                          notifiedIPs.add(visitor.ip);
                          
                          // Show notification for this visitor
                          if (notificationsEnabled) {
                              showNotificationPopup(visitor);
                          }
                      }
                  });
                  
                  // Update stats without page reload
                  updateStats(data.visitors);
              }
          })
          .catch(error => {
              console.error('Error checking for new visitors:', error);
          });
      }
      
      // Function to update stats without page reload
      function updateStats(newVisitors) {
          if (!newVisitors || newVisitors.length === 0) return;
          
          // Update total visits
          const totalVisitsElement = document.querySelector('.stat-card:nth-child(1) .stat-value');
          if (totalVisitsElement) {
              const currentCount = parseInt(totalVisitsElement.textContent);
              totalVisitsElement.textContent = currentCount + newVisitors.length;
          }
          
          // Update unique visitors
          const uniqueVisitsElement = document.querySelector('.stat-card:nth-child(2) .stat-value');
          if (uniqueVisitsElement) {
              // Count unique IPs in new visitors
              const uniqueIPs = new Set();
              newVisitors.forEach(visitor => uniqueIPs.add(visitor.ip));
              
              const currentCount = parseInt(uniqueVisitsElement.textContent);
              uniqueVisitsElement.textContent = currentCount + uniqueIPs.size;
          }
          
          // Pulse the stats cards to indicate new data
          document.querySelectorAll('.stat-card').forEach(card => {
              card.classList.add('stat-card-pulse');
              setTimeout(() => {
                  card.classList.remove('stat-card-pulse');
              }, 1000);
          });
      }
      
      // Event listeners for notification popup
      document.getElementById('notificationPopupClose').addEventListener('click', function() {
          document.getElementById('notificationPopup').classList.remove('show');
          document.getElementById('notificationPopupOverlay').classList.remove('show');
      });
      
      document.getElementById('notificationPopupButton').addEventListener('click', function() {
          document.getElementById('notificationPopup').classList.remove('show');
          document.getElementById('notificationPopupOverlay').classList.remove('show');
      });
      
      document.getElementById('notificationPopupOverlay').addEventListener('click', function() {
          document.getElementById('notificationPopup').classList.remove('show');
          document.getElementById('notificationPopupOverlay').classList.remove('show');
      });
      
      // Event listener for notification toggle
      document.getElementById('notificationToggle').addEventListener('click', function() {
          notificationsEnabled = !notificationsEnabled;
          document.getElementById('notificationStatus').textContent = notificationsEnabled ? 'On' : 'Off';
          
          // Save preference to localStorage
          localStorage.setItem('notificationsEnabled', notificationsEnabled ? 'true' : 'false');
          
          // Show toast notification
          if (notificationsEnabled) {
              showToast('Notifications Enabled', 'You will be notified when new visitors arrive.', 'fa-bell', false);
              
              // Play a test sound to confirm
              playNotificationSound();
          } else {
              showToast('Notifications Disabled', 'You will no longer receive notifications.', 'fa-bell-slash', false);
          }
      });
      
      // Event listener for volume control
      document.getElementById('volumeSlider').addEventListener('input', function() {
          // Save volume preference to localStorage
          localStorage.setItem('notificationVolume', this.value);
      });
      
      // Event listener for clear visits button
      document.getElementById('clearVisitsBtn').addEventListener('click', function() {
          // Show confirmation dialog
          document.getElementById('confirmationDialog').classList.add('show');
          document.getElementById('notificationPopupOverlay').classList.add('show');
      });
      
      // Event listeners for confirmation dialog
      document.getElementById('cancelClearBtn').addEventListener('click', function() {
          document.getElementById('confirmationDialog').classList.remove('show');
          document.getElementById('notificationPopupOverlay').classList.remove('show');
      });
      
      document.getElementById('confirmClearBtn').addEventListener('click', function() {
          // Hide dialog
          document.getElementById('confirmationDialog').classList.remove('show');
          
          // Show loading toast
          showToast('Processing', 'Clearing visitor logs...', 'fa-spinner fa-spin', false);
          
          // Call clear_visits action
          fetch('?action=clear_visits', {
              method: 'POST',
              headers: {
                  'Cache-Control': 'no-cache, no-store, must-revalidate',
                  'Pragma': 'no-cache',
                  'Expires': '0'
              }
          })
          .then(response => response.json())
          .then(data => {
              if (data.success) {
                  // Show success toast
                  showToast('Success', data.message, 'fa-check-circle', false);
                  
                  // Clear the visitor grid immediately
                  const visitorGrid = document.querySelector('.visitors-grid');
                  if (visitorGrid) {
                      visitorGrid.innerHTML = '';
                  }
                  
                  // Update stats to zero
                  document.querySelectorAll('.stat-value').forEach(stat => {
                      stat.textContent = '0';
                  });
                  
                  // Show no data message if it doesn't exist
                  if (!document.querySelector('.no-data')) {
                      const noDataDiv = document.createElement('div');
                      noDataDiv.className = 'no-data';
                      noDataDiv.innerHTML = '<i class="fas fa-info-circle fa-2x mb-3"></i><p>No visitor data available yet</p>';
                  
                      // Insert after card header
                      const cardHeader = document.querySelector('.card-header');
                      if (cardHeader) {
                          cardHeader.insertAdjacentElement('afterend', noDataDiv);
                      }
                  }
                  
                  // Clear blocked lists
                  document.querySelectorAll('.blocked-list-grid').forEach(grid => {
                      grid.innerHTML = '';
                  });
                  
                  // Show empty messages for blocked lists
                  document.querySelectorAll('.blocked-tab-content').forEach(content => {
                      const emptyMessage = content.querySelector('.blocked-list-empty');
                      if (!emptyMessage) {
                          content.innerHTML = '<div class="blocked-list-empty">No IP addresses have been blocked yet</div>';
                      }
                  });
                  
                  // Hide overlay
                  document.getElementById('notificationPopupOverlay').classList.remove('show');
                  
                  // Reset visitor IDs array and counters
                  currentVisitorIds = [];
                  lastVisitorCount = 0;
                  notifiedIPs.clear(); // Clear the notified IPs set
                  
                  // Reset pagination
                  currentPage = 1;
                  totalPages = 0;
                  updatePagination();
              } else {
                  // Show error toast with the specific error message
                  showToast('Error', data.message || 'Failed to clear visitor logs.', 'fa-exclamation-circle', false);
                  document.getElementById('notificationPopupOverlay').classList.remove('show');
              }
          })
          .catch(error => {
              console.error('Error clearing visits:', error);
              showToast('Error', 'An error occurred while clearing visitor logs: ' + error.message, 'fa-exclamation-circle', false);
              document.getElementById('notificationPopupOverlay').classList.remove('show');
          });
      });
      
      // Event delegation for block IP and ISP buttons
      document.addEventListener('click', function(e) {
          // Block IP
          if (e.target.classList.contains('block-ip') || e.target.parentElement.classList.contains('block-ip')) {
              e.preventDefault();
              const element = e.target.classList.contains('block-ip') ? e.target : e.target.parentElement;
              const ip = element.dataset.ip;
              
              if (ip) {
                  // Show loading toast
                  showToast('Processing', `Blocking IP ${ip}...`, 'fa-spinner fa-spin', false);
                  
                  // Call block_ip action
                  fetch('?action=block_ip', {
                      method: 'POST',
                      headers: {
                          'Content-Type': 'application/x-www-form-urlencoded',
                          'Cache-Control': 'no-cache'
                      },
                      body: `ip=${encodeURIComponent(ip)}`
                  })
                  .then(response => response.json())
                  .then(data => {
                      if (data.success) {
                          // Show success toast
                          showToast('Success', data.message, 'fa-check-circle', false);
                          
                          // Add to blocked list without page reload
                          addToBlockedList('ip', ip);
                          
                          // Mark as blocked in the UI
                          markAsBlocked('ip', ip);
                      } else {
                          // Show error toast
                          showToast('Error', data.message || 'Failed to block IP.', 'fa-exclamation-circle', false);
                      }
                  })
                  .catch(error => {
                      console.error('Error blocking IP:', error);
                      showToast('Error', 'An error occurred while blocking the IP.', 'fa-exclamation-circle', false);
                  });
              }
          }
          
          // Block ISP
          if (e.target.classList.contains('block-isp') || e.target.parentElement.classList.contains('block-isp')) {
              e.preventDefault();
              const element = e.target.classList.contains('block-isp') ? e.target : e.target.parentElement;
              const isp = element.dataset.isp;
              
              if (isp) {
                  // Show loading toast
                  showToast('Processing', `Blocking ISP ${isp}...`, 'fa-spinner fa-spin', false);
                  
                  // Call block_isp action
                  fetch('?action=block_isp', {
                      method: 'POST',
                      headers: {
                          'Content-Type': 'application/x-www-form-urlencoded',
                          'Cache-Control': 'no-cache'
                      },
                      body: `isp=${encodeURIComponent(isp)}`
                  })
                  .then(response => response.json())
                  .then(data => {
                      if (data.success) {
                          // Show success toast
                          showToast('Success', data.message, 'fa-check-circle', false);
                          
                          // Add to blocked list without page reload
                          addToBlockedList('isp', isp);
                          
                          // Mark as blocked in the UI
                          markAsBlocked('isp', isp);
                      } else {
                          // Show error toast
                          showToast('Error', data.message || 'Failed to block ISP.', 'fa-exclamation-circle', false);
                      }
                  })
                  .catch(error => {
                      console.error('Error blocking ISP:', error);
                      showToast('Error', 'An error occurred while blocking the ISP.', 'fa-exclamation-circle', false);
                  });
              }
          }
          
          // Unblock item
          if (e.target.classList.contains('blocked-item-remove') || e.target.parentElement.classList.contains('blocked-item-remove')) {
              e.preventDefault();
              const element = e.target.classList.contains('blocked-item-remove') ? e.target : e.target.parentElement;
              const type = element.dataset.type;
              const value = element.dataset.value;
              
              if (type && value) {
                  // Show loading toast
                  showToast('Processing', `Unblocking ${type.toUpperCase()} ${value}...`, 'fa-spinner fa-spin', false);
                  
                  // Call unblock action
                  fetch(`?action=unblock_${type}`, {
                      method: 'POST',
                      headers: {
                          'Content-Type': 'application/x-www-form-urlencoded',
                          'Cache-Control': 'no-cache'
                      },
                      body: `${type}=${encodeURIComponent(value)}`
                  })
                  .then(response => response.json())
                  .then(data => {
                      if (data.success) {
                          // Show success toast
                          showToast('Success', data.message, 'fa-check-circle', false);
                          
                          // Remove from blocked list without page reload
                          const blockedItem = element.closest('.blocked-item');
                          if (blockedItem) {
                              blockedItem.remove();
                              
                              // Check if list is empty now
                              const blockedGrid = document.querySelector(`#blocked-${type}s .blocked-list-grid`);
                              if (blockedGrid && blockedGrid.children.length === 0) {
                                  blockedGrid.parentElement.innerHTML = `<div class="blocked-list-empty">No ${type === 'ip' ? 'IP addresses' : 'ISPs'} have been blocked yet</div>`;
                              }
                          }
                          
                          // Remove blocked badges from UI
                          removeBlockedBadges(type, value);
                      } else {
                          // Show error toast
                          showToast('Error', data.message || `Failed to unblock ${type}.`, 'fa-exclamation-circle', false);
                      }
                  })
                  .catch(error => {
                      console.error(`Error unblocking ${type}:`, error);
                      showToast('Error', `An error occurred while unblocking the ${type}.`, 'fa-exclamation-circle', false);
                  });
              }
          }
          
          // Tab switching
          if (e.target.classList.contains('blocked-tab')) {
              e.preventDefault();
              const tabId = e.target.dataset.tab;
              
              // Update active tab
              document.querySelectorAll('.blocked-tab').forEach(tab => {
                  tab.classList.remove('active');
              });
              e.target.classList.add('active');
              
              // Show corresponding content
              document.querySelectorAll('.blocked-tab-content').forEach(content => {
                  content.classList.remove('active');
              });
              document.getElementById(tabId).classList.add('active');
          }
          
          // Pagination
          if (e.target.id === 'prevPage' || e.target.parentElement.id === 'prevPage') {
              if (currentPage > 1) {
                  currentPage--;
                  updatePagination();
              }
          }
          
          if (e.target.id === 'nextPage' || e.target.parentElement.id === 'nextPage') {
              if (currentPage < totalPages) {
                  currentPage++;
                  updatePagination();
              }
          }
      });
      
      // Function to add to blocked list without page reload
      function addToBlockedList(type, value) {
          const tabContent = document.getElementById(`blocked-${type}s`);
          if (!tabContent) return;
          
          // Remove empty message if it exists
          const emptyMessage = tabContent.querySelector('.blocked-list-empty');
          if (emptyMessage) {
              emptyMessage.remove();
          }
          
          // Get or create the grid
          let grid = tabContent.querySelector('.blocked-list-grid');
          if (!grid) {
              grid = document.createElement('div');
              grid.className = 'blocked-list-grid';
              tabContent.appendChild(grid);
          }
          
          // Check if item already exists
          const existingItem = Array.from(grid.querySelectorAll('.blocked-item-text')).find(item => item.textContent === value);
          if (existingItem) return;
          
          // Create the blocked item
          const item = document.createElement('div');
          item.className = 'blocked-item';
          item.innerHTML = `
              <span class="blocked-item-text">${value}</span>
              <button class="blocked-item-remove" data-type="${type}" data-value="${value}">
                  <i class="fas fa-times"></i>
              </button>
          `;
          
          // Add to the grid
          grid.appendChild(item);
      }
      
      // Function to mark as blocked in the UI
      function markAsBlocked(type, value) {
          if (type === 'ip') {
              // Find all visitor cards with this IP
              document.querySelectorAll(`.visitor-card`).forEach(card => {
                  const ipElement = card.querySelector('.visitor-ip');
                  if (ipElement && ipElement.textContent.trim() === value) {
                      // Add blocked badge if it doesn't exist
                      if (!ipElement.querySelector('.blocked-badge')) {
                          const badge = document.createElement('span');
                          badge.className = 'blocked-badge';
                          badge.innerHTML = '<i class="fas fa-ban"></i> Blocked';
                          ipElement.appendChild(badge);
                      }
                  }
              });
          } else if (type === 'isp') {
              // Find all visitor cards with this ISP
              document.querySelectorAll(`.visitor-card`).forEach(card => {
                  const ispElements = card.querySelectorAll('.visitor-info-value');
                  ispElements.forEach(element => {
                      if (element.textContent.includes(value)) {
                          // Add blocked badge if it doesn't exist
                          if (!element.querySelector('.blocked-badge')) {
                              const badge = document.createElement('span');
                              badge.className = 'blocked-badge';
                              badge.innerHTML = '<i class="fas fa-ban"></i> Blocked';
                              element.appendChild(badge);
                          }
                      }
                  });
              });
          }
      }
      
      // Function to remove blocked badges from UI
      function removeBlockedBadges(type, value) {
          if (type === 'ip') {
              // Find all visitor cards with this IP
              document.querySelectorAll(`.visitor-card`).forEach(card => {
                  const ipElement = card.querySelector('.visitor-ip');
                  if (ipElement && ipElement.textContent.trim() === value) {
                      // Remove blocked badge if it exists
                      const badge = ipElement.querySelector('.blocked-badge');
                      if (badge) {
                          badge.remove();
                      }
                  }
              });
          } else if (type === 'isp') {
              // Find all visitor cards with this ISP
              document.querySelectorAll(`.visitor-card`).forEach(card => {
                  const ispElements = card.querySelectorAll('.visitor-info-value');
                  ispElements.forEach(element => {
                      if (element.textContent.includes(value)) {
                          // Remove blocked badge if it exists
                          const badge = element.querySelector('.blocked-badge');
                          if (badge) {
                              badge.remove();
                          }
                      }
                  });
              });
          }
      }
      
      // Load notification preference from localStorage
      document.addEventListener('DOMContentLoaded', function() {
          const savedPreference = localStorage.getItem('notificationsEnabled');
          if (savedPreference !== null) {
              notificationsEnabled = savedPreference === 'true';
              document.getElementById('notificationStatus').textContent = notificationsEnabled ? 'On' : 'Off';
          }
          
          // Initialize volume from localStorage
          const savedVolume = localStorage.getItem('notificationVolume');
          if (savedVolume !== null) {
              document.getElementById('volumeSlider').value = savedVolume;
          }
          
          // Start the countdown and check for new visitors
          updateCountdown();
          setInterval(checkForNewVisitors, 3000);
          
          // Initialize pagination
          updatePagination();
      });
  </script>
</body>
</html>

