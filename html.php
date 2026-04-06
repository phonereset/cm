<?php
header("Content-Type: application/json; charset=utf-8");

// -------------------------
// NID + DOB
// -------------------------
$nid = $_GET["nid"] ?? "";
$dob = $_GET["dob"] ?? "";

if ($nid === "" || $dob === "") {
    echo json_encode(["status" => "error", "message" => "nid & dob required"]);
    exit;
}
$accountsFile = __DIR__ . "/accountsx25.json";

$authPath = __DIR__ . "/authx25.json";


$accounts = [];

if (file_exists($accountsFile)) {
    $accountsJson = file_get_contents($accountsFile);
    $accounts = json_decode($accountsJson, true);
    if (!is_array($accounts)) {
        $accounts = [];
    }
}
function incrementUsage(array &$accounts, string $accountsFile, ?string $username, ?int $index = null): void
{
    if (!$username) {
        return;
    }

    if ($index !== null && isset($accounts[$index])) {
        $accounts[$index]["used"] = (isset($accounts[$index]["used"]) ? (int)$accounts[$index]["used"] : 0) + 1;
        file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return;
    }

    foreach ($accounts as $i => $acc) {
        if (($acc["username"] ?? "") === $username) {
            $accounts[$i]["used"] = (isset($accounts[$i]["used"]) ? (int)$accounts[$i]["used"] : 0) + 1;
            file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return;
        }
    }
}

function getActiveAccountIndex(array $accounts)
{
    foreach ($accounts as $idx => $acc) {
        $isSuccess = isset($acc["success"]) ? (bool)$acc["success"] : false;
        $limitExhausted = isset($acc["limitExhausted"]) ? (bool)$acc["limitExhausted"] : false;
        if ($isSuccess && !$limitExhausted) {
            return $idx;
        }
    }
    return null;
}
$limitMessage = "আপনি আজকে ২৫ বার এনআইডি সার্চ করেছেন। আপনার আজকে সর্বোচ্চ সার্চের সীমা ২৫ বার।";

