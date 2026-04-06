<?php

// ================= IMPORT LOGIC =================
$message = "";

if(isset($_GET['import'])){

    $sourceFile = "accounts.txt";
    $outputFile = "naiaccountsx25.json";
    $limit = 200;

    if(!file_exists($sourceFile)){
        $message = "❌ Source file not found!";
    }else{

        $data = file_get_contents($sourceFile);
        $accounts = preg_split("/\n\s*\n/", trim($data));

        $importAccounts = array_slice($accounts, 0, $limit);
        $remainingAccounts = array_slice($accounts, $limit);

        // Load old JSON
        if(file_exists($outputFile)){
            $existingData = json_decode(file_get_contents($outputFile), true);
            if(!$existingData) $existingData = [];
        }else{
            $existingData = [];
        }

        $newData = [];

        foreach($importAccounts as $acc){

            preg_match('/USER:\s*(.*)/', $acc, $userMatch);
            preg_match('/PASSWORD:\s*(.*)/', $acc, $passMatch);

            $username = trim($userMatch[1] ?? '');
            $password = trim($passMatch[1] ?? '');

            if($username && $password){
                $newData[] = [
                    "username" => $username,
                    "password" => $password,
                    "success" => true,
                    "expiredIn" => null,
                    "limitExhausted" => false,
                    "used" => 0,
                    "lastLogin" => date("Y-m-d H:i:s"),
                    "lastChecked" => date("Y-m-d H:i:s")
                ];
            }
        }

        // Merge
        $finalData = array_merge($existingData, $newData);

        // Save JSON
        file_put_contents($outputFile, json_encode($finalData, JSON_PRETTY_PRINT));

        // Update source file
        file_put_contents($sourceFile, implode("\n\n", $remainingAccounts));

        $message = "✅ Imported ".count($newData)." accounts successfully!";
    }

    echo $message;
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Account Importer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family: 'Segoe UI', sans-serif;
}

body{
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background: linear-gradient(135deg,#0f2027,#203a43,#2c5364);
}

.container{
    width:350px;
    padding:30px;
    border-radius:20px;
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(15px);
    box-shadow:0 0 30px rgba(0,0,0,0.5);
    text-align:center;
}

h1{
    color:#fff;
    margin-bottom:20px;
    font-size:22px;
}

button{
    width:100%;
    padding:12px;
    border:none;
    border-radius:10px;
    font-size:16px;
    font-weight:bold;
    cursor:pointer;
    background: linear-gradient(45deg,#ff416c,#ff4b2b,#1fd1f9,#b621fe);
    background-size:300% 300%;
    color:#fff;
    transition:0.4s;
}

button:hover{
    background-position:right center;
    transform:scale(1.05);
}

.status{
    margin-top:15px;
    color:#0f0;
    font-size:14px;
}

.error{
    color:#ff4b2b;
}

.footer{
    margin-top:20px;
    font-size:12px;
    color:#aaa;
}
.footer a{
    color:#1fd1f9;
    text-decoration:none;
}
</style>

</head>
<body>

<div class="container">
    <h1>🚀 Account Importer</h1>

    <button onclick="importAccounts()">⚡️ Import 100 Accounts</button>

    <div class="status" id="statusBox"></div>

    <div class="footer">
        Developed By: <a href="https://t.me/iamRaihan" target="_blank">Ariyan Raihan</a>
    </div>
</div>

<script>
function importAccounts(){
    let statusBox = document.getElementById("statusBox");
    statusBox.innerHTML = "⏳ Importing...";

    fetch("?import=1")
    .then(res => res.text())
    .then(data => {
        statusBox.innerHTML = data;
        statusBox.classList.remove("error");
    })
    .catch(err => {
        statusBox.innerHTML = "❌ Error importing!";
        statusBox.classList.add("error");
    });
}
</script>

</body>
</html>