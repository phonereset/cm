<?php
header('Content-Type: application/json');

function getRedirectAndCookie($url)
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 12; V2111 Build/SP1A.210812.003_NONFC) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.7151.115 Mobile Safari/537.36',
    ]);

    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);

    preg_match('/Location:\s*(.*)/i', $header, $location_match);
    $redirect_url = isset($location_match[1]) ? trim($location_match[1]) : null;

    if ($redirect_url && strpos($redirect_url, 'http://') === 0) {
        $redirect_url = preg_replace('/^http:/i', 'https:', $redirect_url);
    }

    preg_match_all('/Set-Cookie:\s*([^=]+)=([^;]+)/i', $header, $matches);
    $cookies = array_map(function ($name, $value) {
        return "$name=$value";
    }, $matches[1], $matches[2]);

    curl_close($ch);

    return [$redirect_url, implode('; ', $cookies)];
}

function getFinalResponse($url, $cookie)
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 12; V2111 Build/SP1A.210812.003_NONFC) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.7151.115 Mobile Safari/537.36',
        CURLOPT_HTTPHEADER => [
            "Cookie: $cookie"
        ]
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function extract_hidden_inputs($html)
{
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

function extract_ajax_identifier($html)
{
    // Extract all ajaxIdentifier occurrences from the HTML/JavaScript content
    // Looking for pattern: "ajaxIdentifier":"VALUE" or ajaxIdentifier:"VALUE"
    // Handle both quoted key and unquoted key, and handle escaped quotes in value
    $results = [];
    if (preg_match_all('/["\']?ajaxIdentifier["\']?\s*:\s*["\']([^"\']+)["\']/', $html, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $index => $match) {
            $ajaxIdentifier = $matches[1][$index][0];
            $position = $match[1];
            
            // Convert \u002F to /
            $ajaxIdentifier = str_replace('\u002F', '/', $ajaxIdentifier);
            
            // Calculate line number from position
            $before_match = substr($html, 0, $position);
            $line_number = substr_count($before_match, "\n") + 1;
            
            $results[] = [
                'value' => $ajaxIdentifier,
                'line_number' => $line_number,
                'position' => $position
            ];
        }
    }
    return $results;
}

function checkUsername($username, $tokens, $cookie)
{
    // Build the context from pContext
    $context = $tokens['pContext'];
    $url = "https://cdms.police.gov.bd/cdms/wwv_flow.ajax?p_context=$context";
    
    // Build p_request
    $p_request = "PLUGIN=" . $tokens['ajaxIdentifier'];
    
    // Build p_json
    $p_json = [
        'pageItems' => [
            'itemsToSubmit' => [
                ['n' => 'P101_USERNAME', 'v' => $username]
            ],
            'protected' => $tokens['pPageItemsProtected'],
            'rowVersion' => $tokens['pPageItemsRowVersion'],
            'formRegionChecksums' => []
        ],
        'salt' => $tokens['pSalt']
    ];
    
    $post_data = [
        'p_flow_id' => $tokens['p_flow_id'],
        'p_flow_step_id' => $tokens['p_flow_step_id'],
        'p_instance' => $tokens['p_instance'],
        'p_debug' => '',
        'p_request' => $p_request,
        'p_json' => json_encode($p_json)
    ];
    
    $ch = curl_init($url);
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post_data),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json, text/javascript, */*; q=0.01',
            'Accept-Language: en-US,en;q=0.9,bn;q=0.8,ha;q=0.7',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With: XMLHttpRequest',
            'Origin: https://cdms.police.gov.bd',
            'Referer: https://cdms.police.gov.bd/cdms/f?p=105:LOGIN:' . $tokens['p_instance'],
            "Cookie: $cookie"
        ]
    ]);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($err) {
        return [
            'success' => false,
            'username' => $username,
            'error' => $err,
            'http_code' => $http_code
        ];
    }
    
    // Try to decode as JSON
    $jsonResponse = json_decode($response, true);
    
    if ($jsonResponse !== null) {
        // Extract message from response
        $message = '';
        $usernameExists = false;
        $expiredIn = null;
        
        // Check for 'values' array (username exists)
        if (isset($jsonResponse['values']) && is_array($jsonResponse['values']) && !empty($jsonResponse['values'])) {
            $message = implode(' ', $jsonResponse['values']);
            $usernameExists = true; // If we get values, username exists
            
            // Check if password already expired
            if (stripos($message, 'YOUR PASSWORD EXPIRED') !== false && stripos($message, 'IN') === false) {
                $expiredIn = "0"; // Password already expired
            } elseif (preg_match('/(\d+)\s+DAY/i', $message, $matches)) {
                // Extract expiration days from message
                // Pattern: "YOUR PASSWORD WILL EXPIRED IN 6 DAYS" or similar
                $expiredIn = $matches[1]; // Keep as string
            }
        } elseif (isset($jsonResponse['error'])) {
            $message = $jsonResponse['error'];
        } elseif (isset($jsonResponse['item']) && is_array($jsonResponse['item']) && !empty($jsonResponse['item'])) {
            // Alternative response format
            if (isset($jsonResponse['item'][0]['value'])) {
                $message = $jsonResponse['item'][0]['value'];
                $usernameExists = true;
            }
        }
        
        // Build response
        $responseData = [
            'success' => true,
            'username' => $username,
            'username_exists' => $usernameExists,
            'message' => $message
        ];
        
        // Add expiredIn if found (or if password expired)
        if ($expiredIn !== null) {
            $responseData['expiredIn'] = $expiredIn;
        }
        
        return $responseData;
    } else {
        // Not JSON or invalid JSON
        return [
            'success' => false,
            'username' => $username,
            'http_code' => $http_code,
            'raw_response' => substr($response, 0, 500),
            'error' => 'Response is not valid JSON'
        ];
    }
}

$initial_url = 'https://cdms.police.gov.bd/cdms/f?p=105:998';
list($redirect_url, $cookie) = getRedirectAndCookie($initial_url);

if (!$redirect_url) {
    echo json_encode(['error' => 'Redirect URL not found'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$final_response = getFinalResponse($redirect_url, $cookie);

if (empty($final_response) || trim($final_response) === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Empty response from server - login may have failed'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$hidden_inputs = extract_hidden_inputs($final_response);
$ajax_identifiers = extract_ajax_identifier($final_response);

$output = array_merge($hidden_inputs, [
    'cookies' => $cookie
]);

if (!empty($ajax_identifiers)) {
    // Get only the last ajaxIdentifier
    $last_ajax_identifier = end($ajax_identifiers);
    $output['ajaxIdentifier'] = $last_ajax_identifier['value'];
    $output['ajaxIdentifier_line_number'] = $last_ajax_identifier['line_number'];
    $output['ajaxIdentifier_position'] = $last_ajax_identifier['position'];
}

// Check username if provided (supports both 'user' and 'username' parameters)
$username = isset($_GET['user']) ? $_GET['user'] : (isset($_POST['user']) ? $_POST['user'] : (isset($_GET['username']) ? $_GET['username'] : (isset($_POST['username']) ? $_POST['username'] : null)));

if ($username) {
    $username_check = checkUsername($username, $output, $cookie);
    // Show only the username check response
    echo json_encode($username_check, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);