if (file_exists($authPath) && filesize($authPath) > 0) {
    $auth = json_decode(file_get_contents($authPath), true);

    if (is_array($auth)) {
        $p_flow_id              = $auth["p_flow_id"];
        $p_flow_step_id         = $auth["p_flow_step_id"];
        $p_instance             = $auth["p_instance"];
        $p_page_submission_id   = $auth["p_page_submission_id"];
        $p_request              = "Search_In_NID";
        $p_reload_on_submit     = "S";
        $pSalt                  = $auth["pSalt"];

        $P0_IP                  = $auth["P0_IP"];
        $P0_G_IP                = $auth["P0_G_IP"];
        $P0_CURR_URL            = $auth["P0_CURR_URL"];

        $P600_ERROR_MESSAGE     = $auth["P600_ERROR_MESSAGE"];
        $P600_PATH              = $auth["P600_PATH"];
        $P600_FIRSTLINEADDRESS  = $auth["P600_FIRSTLINEADDRESS"];
        $P600_NEW_3             = $auth["P600_NEW_3"];
        $P600_NEW_2             = $auth["P600_NEW_2"];

        $pPageItemsProtected    = $auth["pPageItemsProtected"];

        $cookies                = $auth["cookies"];

        $pageItems = [
            "pageItems" => [
                "itemsToSubmit" => [
                    ["n" => "P0_IP", "v" => $P0_IP],
                    ["n" => "P0_G_IP", "v" => $P0_G_IP],
                    ["n" => "P0_CURR_URL", "v" => $P0_CURR_URL],
                    ["n" => "P600_NATIONALID", "v" => $nid],
                    ["n" => "P600_ERROR_MESSAGE", "v" => "", "ck" => $P600_ERROR_MESSAGE],
                    ["n" => "P600_DOB", "v" => $dob],
                    ["n" => "P600_PATH", "v" => $P600_PATH],
                    ["n" => "P600_FIRSTLINEADDRESS", "v" => $P600_FIRSTLINEADDRESS],
                    ["n" => "P600_NEW_3", "v" => "", "ck" => $P600_NEW_3],
                    ["n" => "P600_NEW_2", "v" => "", "ck" => $P600_NEW_2]
                ],
                "protected" => $pPageItemsProtected,
                "rowVersion" => "",
                "formRegionChecksums" => []
            ],
            "salt" => $pSalt
        ];

        $p_json = json_encode($pageItems);

        $postData = http_build_query([
            "p_flow_id"             => $p_flow_id,
            "p_flow_step_id"        => $p_flow_step_id,
            "p_instance"            => $p_instance,
            "p_debug"               => "",
            "p_request"             => $p_request,
            "p_reload_on_submit"    => "S",
            "p_page_submission_id"  => $auth["p_page_submission_id"],
            "p_json"                => $p_json
        ]);

        $url = "https://cdms.police.gov.bd/cdms/wwv_flow.accept?p_context=105:600:$p_instance";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_HTTPHEADER     => [
                "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
                "Accept: application/json, text/javascript, */*; q=0.01",
                "X-Requested-With: XMLHttpRequest",
                "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
                "Cookie: $cookies"
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo json_encode(["status" => "error", "message" => curl_error($ch)]);
            curl_close($ch);
            exit;
        }

        curl_close($ch);

        $decoded = json_decode($response, true);
        if (is_array($decoded) && isset($decoded["redirectURL"])) {
            $redirectUrl = "https://cdms.police.gov.bd/cdms/" . ltrim($decoded["redirectURL"], "/");

            $ch2 = curl_init($redirectUrl);
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    "User-Agent: Mozilla/5.0",
                    "Cookie: $cookies"
                ],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);

            $second = curl_exec($ch2);
            curl_close($ch2);
            $nidFound = false;
            $responseNid = "";

            if (!empty($second) && !empty($nid)) {
                if (preg_match('/<span[^>]*id="P600_NID"[^>]*>(\d+)<\/span>/i', $second, $matches)) {
                    $responseNid = trim($matches[1] ?? "");
                    if ($responseNid === $nid) {
                        $nidFound = true;
                    }
                }
            }

            $bpIdUsername = $auth["bpId"] ?? "";
            incrementUsage($accounts, $accountsFile, $bpIdUsername, null);

            echo json_encode([
                "code"    => 200,
                "success" => true,
                "message" => $nidFound ? "Search completed successfully" : "Search completed (NID not matched or not checked)",
                "data"    => [
                    "username" => $bpIdUsername,
                    "bpId"     => $bpIdUsername,
                    "nidMatch" => $nidFound,
                    "response" => $second
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if (is_array($decoded) && isset($decoded["error"])) {
            $msg = $decoded["error"];
            $lowerMsg = strtolower($msg);
            $addInfo = strtolower($decoded["addInfo"] ?? "");

            $isSession = str_contains($lowerMsg, "session") || str_contains($addInfo, "sign in");
            $isPageProtection = str_contains($lowerMsg, "page protection violation");

            if ($isSession || $isPageProtection) {
                @unlink($authPath);
            } else {
                echo $response;
                exit;
            }
        } elseif (is_array($decoded) && isset($decoded["errors"][0]["message"])) {
            $msg = $decoded["errors"][0]["message"];
            $lowerMsg = strtolower($msg);

            $isLimit = (trim($msg) === $limitMessage);
            $isSession = str_contains($lowerMsg, "session");

            if ($isLimit || $isSession) {
                if ($isLimit) {
                    $bpIdUsername = $auth["bpId"] ?? "";
                    foreach ($accounts as $i => $acc) {
                        if (($acc["username"] ?? "") === $bpIdUsername) {
                            $accounts[$i]["limitExhausted"] = true;
                            file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                            break;
                        }
                    }
                }
                @unlink($authPath);
            } else {
                echo $response;
                exit;
            }
        } else {
            echo $response;
            exit;
        }
    }
}


while (true) {
    $activeIndex = getActiveAccountIndex($accounts);
    if ($activeIndex === null) {
        echo json_encode(["status" => "error", "message" => "No active account!"], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $activeUser = [
        "username" => $accounts[$activeIndex]["username"] ?? "",
        "password" => $accounts[$activeIndex]["password"] ?? "",
    ];

    if ($activeUser["username"] === "" || $activeUser["password"] === "") {
        continue;
    }

    if (file_exists($authPath)) {
        @unlink($authPath);
    }

    $protocol = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https://" : "http://";
    $host = $_SERVER["HTTP_HOST"] ?? $_SERVER["SERVER_NAME"] ?? "localhost";
    $basePath = rtrim(dirname($_SERVER["PHP_SELF"]), "/");

    $loginAPI = $protocol . $host . $basePath . "/apiLogin.php?user=" . urlencode($activeUser["username"]) . "&pass=" . urlencode($activeUser["password"]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $loginAPI,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 15,
    ]);

    $loginResponse = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        continue;
    }
    curl_close($ch);

    $loginJson = json_decode($loginResponse, true);
    if (!is_array($loginJson) || strtolower($loginJson["status"] ?? "") !== "success") {
        if (is_array($loginJson) &&
            strtolower($loginJson["status"] ?? "") === "error" &&
            trim($loginJson["message"] ?? "") === "Login failed or session invalid") {
            $accounts[$activeIndex]["success"] = false;
            file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        continue;
    }

    if (!file_exists($authPath)) {
        continue;
    }

    $auth = json_decode(file_get_contents($authPath), true);
    if (!$auth) {
        continue;
    }

    $p_flow_id              = $auth["p_flow_id"];
    $p_flow_step_id         = $auth["p_flow_step_id"];
    $p_instance             = $auth["p_instance"];
    $p_page_submission_id   = $auth["p_page_submission_id"];
    $p_request              = "Search_In_NID";
    $p_reload_on_submit     = "S";
    $pSalt                  = $auth["pSalt"];

    $P0_IP                  = $auth["P0_IP"];
    $P0_G_IP                = $auth["P0_G_IP"];
    $P0_CURR_URL            = $auth["P0_CURR_URL"];

    $P600_ERROR_MESSAGE     = $auth["P600_ERROR_MESSAGE"];
    $P600_PATH              = $auth["P600_PATH"];
    $P600_FIRSTLINEADDRESS  = $auth["P600_FIRSTLINEADDRESS"];
    $P600_NEW_3             = $auth["P600_NEW_3"];
    $P600_NEW_2             = $auth["P600_NEW_2"];

    $pPageItemsProtected    = $auth["pPageItemsProtected"];

    $cookies                = $auth["cookies"];
    $bpId                   = $auth["bpId"];

    $pageItems = [
        "pageItems" => [
            "itemsToSubmit" => [
                ["n" => "P0_IP", "v" => $P0_IP],
                ["n" => "P0_G_IP", "v" => $P0_G_IP],
                ["n" => "P0_CURR_URL", "v" => $P0_CURR_URL],
                ["n" => "P600_NATIONALID", "v" => $nid],
                ["n" => "P600_ERROR_MESSAGE", "v" => "", "ck" => $P600_ERROR_MESSAGE],
                ["n" => "P600_DOB", "v" => $dob],
                ["n" => "P600_PATH", "v" => $P600_PATH],
                ["n" => "P600_FIRSTLINEADDRESS", "v" => $P600_FIRSTLINEADDRESS],
                ["n" => "P600_NEW_3", "v" => "", "ck" => $P600_NEW_3],
                ["n" => "P600_NEW_2", "v" => "", "ck" => $P600_NEW_2]
            ],
            "protected" => $pPageItemsProtected,
            "rowVersion" => "",
            "formRegionChecksums" => []
        ],
        "salt" => $pSalt
    ];

    $p_json = json_encode($pageItems);

    $postData = http_build_query([
        "p_flow_id"             => $p_flow_id,
        "p_flow_step_id"        => $p_flow_step_id,
        "p_instance"            => $p_instance,
        "p_debug"               => "",
        "p_request"             => $p_request,
        "p_reload_on_submit"    => "S",
        "p_page_submission_id"  => $auth["p_page_submission_id"],
        "p_json"                => $p_json
    ]);

    $url = "https://cdms.police.gov.bd/cdms/wwv_flow.accept?p_context=105:600:$p_instance";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_HTTPHEADER     => [
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
            "Accept: application/json, text/javascript, */*; q=0.01",
            "X-Requested-With: XMLHttpRequest",
            "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
            "Cookie: $cookies"
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo json_encode(["status" => "error", "message" => curl_error($ch)]);
        curl_close($ch);
        exit;
    }

    curl_close($ch);

    $decoded = json_decode($response, true);
    if (is_array($decoded) && isset($decoded["redirectURL"])) {
        $redirectUrl = "https://cdms.police.gov.bd/cdms/" . ltrim($decoded["redirectURL"], "/");

        $ch2 = curl_init($redirectUrl);
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "User-Agent: Mozilla/5.0",
                "Cookie: $cookies"
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $second = curl_exec($ch2);
        curl_close($ch2);
        $nidFound = false;
        $responseNid = "";

        if (!empty($second) && !empty($nid)) {
            if (preg_match('/<span[^>]*id="P600_NID"[^>]*>(\d+)<\/span>/i', $second, $matches)) {
                $responseNid = trim($matches[1] ?? "");
                if ($responseNid === $nid) {
                    $nidFound = true;
                }
            }
        }

        $bpIdUsername = $bpId ?? ($activeUser["username"] ?? "");

        incrementUsage($accounts, $accountsFile, $bpIdUsername, $activeIndex);

        echo json_encode([
            "code"    => 200,
            "success" => true,
            "message" => $nidFound ? "Search completed successfully" : "Search completed (NID not matched or not checked)",
            "data"    => [
                "username" => $bpIdUsername,
                "bpId"     => $bpIdUsername,
                "nidMatch" => $nidFound,
                "response" => $second
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (is_array($decoded) && isset($decoded["error"])) {
        $msg = $decoded["error"];
        $lowerMsg = strtolower($msg);
        $addInfo = strtolower($decoded["addInfo"] ?? "");

        $isSession = str_contains($lowerMsg, "session") || str_contains($addInfo, "sign in");
        if ($isSession) {
            continue;
        }
    }
    if (!is_array($decoded)) {
        continue;
    }

    $msg = $decoded["errors"][0]["message"] ?? "";

    if ($msg !== "" && trim($msg) === $limitMessage) {
        $accounts[$activeIndex]["limitExhausted"] = true;
        file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        continue;
    }
    $sessionError = false;
    if ($msg !== "" && str_contains(strtolower($msg), "session")) {
        $sessionError = true;
    }

    if ($sessionError) {
        continue;
    }
    echo $response;
    exit;
}
?>
