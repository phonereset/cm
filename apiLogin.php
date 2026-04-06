<?php
header('Content-Type: application/json; charset=utf-8');


// ----------


// Support both GET and POST methods
$usr = $_POST['user'] ?? $_GET['user'] ?? '';
$pass = $_POST['pass'] ?? $_GET['pass'] ?? '';

if ($usr === '' || $pass === '') {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required parameters: user and pass'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- Function: Get Redirect URL + Initial Cookies ----------
function getRedirectAndCookie($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 12)',
    ]);

    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);

    // Find redirect URL
    preg_match('/Location:\s*(.*)/i', $header, $match);
    $redirect_url = isset($match[1]) ? trim($match[1]) : null;

    if ($redirect_url && strpos($redirect_url, 'http://') === 0) {
        $redirect_url = preg_replace('/^http:/i', 'https:', $redirect_url);
    }

    // Extract cookies
    preg_match_all('/Set-Cookie:\s*([^=]+)=([^;]+)/i', $header, $matches);
    $cookies = [];
    if (!empty($matches[1])) {
        foreach ($matches[1] as $i => $name) {
            $cookies[] = "$name={$matches[2][$i]}";
        }
    }

    curl_close($ch);
    return [$redirect_url, implode('; ', $cookies)];
}

// ---------- Function: Fetch page with cookie ----------
function fetchPage($url, $cookie) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ["Cookie: $cookie"],
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 12)',
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

// ---------- Function: Extract hidden inputs ----------
function extract_hidden_inputs($html) {
    $doc = new DOMDocument();
    @$doc->loadHTML($html);

    $xpath = new DOMXPath($doc);
    $inputs = $xpath->query('//input[@type="hidden"]');

    $result = [];
    foreach ($inputs as $input) {
        $name = $input->getAttribute('name') ?: $input->getAttribute('id');
        $value = $input->getAttribute('value');

        // APEX "data-for"
        if ($name) {
            $df = $xpath->query('//input[@data-for="' . $name . '"]');
            if ($df->length > 0) {
                $value = $df->item(0)->getAttribute('value');
            }
            $result[$name] = $value;
        }
    }
    return $result;
}

// ------------------------------------------------------------
// STEP 1: Hit initial URL WITH RETRY 3 TIMES
// ------------------------------------------------------------
$initial_url = "https://cdms.police.gov.bd/cdms/f?p=105:998";

$attempt = 0;
$redirect_url = null;
$initial_cookie = null;

while ($attempt < 3 && !$redirect_url) {
    $attempt++;
    list($redirect_url, $initial_cookie) = getRedirectAndCookie($initial_url);

    if ($redirect_url) {
        break;
    }
}

if (!$redirect_url) {
    echo json_encode([
        "status" => "error",
        "message" => "Redirect URL not found.."
    ], JSON_PRETTY_PRINT);
    exit;
}

// ------------------------------------------------------------
// STEP 2: Load redirect page using the initial cookies
// ------------------------------------------------------------
$html = fetchPage($redirect_url, $initial_cookie);

if (!$html) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to load redirect page"
    ], JSON_PRETTY_PRINT);
    exit;
}

// ------------------------------------------------------------
// STEP 3: Extract hidden inputs
// ------------------------------------------------------------
$hidden = extract_hidden_inputs($html);


// ----------------------------------
// NO MORE 1.php — WE USE SAME VALUES
// ----------------------------------
$data1 = [
    "status" => "ok",
    "initial_cookie" => $initial_cookie,
    "redirect_url" => $redirect_url,
    "hidden_inputs" => $hidden
];


// ---------------------- Extract Values -----------------------
$cookie        = $data1["initial_cookie"] ?? '';
$redirect_url  = $data1["redirect_url"] ?? '';
$h             = $data1["hidden_inputs"] ?? [];

$salt          = $h["pSalt"] ?? '';
$p_instance    = $h["p_instance"] ?? '';
$page_id       = $h["p_page_submission_id"] ?? '';
$context       = $h["pContext"] ?? '';
$protected     = $h["pPageItemsProtected"] ?? '';
$p101_text_ck  = $h["P101_TEXT"] ?? '';

// ---------------------- Build POST Data ----------------------
$postData = [
    'salt' => $salt,
    'pageItems' => [
        'itemsToSubmit' => [
            ["n" => "P0_IP", "v" => "104.196.219.10"],
            ["n" => "P0_G_IP", "v" => "104.180.219.10"],
            ["n" => "P0_CURR_URL", "v" => "CDMS"],
            ["n" => "P101_TEXT", "v" => '<span style="color:red;">Please type this manually. Pasting is disabled.</span>', "ck" => $p101_text_ck],
            ["n" => "P101_USERNAME", "v" => $usr],
            ["n" => "P101_TOTAL_LOGIN_USER", "v" => ""],
            ["n" => "P101_PASSWORD", "v" => $pass],
            ["n" => "P101_CLINT_IP", "v" => ""],
            ["n" => "P101_OTP", "v" => ""],
            ["n" => "P101_OTP_FLAG", "v" => "N"],
            ["n" => "P101_PLATFORM", "v" => "Android"],
            ["n" => "P101_PLATFORM_VERSION", "v" => ""],
            ["n" => "P101_ARCHITECTURE", "v" => ""],
            ["n" => "P101_MODEL", "v" => ""],
            ["n" => "P101_BROWSER_VERSION", "v" => ""],
            ["n" => "P101_USER_AGENT", "v" => "Mozilla/5.0 (Linux; Android 15; 23129RAA4G Build/AQ3A.240829.003) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.7444.102 Mobile Safari/537.36"],
            ["n" => "P101_SCREEN_SIZE", "v" => "393 x 873"],
            ["n" => "P101_PIXEL_RATIO", "v" => "2.75"],
            ["n" => "P101_COLOR_DEPTH", "v" => "24"],
            ["n" => "P101_CPU_CORES", "v" => "8"],
            ["n" => "P101_RAM_GB", "v" => "4"],
            ["n" => "P101_TOUCH_POINTS", "v" => "5"],
            ["n" => "P101_TIMEZONE", "v" => "Asia/Dhaka"],
            ["n" => "P101_WEBGL_VENDOR", "v" => "Qualcomm"],
            ["n" => "P101_WEBGL_RENDERER", "v" => "Adreno (TM) 610"]
        ],
        'protected' => $protected,
        'rowVersion' => '',
        'formRegionChecksums' => []
    ]
];

