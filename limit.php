<?php

$file = "accountsx25.json";
$message = "";

if(isset($_POST['reset'])){

    if(!file_exists($file)){
        $message = "❌ JSON file not found!";
    }else{

        $data = json_decode(file_get_contents($file), true);

        if(!$data){
            $message = "❌ Invalid JSON!";
        }else{

            // loop and reset used
            foreach($data as &$acc){
                $acc['used'] = 0;
            }

            // save back
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

            $message = "✅ All 'used' values reset to 0 successfully!";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reset Used Limit</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body{
    background:#0f2027;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
    font-family:sans-serif;
}

.box{
    background:#203a43;
    padding:30px;
    border-radius:15px;
    text-align:center;
    color:#fff;
    box-shadow:0 0 20px rgba(0,0,0,0.5);
}

button{
    padding:12px 20px;
    border:none;
    border-radius:10px;
    background:linear-gradient(45deg,#ff416c,#ff4b2b);
    color:#fff;
    font-weight:bold;
    cursor:pointer;
}

.msg{
    margin-top:15px;
    color:#0f0;
}
.error{
    color:#ff4b2b;
}
</style>

</head>
<body>

<div class="box">
    <h2>⚡️ Reset Used Limit</h2>

    <form method="POST">
        <button type="submit" name="reset">🔄 Reset All Used = 0</button>
    </form>

    <?php if($message): ?>
        <div class="msg"><?php echo $message; ?></div>
    <?php endif; ?>
</div>

</body>
</html>