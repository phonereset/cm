<?php
// Professional Account Management Dashboard with Advanced UI/UX
// Load accountsx25.json
$accountsFile = __DIR__ . '/accountsx25.json';
$accounts = [];

if (file_exists($accountsFile)) {
    $json = file_get_contents($accountsFile);
    $data = json_decode($json, true);
    if (is_array($data)) {
        $accounts = $data;
    }
}

// Load authx25.json for display
$authFile = __DIR__ . '/authx25.json';
$authData = null;
$authRaw = null;
if (file_exists($authFile) && filesize($authFile) > 0) {
    $authRaw = file_get_contents($authFile);
    $authData = json_decode($authRaw, true);
    if (is_array($authData)) {
        $authRaw = json_encode($authData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

// Statistics
$totalAccounts = count($accounts);
$activeAccounts = array_filter($accounts, fn($acc) => ($acc['success'] ?? false));
$inactiveAccounts = $totalAccounts - count($activeAccounts);
$limitedAccounts = array_filter($accounts, fn($acc) => ($acc['limitExhausted'] ?? false));
$availableAccounts = array_filter($accounts, fn($acc) => ($acc['success'] ?? false) && !($acc['limitExhausted'] ?? false));

// Handle actions
$notification = null;
$notificationType = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    // Toggle limitExhausted
    if ($action === 'toggle_limit') {
        $idxRaw = $_POST['index'] ?? null;
        $idx = is_numeric($idxRaw) ? (int)$idxRaw : -1;

        if ($idx < 0 || !isset($accounts[$idx])) {
            $notification = 'Invalid account index';
            $notificationType = 'error';
        } else {
            $current = isset($accounts[$idx]['limitExhausted']) ? (bool)$accounts[$idx]['limitExhausted'] : false;
            $newValue = !$current;
            $username = $accounts[$idx]['username'] ?? '';

            $accounts[$idx]['limitExhausted'] = $newValue;
            file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $notification = "Limit toggled " . ($newValue ? 'LIMITED' : 'AVAILABLE') . " for {$username}";
            $notificationType = 'success';

            // Refresh stats
            $limitedAccounts = array_filter($accounts, fn($acc) => ($acc['limitExhausted'] ?? false));
            $availableAccounts = array_filter($accounts, fn($acc) => ($acc['success'] ?? false) && !($acc['limitExhausted'] ?? false));
        }
    }

    // Toggle status (active/inactive)
    if ($action === 'toggle_status') {
        $idxRaw = $_POST['index'] ?? null;
        $idx = is_numeric($idxRaw) ? (int)$idxRaw : -1;

        if ($idx < 0 || !isset($accounts[$idx])) {
            $notification = 'Invalid account index';
            $notificationType = 'error';
        } else {
            $current = isset($accounts[$idx]['success']) ? (bool)$accounts[$idx]['success'] : false;
            $newValue = !$current;
            $username = $accounts[$idx]['username'] ?? '';

            $accounts[$idx]['success'] = $newValue;
            // If making inactive, also set as limited
            if (!$newValue) {
                $accounts[$idx]['limitExhausted'] = true;
            }
            file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $notification = "Status toggled " . ($newValue ? 'ACTIVE' : 'INACTIVE') . " for {$username}";
            $notificationType = 'success';

            // Refresh stats
            $activeAccounts = array_filter($accounts, fn($acc) => ($acc['success'] ?? false));
            $inactiveAccounts = $totalAccounts - count($activeAccounts);
            $availableAccounts = array_filter($accounts, fn($acc) => ($acc['success'] ?? false) && !($acc['limitExhausted'] ?? false));
        }
    }

    // Check user expiry
    if ($action === 'check_user') {
        $idxRaw = $_POST['index'] ?? null;
        $idx = is_numeric($idxRaw) ? (int)$idxRaw : -1;

        if ($idx < 0 || !isset($accounts[$idx])) {
            $notification = 'Invalid account index';
            $notificationType = 'error';
        } else {
            $username = $accounts[$idx]['username'] ?? '';
            if ($username === '') {
                $notification = 'Username missing';
                $notificationType = 'error';
            } else {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/');
                $apiUrl = $protocol . $host . $basePath . '/userCheck.php?user=' . urlencode($username);

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL            => $apiUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_TIMEOUT        => 10,
                ]);
                
                $resp = curl_exec($ch);
                if (curl_errno($ch)) {
                    $notification = 'Check failed: ' . curl_error($ch);
                    $notificationType = 'error';
                    curl_close($ch);
                } else {
                    curl_close($ch);
                    $json = json_decode($resp, true);
                    if (is_array($json) && ($json['success'] ?? false)) {
                        $accounts[$idx]['expiredIn'] = $json['expiredIn'] ?? null;
                        file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        $notification = $json['message'] ?? "Checked {$username} successfully";
                        $notificationType = 'success';
                    } else {
                        $notification = $json['message'] ?? 'Check failed';
                        $notificationType = 'warning';
                    }
                }
            }
        }
    }

    // Login action
    if ($action === 'login') {
        $username = $_POST['user'] ?? '';
        $password = $_POST['pass'] ?? '';

        if ($username === '' || $password === '') {
            $notification = 'Missing username or password';
            $notificationType = 'error';
        } else {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/');
            $apiUrl = $protocol . $host . $basePath . '/apiLogin.php';

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query(['user' => $username, 'pass' => $password]),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT        => 15,
            ]);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                $notification = 'Login error: ' . curl_error($ch);
                $notificationType = 'error';
                curl_close($ch);
            } else {
                curl_close($ch);
                $json = json_decode($response, true);
                
                if (is_array($json) && strtolower($json['status'] ?? '') === 'success') {
                    $notification = "Logged in as {$username} successfully";
                    $notificationType = 'success';
                    
                    // Update account success status
                    foreach ($accounts as &$acc) {
                        if (($acc['username'] ?? '') === $username) {
                            $acc['success'] = true;
                            $acc['lastLogin'] = date('Y-m-d H:i:s');
                            break;
                        }
                    }
                    file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    
                    // Refresh auth data
                    $authRaw = file_get_contents($authFile);
                    $authData = json_decode($authRaw, true);
                    $authRaw = json_encode($authData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                } else {
                    $notification = $json['message'] ?? 'Login failed';
                    $notificationType = 'error';
                }
            }
        }
    }
    
    // Bulk actions
    if ($action === 'bulk_toggle_limits') {
        $toggleTo = $_POST['toggle_state'] ?? 'false';
        $newState = $toggleTo === 'true';

        foreach ($accounts as &$acc) {
            $acc['limitExhausted'] = $newState;
        }

        file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $notification = "All limits toggled " . ($newState ? 'ON' : 'OFF');
        $notificationType = 'success';

        // Refresh stats
        $limitedAccounts = array_filter($accounts, fn($acc) => ($acc['limitExhausted'] ?? false));
        $availableAccounts = array_filter($accounts, fn($acc) => ($acc['success'] ?? false) && !($acc['limitExhausted'] ?? false));
    }

    // Bulk toggle status
    if ($action === 'bulk_toggle_status') {
        $toggleTo = $_POST['toggle_state'] ?? 'false';
        $newState = $toggleTo === 'true';

        foreach ($accounts as &$acc) {
            $acc['success'] = $newState;
            // If making inactive, also set as limited
            if (!$newState) {
                $acc['limitExhausted'] = true;
            }
        }

        file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $notification = "All accounts toggled " . ($newState ? 'ACTIVE' : 'INACTIVE');
        $notificationType = 'success';

        // Refresh stats
        $activeAccounts = array_filter($accounts, fn($acc) => ($acc['success'] ?? false));
        $inactiveAccounts = $totalAccounts - count($activeAccounts);
        $availableAccounts = array_filter($accounts, fn($acc) => ($acc['success'] ?? false) && !($acc['limitExhausted'] ?? false));
    }

    // Bulk check users expiry
    if ($action === 'bulk_check_users') {
        $checked = 0;
        $errors = 0;

        foreach ($accounts as $idx => &$acc) {
            $username = $acc['username'] ?? '';
            if ($username === '') continue;

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/');
            $apiUrl = $protocol . $host . $basePath . '/userCheck.php?user=' . urlencode($username);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT        => 10,
            ]);

            $resp = curl_exec($ch);
            if (!curl_errno($ch)) {
                $json = json_decode($resp, true);
                if (is_array($json) && ($json['success'] ?? false)) {
                    $acc['expiredIn'] = $json['expiredIn'] ?? null;
                    $checked++;
                } else {
                    $errors++;
                }
            } else {
                $errors++;
            }
            curl_close($ch);
        }

        file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $notification = "Checked {$checked} accounts" . ($errors > 0 ? ", {$errors} failed" : "");
        $notificationType = $errors > 0 ? 'warning' : 'success';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <title>Account Manager | CDMS Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #0f172a;
            --darker: #020617;
            --light: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --radius-sm: 0.375rem;
            --radius: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, var(--darker) 0%, var(--gray-900) 100%);
            color: var(--gray-200);
            min-height: 100vh;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .app-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 12px 12px 20px;
            min-height: 100vh;
        }

        /* Header */
        .header {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        @media (min-width: 768px) {
            .header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .brand-text h1 {
            font-size: 24px;
            font-weight: 700;
            color: white;
            line-height: 1.2;
        }

        .brand-text p {
            font-size: 13px;
            color: var(--gray-400);
            margin-top: 2px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 0 auto 24px;
            max-width: 1100px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-lg);
            padding: 20px;
            backdrop-filter: blur(10px);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card i {
            font-size: 24px;
            margin-bottom: 12px;
            opacity: 0.8;
        }

        .stat-card.total i { color: var(--primary-light); }
        .stat-card.active i { color: var(--secondary); }
        .stat-card.inactive i { color: var(--warning); }
        .stat-card.limited i { color: var(--danger); }
        .stat-card.available i { color: var(--secondary); }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: white;
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--gray-400);
        }

        /* Main Layout */
        .main-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            max-width: 1100px;
            margin: 0 auto;
        }

        @media (min-width: 1024px) {
            .main-layout {
                grid-template-columns: 2fr 1fr;
            }
        }

        /* Card Styling */
        .card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-lg);
            padding: 24px;
            backdrop-filter: blur(10px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i {
            color: var(--primary-light);
        }

        /* Table */
        .table-container {
            overflow-x: auto;
            border-radius: var(--radius);
            margin: -4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        thead {
            background: rgba(255, 255, 255, 0.03);
        }

        th {
            padding: 16px 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            white-space: nowrap;
        }

        td {
            padding: 16px 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            font-size: 14px;
        }

        tbody tr {
            transition: background-color 0.2s ease;
        }

        tbody tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            line-height: 1;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .badge-error {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.15);
            color: #fcd34d;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .badge-info {
            background: rgba(59, 130, 246, 0.15);
            color: #93c5fd;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .badge-muted {
            background: rgba(148, 163, 184, 0.15);
            color: var(--gray-400);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--secondary) 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }

        .btn-success:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-outline {
            background: transparent;
            color: var(--gray-300);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            justify-content: center;
        }

        /* Toggle Switch */
        .toggle-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toggle-label {
            font-size: 12px;
            color: var(--gray-400);
        }

        .toggle-switch {
            position: relative;
            width: 44px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 34px;
            transition: .4s;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            border-radius: 50%;
            transition: .4s;
        }

        input:checked + .toggle-slider {
            background: linear-gradient(135deg, var(--secondary) 0%, #059669 100%);
            border-color: var(--secondary);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(20px);
        }

        /* Action Buttons */
        .action-group {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        /* Auth Preview */
        .auth-preview {
            margin-top: 16px;
            width: 100%;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }

        .json-viewer {
            background: rgba(0, 0, 0, 0.2);
            border-radius: var(--radius);
            padding: 16px;
            max-height: 300px;
            overflow-y: auto;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-word;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.5;
            color: var(--gray-300);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .json-key { color: #fca5a5; }
        .json-string { color: #86efac; }
        .json-number { color: #93c5fd; }
        .json-boolean { color: #c4b5fd; }
        .json-null { color: var(--gray-500); }

        /* Notification */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .alert {
            padding: 16px;
            border-radius: var(--radius);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-lg);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #a7f3d0;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fecaca;
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fde68a;
        }

        .alert-info {
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #bfdbfe;
        }

        .alert i {
            font-size: 18px;
            flex-shrink: 0;
        }

        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .app-container {
                padding: 12px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stat-card {
                padding: 16px;
            }
            
            .stat-value {
                font-size: 24px;
            }
            
            .card {
                padding: 16px;
            }
            
            th, td {
                padding: 12px 8px;
                font-size: 13px;
            }
            
            .action-group {
                flex-direction: column;
                width: 100%;
            }
            
            .action-group .btn {
                width: 100%;
                justify-content: center;
            }
            
            .notification {
                left: 12px;
                right: 12px;
                top: 12px;
                max-width: none;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .brand-text h1 {
                font-size: 20px;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .bulk-actions {
                width: 100%;
            }
            
            .bulk-actions form {
                width: 100%;
            }
            
            .bulk-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: var(--gray-400);
        }

        /* Utility Classes */
        .text-muted { color: var(--gray-500); font-size: 12px; }
        .text-center { text-align: center; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .d-flex { display: flex; }
        .align-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .w-100 { width: 100%; }
    </style>
    <script>
        // Auto-dismiss notifications after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const notifications = document.querySelectorAll('.alert');
            notifications.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateX(100%)';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
            
            // Add loading indicators to buttons
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.classList.contains('no-loading')) {
                        const originalHTML = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<span class="loading"></span>';
                        submitBtn.disabled = true;
                        
                        setTimeout(() => {
                            submitBtn.innerHTML = originalHTML;
                            submitBtn.disabled = false;
                        }, 3000);
                    }
                });
            });
        });
    </script>
</head>
<body>
    <?php if ($notification): ?>
    <div class="notification">
        <div class="alert alert-<?php echo $notificationType; ?>">
            <i class="fas fa-<?php 
                echo $notificationType === 'success' ? 'check-circle' : 
                     ($notificationType === 'error' ? 'exclamation-circle' : 
                     ($notificationType === 'warning' ? 'exclamation-triangle' : 'info-circle')); 
            ?>"></i>
            <div><?php echo htmlspecialchars($notification, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="app-container">
        <!-- Header -->
        <header class="header">
            <div class="brand">
                <div class="brand-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="brand-text">
                    <h1>Account Manager</h1>
                    <p>Manage CDMS accounts & authentication sessions</p>
                </div>
            </div>
            <div class="d-flex gap-3 align-center">
                <div class="text-muted">
                    <i class="fas fa-sync-alt"></i> Auto-refresh: 60s
                </div>
                <button class="btn btn-outline btn-sm" onclick="location.reload()">
                    <i class="fas fa-redo"></i> Refresh
                </button>
            </div>
        </header>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card total">
                <i class="fas fa-users"></i>
                <div class="stat-value"><?php echo $totalAccounts; ?></div>
                <div class="stat-label">Total Accounts</div>
            </div>
            <div class="stat-card active">
                <i class="fas fa-check-circle"></i>
                <div class="stat-value"><?php echo count($activeAccounts); ?></div>
                <div class="stat-label">Active Accounts</div>
            </div>
            <div class="stat-card inactive">
                <i class="fas fa-times-circle"></i>
                <div class="stat-value"><?php echo $inactiveAccounts; ?></div>
                <div class="stat-label">Inactive Accounts</div>
            </div>
            <div class="stat-card limited">
                <i class="fas fa-ban"></i>
                <div class="stat-value"><?php echo count($limitedAccounts); ?></div>
                <div class="stat-label">Limit Exhausted</div>
            </div>
            <div class="stat-card available">
                <i class="fas fa-bolt"></i>
                <div class="stat-value"><?php echo count($availableAccounts); ?></div>
                <div class="stat-label">Available</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-layout">
            <!-- Accounts Table -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-list"></i> Accounts
                    </h2>
                    <div class="bulk-actions d-flex gap-2 flex-wrap">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="bulk_check_users" />
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-search"></i> Check All
                            </button>
                        </form>
                        <form method="post" action="">
                            <input type="hidden" name="action" value="bulk_toggle_status" />
                            <input type="hidden" name="toggle_state" value="true" />
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fas fa-play"></i> Activate All
                            </button>
                        </form>
                        <form method="post" action="">
                            <input type="hidden" name="action" value="bulk_toggle_status" />
                            <input type="hidden" name="toggle_state" value="false" />
                            <button type="submit" class="btn btn-outline btn-sm">
                                <i class="fas fa-pause"></i> Deactivate All
                            </button>
                        </form>
                        <form method="post" action="">
                            <input type="hidden" name="action" value="bulk_toggle_limits" />
                            <input type="hidden" name="toggle_state" value="false" />
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fas fa-check"></i> Enable All
                            </button>
                        </form>
                        <form method="post" action="">
                            <input type="hidden" name="action" value="bulk_toggle_limits" />
                            <input type="hidden" name="toggle_state" value="true" />
                            <button type="submit" class="btn btn-outline btn-sm">
                                <i class="fas fa-ban"></i> Limit All
                            </button>
                        </form>
                    </div>
                </div>

                <?php if (empty($accounts)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Accounts Found</h3>
                        <p>Add accounts to <code>accountsx25.json</code> to get started</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Username</th>
                                    <th>Password</th>
                                     <th>Status</th>
                                     <th>Expires In</th>
                                     <th>Limit</th>
                                     <th>Used</th>
                                     <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $i => $acc): ?>
                                    <?php
                                    $username = $acc['username'] ?? '';
                                    $password = $acc['password'] ?? '';
                                    $success  = $acc['success'] ?? false;
                                    $expiredIn = $acc['expiredIn'] ?? null;
                                    $limitExhausted = $acc['limitExhausted'] ?? false;
                                     $usedCount = isset($acc['used']) ? (int)$acc['used'] : 0;
                                    $lastLogin = $acc['lastLogin'] ?? null;
                                    ?>
                                    <tr>
                                        <td class="text-muted"><?php echo $i + 1; ?></td>
                                        <td>
                                            <div class="d-flex align-center gap-2">
                                                <i class="fas fa-user-circle text-muted"></i>
                                                <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                            <?php if ($lastLogin): ?>
                                                <div class="text-muted" style="font-size: 11px; margin-top: 2px;">
                                                    <i class="far fa-clock"></i> <?php echo $lastLogin; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                         <td>
                                             <div class="d-flex align-center gap-2">
                                                 <i class="fas fa-key text-muted"></i>
                                                 <span class="text-muted password-mask"
                                                       data-pass="<?php echo htmlspecialchars($password, ENT_QUOTES, 'UTF-8'); ?>"
                                                       data-visible="0">••••••••</span>
                                                 <button type="button"
                                                         class="btn btn-icon btn-sm btn-outline"
                                                         onclick="togglePassword(this)"
                                                         aria-label="Show password">
                                                     <i class="fas fa-eye"></i>
                                                 </button>
                                             </div>
                                         </td>
                                        <td>
                                            <form method="post" action="" class="toggle-container">
                                                <input type="hidden" name="action" value="toggle_status" />
                                                <input type="hidden" name="index" value="<?php echo $i; ?>" />
                                                <label class="toggle-switch">
                                                    <input type="checkbox"
                                                           <?php echo $success ? 'checked' : ''; ?>
                                                           onchange="this.closest('form').submit()">
                                                    <span class="toggle-slider"></span>
                                                </label>
                                                <span class="toggle-label">
                                                    <?php echo $success ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </form>
                                        </td>
                                        <td>
                                            <?php if ($expiredIn !== null): ?>
                                                <span class="badge badge-<?php echo $expiredIn <= 0 ? 'warning' : 'info'; ?>">
                                                    <i class="fas fa-clock"></i> 
                                                    <?php echo htmlspecialchars((string)$expiredIn, ENT_QUOTES, 'UTF-8'); ?>d
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$success): ?>
                                                <span class="badge badge-error">
                                                    <i class="fas fa-ban"></i> Inactve
                                                </span>
                                            <?php else: ?>
                                                <form method="post" action="" class="toggle-container">
                                                    <input type="hidden" name="action" value="toggle_limit" />
                                                    <input type="hidden" name="index" value="<?php echo $i; ?>" />
                                                    <label class="toggle-switch">
                                                        <input type="checkbox"
                                                               <?php echo !$limitExhausted ? 'checked' : ''; ?>
                                                               onchange="this.closest('form').submit()">
                                                        <span class="toggle-slider"></span>
                                                    </label>
                                                    <span class="toggle-label">
                                                        <?php echo $limitExhausted ? 'Limited' : 'Available'; ?>
                                                    </span>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                         <td>
                                             <span class="badge badge-muted">
                                                 <i class="fas fa-list-ol"></i>
                                                 <?php echo $usedCount; ?>
                                             </span>
                                         </td>
                                        <td>
                                            <div class="action-group">
                                                <form method="post" action="">
                                                    <input type="hidden" name="action" value="check_user" />
                                                    <input type="hidden" name="index" value="<?php echo $i; ?>" />
                                                    <button type="submit" class="btn btn-outline btn-sm">
                                                        <i class="fas fa-search"></i> Check
                                                    </button>
                                                </form>
                                                <form method="post" action="">
                                                    <input type="hidden" name="action" value="login" />
                                                    <input type="hidden" name="user" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" />
                                                    <input type="hidden" name="pass" value="<?php echo htmlspecialchars($password, ENT_QUOTES, 'UTF-8'); ?>" />
                                                    <button type="submit" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-sign-in-alt"></i> Login
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Auth Session Panel -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-shield-alt"></i> Current Session
                    </h2>
                    <?php if ($authData): ?>
                        <span class="badge badge-success">
                            <i class="fas fa-check-circle"></i> Active
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!$authData): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <h3>No Active Session</h3>
                        <p>Login with an account to start a session</p>
                    </div>
                <?php else: ?>
                     <div class="mb-3">
                        <div class="d-flex justify-between align-center mb-2">
                            <div>
                                <strong>Logged in as:</strong>
                                <span class="badge badge-info ml-2">
                                    <i class="fas fa-user"></i> 
                                     <?php
                                     $who = $authData['username'] ?? $authData['bpId'] ?? 'Unknown';
                                     echo htmlspecialchars($who, ENT_QUOTES, 'UTF-8');
                                     ?>
                                </span>
                            </div>
                            <?php if (isset($authData['timestamp'])): ?>
                                <div class="text-muted">
                                    <i class="far fa-clock"></i> 
                                    <?php echo date('Y-m-d H:i:s', $authData['timestamp']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (isset($authData['expiresIn'])): ?>
                            <div class="d-flex align-center gap-2 mb-3">
                                <div class="text-muted">Session expires in:</div>
                                <div class="badge badge-<?php echo ($authData['expiresIn'] ?? 0) < 3600 ? 'warning' : 'success'; ?>">
                                    <?php
                                    $expiresIn = $authData['expiresIn'] ?? 0;
                                    $hours = floor($expiresIn / 3600);
                                    $minutes = floor(($expiresIn % 3600) / 60);
                                    echo "{$hours}h {$minutes}m";
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="auth-preview">
                        <div class="text-muted mb-2">Session Data:</div>
                        <div class="json-viewer" id="json-viewer">
                            <!-- JSON will be formatted by JavaScript -->
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mt-4">
                    <div class="text-muted mb-2">Quick Actions:</div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline btn-sm w-100" onclick="location.reload()">
                            <i class="fas fa-sync"></i> Refresh Session
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Format JSON with syntax highlighting
        function formatJSON(json) {
            if (!json) return '';
            
            json = json.replace(/&/g, '&amp;')
                      .replace(/</g, '&lt;')
                      .replace(/>/g, '&gt;');
            
            return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, 
                function(match) {
                    let cls = 'json-number';
                    if (/^"/.test(match)) {
                        if (/:$/.test(match)) {
                            cls = 'json-key';
                        } else {
                            cls = 'json-string';
                        }
                    } else if (/true|false/.test(match)) {
                        cls = 'json-boolean';
                    } else if (/null/.test(match)) {
                        cls = 'json-null';
                    }
                    return '<span class="' + cls + '">' + match + '</span>';
                });
        }

        // Display formatted JSON
        <?php if ($authData): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const viewer = document.getElementById('json-viewer');
            if (viewer) {
                const authObj = <?php echo json_encode($authData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                const pretty = JSON.stringify(authObj, null, 2);
                viewer.innerHTML = formatJSON(pretty);
            }
        });
        <?php endif; ?>

        // Toggle show/hide password in table
        function togglePassword(btn) {
            const mask = btn.previousElementSibling;
            if (!mask) return;
            const isVisible = mask.getAttribute('data-visible') === '1';
            if (isVisible) {
                mask.textContent = '••••••••';
                mask.setAttribute('data-visible', '0');
                btn.querySelector('i').className = 'fas fa-eye';
                btn.setAttribute('aria-label', 'Show password');
            } else {
                mask.textContent = mask.getAttribute('data-pass') || '';
                mask.setAttribute('data-visible', '1');
                btn.querySelector('i').className = 'fas fa-eye-slash';
                btn.setAttribute('aria-label', 'Hide password');
            }
        }

        // Download authx25.json
        function downloadAuth() {
            const data = `<?php echo addslashes($authRaw); ?>`;
            const blob = new Blob([data], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'auth-session-' + new Date().toISOString().split('T')[0] + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // Auto-refresh every 60 seconds
        setTimeout(() => {
            location.reload();
        }, 60000);
    </script>
</body>
</html>