$postFields = http_build_query([
    'p_json' => json_encode($postData),
    'p_flow_id' => 105,
    'p_flow_step_id' => 101,
    'p_instance' => $p_instance,
    'p_page_submission_id' => $page_id,
    'p_request' => 'P101_LOGIN',
    'p_reload_on_submit' => 'A'
]);

// ---------------------- Helper Functions ----------------------
function getFinalResponse($url, $cookie) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Cookie: $cookie",
            "User-Agent: Mozilla/5.0 (Linux; Android 15; 23129RAA4G Build/AQ3A.240829.003) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.7444.102 Mobile Safari/537.36"
        ],
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

// duplicated function renamed (per your request) -------
function extract_hidden_inputs2($html) {
    $doc = new DOMDocument();
    @$doc->loadHTML($html);
    $xpath = new DOMXPath($doc);
    $result = [];
    $hiddenInputs = $xpath->query('//input[@type="hidden"]');
    foreach ($hiddenInputs as $input) {
        $name = $input->getAttribute('name') ?: $input->getAttribute('id');
        $value = $input->getAttribute('value');
        if ($name) {
            $dataFor = $xpath->query('//input[@data-for="' . $name . '"]');
            if ($dataFor->length > 0) {
                $value = $dataFor->item(0)->getAttribute('value');
            }
            $result[$name] = $value;
        }
    }
    return $result;
}

// ---------------------- cURL Request ------------------------
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://cdms.police.gov.bd/cdms/wwv_flow.accept?p_context=105:101:$p_instance",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_COOKIE => "$cookie; LOGIN_USERNAME_COOKIE=9219223955",
    CURLOPT_HTTPHEADER => [
        'User-Agent: Mozilla/5.0 (Linux; Android 15; 23129RAA4G Build/AQ3A.240829.003) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.7444.102 Mobile Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*',
        'Content-Type: application/x-www-form-urlencoded',
        'Cache-Control: max-age=0'
    ],
    CURLOPT_HEADER => true,
]);

$response = curl_exec($curl);
if (curl_errno($curl)) {
    echo json_encode(['status' => 'error', 'message' => curl_error($curl)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    curl_close($curl);
    exit;
}
$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $header_size);
$body = substr($response, $header_size);
curl_close($curl);

// ---------------------- Parse Cookies ------------------------
preg_match_all('/Set-Cookie:\s*([^;]+);/i', $headers, $cookieMatches);
$cookies = $cookieMatches[1] ?? [];
$cookieFull = implode('; ', $cookies);

// ---------------------- Fetch Protected Page ------------------------
$secondUrl = "https://cdms.police.gov.bd/cdms/f?p=105:600:" . $p_instance . ":::600::";
$finalHtml = getFinalResponse($secondUrl, $cookieFull);
if (!$finalHtml || trim($finalHtml) === '') {
    echo json_encode(['status' => 'error', 'message' => 'Login failed or session invalid'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------- Prepare Data ------------------------
$hidden_inputs = extract_hidden_inputs2($finalHtml);
$output = array_merge($hidden_inputs, [
    'cookies' => $cookieFull,
    'bpId' => $usr
]);

// ---------------------- Fetch expiredIn via userCheck ------------------------
$expiredIn = null;
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
$basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$userCheckUrl = $protocol . $host . $basePath . '/userCheck.php?user=' . urlencode($usr);

$chExpired = curl_init();
curl_setopt_array($chExpired, [
    CURLOPT_URL => $userCheckUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$expiredResp = curl_exec($chExpired);
if (!curl_errno($chExpired)) {
    $expiredJson = json_decode($expiredResp, true);
    if (is_array($expiredJson) && ($expiredJson['success'] ?? false)) {
        $expiredIn = $expiredJson['expiredIn'] ?? null;
    }
}
curl_close($chExpired);

// Update accountsx25.json with latest expiredIn for this user
if ($expiredIn !== null) {
    $accountsFile = __DIR__ . '/accountsx25.json';
    if (file_exists($accountsFile)) {
        $accData = json_decode(file_get_contents($accountsFile), true);
        if (is_array($accData)) {
            foreach ($accData as &$acc) {
                if (($acc['username'] ?? '') === $usr) {
                    $acc['expiredIn'] = $expiredIn;
                }
            }
            file_put_contents($accountsFile, json_encode($accData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
}

// ---------------------- Save to authx25.json ------------------------
$authFile = __DIR__ . '/authx25.json';

$result = file_put_contents($authFile, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);

// ---------------------- Return JSON response ------------------------
echo json_encode([
    "status" => "success",
    "message" => "login successfully",
    "expiredIn" => $expiredIn,
    "authFile" => $authFile,
    "data" => $output
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;

