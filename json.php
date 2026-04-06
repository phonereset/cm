<?php
header('Content-Type: application/json; charset=utf-8');

$rawNid = $_GET['nid'] ?? '';
$rawDob = $_GET['dob'] ?? '';

$nid = trim($rawNid);
$dob = trim($rawDob);

if ($nid === '' || $dob === '') {
    echo json_encode([
        'code' => 400,
        'status' => 'error',
        'message' => 'nid or dob missing',
        'data' => 'nai!'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
$basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$url = $protocol . $host . $basePath . "/html.php?nid=" . urlencode($nid) . "&dob=" . urlencode($dob);


function callHtmlEndpoint($url)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36",
        CURLOPT_HTTPHEADER => [
            "ngrok-skip-browser-warning: true",
            "Content-Type: application/json"
        ],
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $error) {
        return [null, $error ?: 'Unable to reach html endpoint'];
    }

    return [$response, null];
}

[$responseBody, $error] = callHtmlEndpoint($url);
if ($error) {
    echo json_encode([
        'code' => 500,
        'status' => 'error',
        'message' => $error,
        'data' => 'nai!'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$json = json_decode($responseBody, true);
if (!is_array($json)) {
    echo json_encode([
        'code' => 502,
        'status' => 'error',
        'message' => 'Invalid JSON',
        'data' => $responseBody
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Check for NID/DOB validation errors
if (isset($json['errors']) && is_array($json['errors'])) {
    foreach ($json['errors'] as $error) {
        $message = $error['message'] ?? '';
        if (stripos($message, 'Invalid NID or Date Of Birth') !== false) {
            echo json_encode([
                'code' => 400,
                'status' => 'error',
                'message' => 'Invalid NID or Date of Birth provided',
                'data' => 'nai!'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
    }
}

if (($json['code'] ?? 0) !== 200 || ($json['success'] ?? false) !== true) {
    $errorMessage = $json['message'] ?? 'Unknown error from html endpoint';

    // Check for "Access is denied" error and replace with "server err"
    if (isset($json['errors']) && is_array($json['errors'])) {
        foreach ($json['errors'] as $error) {
            $message = $error['message'] ?? '';
            if (stripos($message, 'Access is denied') !== false) {
                $errorMessage = 'Access is denied';
                break;
            }
        }
    }

    echo json_encode([
        'code' => 400,
        'status' => 'error',
        'message' => $errorMessage,
        'data' => 'nai!',

       // 'data' => $responseBody
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$htmlResponse = $json['data']['response'] ?? '';
if ($htmlResponse === '') {
    echo json_encode([
        'code' => 404,
        'status' => 'error',
        'message' => 'Empty response payload',
        'data' => 'nai!'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function extractSpanValue($html, $id)
{
    if (preg_match('/<span[^>]+id="' . preg_quote($id, '/') . '"[^>]*>([^<]*)<\/span>/i', $html, $matches)) {
        return trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    return '';
}

$data = [
    'nationalId' => extractSpanValue($htmlResponse, 'P600_NID'),
    'nameBangla' => extractSpanValue($htmlResponse, 'P600_PERSON_NAME'),
    'nameEnglish' => extractSpanValue($htmlResponse, 'P600_PERSON_NAME_ENG'),
    'fatherName' => extractSpanValue($htmlResponse, 'P600_FATH_NM'),
    'motherName' => extractSpanValue($htmlResponse, 'P600_MOTH_NM'),
    'bloodGroup' => extractSpanValue($htmlResponse, 'P600_BLOOD_GR'),
    'gender' => strtolower(extractSpanValue($htmlResponse, 'P600_GEND')),
    'presentHomeOrHoldingNo' => extractSpanValue($htmlResponse, 'P600_HOMEORHOLDINGNO'),
    'presentVillageOrRoad' => extractSpanValue($htmlResponse, 'P600_ADDI_VILL_OR_ROAD'),
    'presentMouzaOrMoholla' => extractSpanValue($htmlResponse, 'P600_ADDITIONALMOUZAORMOHOLLA'),
    'presentPostOffice' => extractSpanValue($htmlResponse, 'P600_POSTOFFICE'),
    'presentPostalCode' => extractSpanValue($htmlResponse, 'P600_POSTALCODE'),
    'presentUpozila' => extractSpanValue($htmlResponse, 'P600_UPOZILA'),
    'presentDistrict' => extractSpanValue($htmlResponse, 'P600_DISTRICT'),
    'presentDivision' => extractSpanValue($htmlResponse, 'P600_DIVISION'),
    'permanentHomeOrHoldingNo' => extractSpanValue($htmlResponse, 'P600_PHOMEORHOLDINGNO'),
    'permanentVillageOrRoad' => extractSpanValue($htmlResponse, 'P600_PADDI_VILL_OR_ROAD'),
    'permanentMouzaOrMoholla' => extractSpanValue($htmlResponse, 'P600_PADDITIONALMOUZAORMOHOLLA'),
    'permanentPostOffice' => extractSpanValue($htmlResponse, 'P600_PPOSTOFFICE'),
    'permanentPostalCode' => extractSpanValue($htmlResponse, 'P600_PPOSTALCODE'),
    'permanentUpozila' => extractSpanValue($htmlResponse, 'P600_PUPOZILA'),
    'permanentDistrict' => extractSpanValue($htmlResponse, 'P600_PDISTRICT'),
    'permanentDivision' => extractSpanValue($htmlResponse, 'P600_PDIVISION'),
    // 'spouseName' => extractSpanValue($htmlResponse, 'P600_SPOUSE_NM'),
    'permanentRmo' => extractSpanValue($htmlResponse, 'P600_PRMO'),
    'permanentUnionOrWard' => extractSpanValue($htmlResponse, 'P600_PUNIONORWARD'),
    'presentRmo' => extractSpanValue($htmlResponse, 'P600_RMO'),
    'presentUnionOrWard' => extractSpanValue($htmlResponse, 'P600_UNIONORWARD'),
];

if ($data['nameBangla'] === '' && $data['nameEnglish'] === '') {
    echo json_encode([
        'code' => 404,
        'status' => 'error',
        'message' => 'Information not found',
        'data' => 'nai!'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$photoUrl = '';
if (preg_match('/<img[^>]+src="([^"]+)"/i', $htmlResponse, $matches)) {
    $remotePhotoUrl = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($remotePhotoUrl !== '') {
        $localFolder = __DIR__ . '/uploads/';
        if (!is_dir($localFolder)) {
            mkdir($localFolder, 0777, true);
        }
        $photoPath = $localFolder . $nid . '.jpg';

        $photoCurl = curl_init($remotePhotoUrl);
        curl_setopt_array($photoCurl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 120,
        ]);
        $photoData = curl_exec($photoCurl);
        curl_close($photoCurl);

        if ($photoData) {
            file_put_contents($photoPath, $photoData);
            $photoUrl = $protocol . $host . $basePath . "/uploads/" . rawurlencode($nid) . ".jpg";
        }
    }
}


function detectReligion($nameBangla, $nameEnglish) {
    $bangla = mb_strtolower($nameBangla, 'UTF-8');
    $english = strtolower($nameEnglish);

    $muslimPatternsBangla = [
        'মোঃ', 'মোঃ', 'মুহাম্মদ', 'মোহাম্মদ', 'মুহম্মদ', 'মোহম্মদ',
        'আলী', 'আলি', 'রহমান', 'রহমান', 'খান', 'মিয়া', 'চৌধুরী', 'চৌধুরি',
        'হোসেন', 'হোসেন', 'হাসান', 'হাসান', 'আহমেদ', 'আহমদ', 'ইসলাম',
        'মন্ডল', 'সরকার', 'শেখ', 'মোল্লা', 'কাজী', 'ইমাম', 'আব্দুল',
        'আবদুল', 'জামাল', 'জামান', 'নুর', 'নূর', 'ফাতেমা', 'ফাতেমা',
        'আয়েশা', 'আয়েশা', 'জাহানারা', 'রাবেয়া', 'খালেদ', 'সালাম',
        'মাসুদ', 'মাসুম', 'রফিক', 'রশিদ', 'ফিরোজ', 'আনোয়ার'
    ];

    $muslimPatternsEnglish = [
        'md', 'mohammad', 'muhammad', 'mohammed', 'muhammed',
        'ali', 'rahman', 'abdul', 'abdullah', 'khan', 'mia', 'chowdhury', 'chowdhuri',
        'hossain', 'hossein', 'hassan', 'hasan', 'ahmed', 'ahmad',
        'islam', 'mondal', 'sarkar', 'sheikh', 'molla', 'kazi', 'imam',
        'jamal', 'zaman', 'nur', 'noor', 'fatema', 'ayesha', 'ayesha',
        'jahangir', 'salam', 'masud', 'masum', 'rafiq', 'rashid', 'firoz', 'anwar'
    ];

    $hinduPatternsBangla = [
        'রায়', 'রায়', 'দাস', 'দাশ', 'বর্মন', 'বর্মা', 'চক্রবর্তী',
        'মজুমদার', 'বন্দ্যোপাধ্যায়', 'চট্টোপাধ্যায়', 'সেন', 'সিংহ',
        'কর', 'পাল', 'বাগচী', 'ঘোষ', 'মিত্র', 'ভট্টাচার্য',
        'নন্দী', 'ভাদুড়ী', 'মুখার্জী', 'বসু', 'গুপ্ত', 'চৌবে',
        'শর্মা', 'ত্রিবেদী', 'চন্দ্র', 'চন্দ', 'কুমার', 'কুমার',
        'লাল', 'প্রসাদ', 'নাথ', 'রাম', 'শ্যাম', 'হরি', 'কৃষ্ণ',
        'গোবিন্দ', 'জয়', 'বিজয়', 'সুবোধ', 'প্রকাশ', 'জ্যোতি',
        'সূর্য', 'চাঁদ', 'সাগর', 'নদী', 'বন', 'বৃক্ষ', 'পুষ্প'
    ];

    $hinduPatternsEnglish = [
        'roy', 'das', 'barman', 'chakraborty', 'majumder', 'banerjee',
        'chatterjee', 'sen', 'singh', 'kar', 'pal', 'bagchi', 'ghosh',
        'mitra', 'bhattacharya', 'nandi', 'bhaduri', 'mukherjee', 'bose',
        'gupta', 'chaube', 'sharma', 'trivedi', 'chandra', 'kumar',
        'lal', 'prasad', 'nath', 'ram', 'shyam', 'hari', 'krishna',
        'jay', 'bijay', 'subodh', 'prakash', 'jyoti', 'surya', 'chand',
        'sagar', 'nodi', 'ban', 'brish', 'pushp', 'saha', 'saha'
    ];

    $christianPatterns = [
        'পল', 'পিটার', 'জন', 'মেরি', 'জোসেফ', 'থমাস', 'অ্যান্ড্রু',
        'ম্যাথু', 'মার্ক', 'লুক', 'জেমস', 'ডেভিড', 'মাইকেল', 'ড্যানিয়েল',
        'রবার্ট', 'জনসন', 'উইলিয়াম', 'চার্লস', 'এলিজাবেথ', 'সারাহ',
        'paul', 'peter', 'john', 'mary', 'joseph', 'thomas', 'andrew',
        'matthew', 'mark', 'luke', 'james', 'david', 'michael', 'daniel',
        'robert', 'william', 'charles', 'elizabeth', 'sarah', 'christian'
    ];

    foreach ($muslimPatternsBangla as $pattern) {
        if (mb_strpos($bangla, $pattern, 0, 'UTF-8') !== false) {
            return 'ইসলাম';
        }
    }

    foreach ($muslimPatternsEnglish as $pattern) {
        if (strpos($english, $pattern) !== false) {
            return 'ইসলাম';
        }
    }

    foreach ($hinduPatternsBangla as $pattern) {
        if (mb_strpos($bangla, $pattern, 0, 'UTF-8') !== false) {
            return 'হিন্দু';
        }
    }

    foreach ($hinduPatternsEnglish as $pattern) {
        if (strpos($english, $pattern) !== false) {
            return 'হিন্দু';
        }
    }

    foreach ($christianPatterns as $pattern) {
        if (mb_strpos($bangla, $pattern, 0, 'UTF-8') !== false ||
            strpos($english, $pattern) !== false) {
            return 'খ্রিস্টান';
        }
    }

    return 'N/A';
}



function calculateAgeAndBirthDay($dob) {
    if (empty($dob) || $dob === 'N/A') {
        return [
            'age' => 'N/A',
            'birthDay' => 'N/A'
        ];
    }

    try {
        // Set Bangladesh timezone
        $timezone = new DateTimeZone('Asia/Dhaka');
        $now = new DateTime('now', $timezone);

        // Parse date of birth
        $birthDate = new DateTime($dob, $timezone);

        // Calculate age
        $interval = $now->diff($birthDate);

        $years = $interval->y;
        $months = $interval->m;
        $days = $interval->d;

        // Format age in Bengali
        $ageString = sprintf("%d বছর %02d মাস %02d দিন", $years, $months, $days);

        // Calculate birth day of week
        $daysOfWeek = [
            'রবিবার',    // Sunday
            'সোমবার',   // Monday
            'মঙ্গলবার', // Tuesday
            'বুধবার',   // Wednesday
            'বৃহস্পতিবার', // Thursday
            'শুক্রবার', // Friday
            'শনিবার'    // Saturday
        ];

        $birthDayOfWeek = $daysOfWeek[$birthDate->format('w')];
        $birthDayString = 'রোজ ' . $birthDayOfWeek;

        return [
            'age' => $ageString,
            'birthDay' => $birthDayString
        ];

    } catch (Exception $e) {
        return [
            'age' => 'N/A',
            'birthDay' => 'N/A'
        ];
    }
}




$voterArea = $data['presentUpozila'] !== '' ? $data['presentUpozila'] . ' আওতাধীন' : '';
$permanentAddressLine = "বাসা/হোল্ডিং: " . ($data["permanentHomeOrHoldingNo"] ?? "") .
    ", গ্রাম/রাস্তা: " . ($data["permanentVillageOrRoad"] ?? "") .
    ", মৌজা/মহল্লা: " . ($data["permanentMouzaOrMoholla"] ?? "") .
    ", ডাকঘর: " . ($data["permanentPostOffice"] ?? "") . " - " . ($data["permanentPostalCode"] ?? "") .
    ", উপজেলা: " . ($data["permanentUpozila"] ?? "") .
    ", জেলা: " . ($data["permanentDistrict"] ?? "") .
    ", বিভাগ: " . ($data["permanentDivision"] ?? "");

$presentAddressLine = "বাসা/হোল্ডিং: " . ($data["presentHomeOrHoldingNo"] ?? "") .
    ", গ্রাম/রাস্তা: " . ($data["presentVillageOrRoad"] ?? "") .
    ", মৌজা/মহল্লা: " . ($data["presentMouzaOrMoholla"] ?? "") .
    ", ডাকঘর: " . ($data["presentPostOffice"] ?? "") . " - " . ($data["presentPostalCode"] ?? "") .
    ", উপজেলা: " . ($data["presentUpozila"] ?? "") .
    ", জেলা: " . ($data["presentDistrict"] ?? "") .
    ", বিভাগ: " . ($data["presentDivision"] ?? "");

$payload = [
    'status' => 'success',
    'message' => 'NID lookup completed',
    'data' => [
        'requestId' => uniqid('req_', true),
        'name' => $data['nameBangla'] ?: 'N/A',
        'nameEn' => $data['nameEnglish'] ?: 'N/A',
        'father' => $data['fatherName'] ?: 'N/A',
        'mother' => $data['motherName'] ?: 'N/A',
        'nationalId' => $data['nationalId'] ?: 'N/A',
        'dateOfBirth' => $dob ?: 'N/A',
        'age' => calculateAgeAndBirthDay($dob)['age'],
        'birthDay' => calculateAgeAndBirthDay($dob)['birthDay'],
        'gender' => $data['gender'] === 'male' ? 'পুরুষ' : ($data['gender'] === 'female' ? 'মহিলা' : 'N/A'),
        'genderEn' => $data['gender'] === 'male' ? 'Male' : ($data['gender'] === 'female' ? 'Female' : 'N/A'),
        'religion' => detectReligion($data['nameBangla'], $data['nameEnglish']) ?: 'N/A',
        'religionEn' => detectReligion($data['nameBangla'], $data['nameEnglish']) === 'ইসলাম' ? 'Islam' :
                        (detectReligion($data['nameBangla'], $data['nameEnglish']) === 'হিন্দু' ? 'Hindu' :
                        (detectReligion($data['nameBangla'], $data['nameEnglish']) === 'খ্রিস্টান' ? 'Christian' : 'N/A')),
        // 'spouse' => $data['spouseName'] ?: 'N/A',
        'bloodGroup' => $data['bloodGroup'] ?: 'N/A',
        'birthPlace' => $data['permanentDistrict'] ?: 'N/A',
        'voterId' => 'V' . $data['nationalId'] ?: 'N/A',
        'voterArea' => $voterArea,
        'pollingStation' => $data['permanentUpozila'] . ' কেন্দ্র' ?: 'N/A',
        'constituency' => $data['permanentDistrict'] . '-1' ?: 'N/A',
        'photo' => $photoUrl,
        'permanentAddress' => [
            'division' => $data['permanentDivision'] ?: 'N/A',
            'region' => $data['permanentDistrict'] ?: 'N/A',
            'district' => $data['permanentDistrict'] ?: 'N/A',
            'upozila' => $data['permanentUpozila'] ?: 'N/A',
            'rmo' => $data['permanentRmo'] ?: 'N/A',
            'unionOrWard' => $data['permanentUnionOrWard'] ?: 'N/A',
            'postOffice' => $data['permanentPostOffice'] ?: 'N/A',
            'postCode' => $data['permanentPostalCode'] ?: 'N/A',
            'mouzaMoholla' => $data['permanentMouzaOrMoholla'] ?: 'N/A',
            'villageOrRoad' => $data['permanentVillageOrRoad'] ?: 'N/A',
            'homeOrHoldingNo' => $data['permanentHomeOrHoldingNo'] ?: 'N/A',
            'addressLine' => html_entity_decode($permanentAddressLine, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?: 'N/A',
        ],
        'presentAddress' => [
            'division' => $data['presentDivision'] ?: 'N/A',
            'region' => $data['presentDistrict'] ?: 'N/A',
            'district' => $data['presentDistrict'] ?: 'N/A',
            'upozila' => $data['presentUpozila'] ?: 'N/A',
            'rmo' => $data['presentRmo'] ?: 'N/A',
            'unionOrWard' => $data['presentUnionOrWard'] ?: 'N/A',
            'postOffice' => $data['presentPostOffice'] ?: 'N/A',
            'postCode' => $data['presentPostalCode'] ?: 'N/A',
            'mouzaMoholla' => $data['presentMouzaOrMoholla'] ?: 'N/A',
            'villageOrRoad' => $data['presentVillageOrRoad'] ?: 'N/A',
            'homeOrHoldingNo' => $data['presentHomeOrHoldingNo'] ?: 'N/A',
            'addressLine' => html_entity_decode($presentAddressLine, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?: 'N/A',
        ],
    ],
];

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

?>