<?php

$responses = [];

if(isset($_POST['submit'])){

    $url = $_POST['url'] ?? '';

    if($url){

        $total = 10;

        $multi = curl_multi_init();
        $channels = [];

        // create requests
        for($i=0; $i<$total; $i++){

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            curl_multi_add_handle($multi, $ch);
            $channels[$i] = $ch;
        }

        // execute all
        do {
            $status = curl_multi_exec($multi, $active);
            curl_multi_select($multi);
        } while ($active && $status == CURLM_OK);

        // collect responses
        foreach($channels as $i => $ch){
            $content = curl_multi_getcontent($ch);

            if(!$content){
                $content = "❌ No Response / Error";
            }

            $responses[] = [
                "id" => $i+1,
                "data" => htmlspecialchars($content)
            ];

            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }

        curl_multi_close($multi);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Request Sender Pro</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family: 'Segoe UI', sans-serif;
}

body{
    background: linear-gradient(135deg,#0f2027,#203a43,#2c5364);
    color:#fff;
    padding:20px;
}

/* Top Box */
.container{
    max-width:400px;
    margin:auto;
    padding:25px;
    border-radius:20px;
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(15px);
    box-shadow:0 0 30px rgba(0,0,0,0.5);
    text-align:center;
}

h1{
    margin-bottom:15px;
}

input{
    width:100%;
    padding:12px;
    border:none;
    border-radius:10px;
    margin-bottom:10px;
    background: rgba(255,255,255,0.1);
    color:#fff;
}

button{
    width:100%;
    padding:12px;
    border:none;
    border-radius:10px;
    font-weight:bold;
    cursor:pointer;
    background: linear-gradient(45deg,#ff416c,#ff4b2b,#1fd1f9,#b621fe);
    background-size:300% 300%;
    color:#fff;
    transition:0.4s;
}

button:hover{
    background-position:right center;
}

/* Response Box */
.response-box{
    max-width:800px;
    margin:30px auto;
}

.response{
    background: rgba(0,0,0,0.6);
    padding:15px;
    border-radius:10px;
    margin-bottom:10px;
    font-size:13px;
    overflow:auto;
}

.response span{
    color:#1fd1f9;
    font-weight:bold;
}

/* Footer */
.footer{
    text-align:center;
    margin-top:30px;
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
    <h1>⚡️ Request Sender Pro</h1>

    <form method="POST">
        <input type="text" name="url" placeholder="Enter URL..." required>
        <button type="submit" name="submit">🚀 Send 10 Requests</button>
    </form>
</div>

<?php if(!empty($responses)): ?>
<div class="response-box">
    <h2 style="text-align:center; margin-bottom:15px;">📡 Responses</h2>

    <?php foreach($responses as $res): ?>
        <div class="response">
            <span>Response <?php echo $res['id']; ?>:</span><br>
            <?php echo nl2br($res['data']); ?>
        </div>
    <?php endforeach; ?>

</div>
<?php endif; ?>

<div class="footer">
    Developed By: <a href="https://t.me/iamRaihan" target="_blank">Ariyan Raihan</a>
</div>

</body>
</html>