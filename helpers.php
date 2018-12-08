<?php
/**
 * Sending SMS to Users
 * @param  string $finalText text content to be sent
 * @param  mobile $mobile_no all mobile numbers
 * @return boolean            text
 */

function sendFreeSms($finalText,$mobile_no)
{
    $ch = curl_init();
    ob_start();
    $message = urlencode($finalText);
    if(is_array($mobile_no) && count($mobile_no) > 0)
    {
        $MobileNumbers = Implode(",",$mobile_no);       
    }
    else{
        $MobileNumbers = $mobile_no;
    }
    curl_setopt($ch, CURLOPT_URL, "http://ip.shreesms.net/smsserver/SMS200N.aspx?Userid=konnectme&UserPassword=Konnect@007&PhoneNumber=91".$MobileNumbers."&Text=".$message."&GSM=KONECT");
    $buffer = curl_exec($ch);
    curl_close($ch);
    ob_end_clean(); 
    if($buffer === FALSE){
        return new Exception();
    }
    return $buffer;        
}

/**
 * Sending Push Notification to Android users
 * @param  array $registatoin_ids array of device_id
 * @param  array $message         message string to be sent
 * @return array                  response
 */

function send_notification($registatoin_ids, $message) {  
$url = 'https://fcm.googleapis.com/fcm/send';
    $GOOGLE_API_KEY='AIzaSyDxITJD159PbXl3jIGMgTLRBhxSnki6jRk'; // Live API key
    $fields = array(
         'registration_ids' => $registatoin_ids,
         'data' => $message,
    );   
    $headers = array(
        'Authorization: key=' .$GOOGLE_API_KEY,
        'Content-Type: application/json'
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    $result = curl_exec($ch);
    curl_close($ch);
    
    if ($result === FALSE) {
        return new Exception();
    }
    return $result;      
}    

/**
 * Send IOS Notification
 * $env is false by default means the device-ID is of production
 * if device-Id is testing ID then we'll pass $env as true
 * $env = FALSE  LIVE URL
 * TRUE   DEV  URL
 */

 

function send_custom_IOSNotification($registatoin_ids, $finalArrayVal, $title, $env = true) 
{    
    $url = "https://fcm.googleapis.com/fcm/send";
    $serverKey = 'AIzaSyDxITJD159PbXl3jIGMgTLRBhxSnki6jRk';
    $arrlength=count($registatoin_ids); 
    for( $x=0; $x < $arrlength; $x++ ) {
        $notification = array('title' =>$title , 'sound' => 'default', 'badge' => $registatoin_ids[$x]['badge']);
        $arrayToSend = array('to' => $registatoin_ids[$x]['device_id'], 'notification' => $notification,'priority'=>'high');
        $json = json_encode($arrayToSend);
        $headers = array();
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Authorization: key='. $serverKey;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Disabling SSL Certificate support temporarly
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        
        //Send the request
        $response = curl_exec($ch);
        //Close request
        if ($response === FALSE) {
            //die('FCM Send Error: ' . curl_error($ch));
        }
        curl_close($ch); 
        updateUserBadges($registatoin_ids[$x]['user_id'],$registatoin_ids[$x]['badge']);
    }
}
 

function updateUserBadges($userId,$badge){  
    $user  = \App\User::select('id','badge_count')->where('id',$userId)->first(); 
    if($user){
        $user->badge_count = $badge;
        $user->save();
        //dd($user);
    }  
}

function send_IOSNotification($registatoin_ids, $messageMe, $env = false) {
    $passphrase = '1234';
    $ctx = stream_context_create();
    $path = storage_path();
    if($env){
        stream_context_set_option($ctx, 'ssl', 'local_cert', $path.'/IRDev.pem');
    }
    else{
        stream_context_set_option($ctx, 'ssl', 'local_cert', $path.'/IRDis.pem');
    }

    stream_context_set_option($ctx, 'ssl', 'passphrase', $passphrase);
    stream_context_set_option($ctx, 'ssl', 'cafile', $path.'/entrust_2048_ca.cer');   

    // Open a connection to the APNS server
    if($env)
    {   
        // ID is of Testing Environment
        $fp = stream_socket_client('ssl://gateway.sandbox.push.apple.com:2195', $err,$errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);
    }
    else
    {
        // ID is of LIVE Environment
        $fp = stream_socket_client('tls://gateway.push.apple.com:2195', $err,$errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);
    }

    if (!$fp)
        exit("Failed to connect: $err $errstr" . PHP_EOL);

    $arrlength=count($registatoin_ids);
    
    \Log::useFiles(storage_path().'/logs/notification.log');
   
    for($x=0;$x<$arrlength;$x++)
    {
        // Added on 14x11x16 because noti. from panel were crashing
        foreach($messageMe as $key => $value)
        {
            $messageMe[$key] = array_map('custom_strval', $messageMe[$key]);
        }

        $payload = json_encode($messageMe);  
        // echo '->> '.$payload;
        $msg = chr(0) . pack('n', 32) . pack('H*', $registatoin_ids[$x]['device_id']) . pack('n', strlen($payload)) . $payload;
        // Send it to the server


        
        
        \Log::info('Processing #'.$x.': '.$registatoin_ids[$x]['device_id']);

        $result = fwrite($fp, $msg, strlen($msg));

        \Log::info('Processing response code is #'.$result);
       
        if (!$result)
        {
            \Log::info('Message not delivered at '.$x);

            fclose($fp);

            if($env)
            {
                // ID is of Testing Environment
                $fp = stream_socket_client('ssl://gateway.sandbox.push.apple.com:2195', $err,$errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);
            }
            else
            {
                // ID is of LIVE Environment
                $fp = stream_socket_client('tls://gateway.push.apple.com:2195', $err,$errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);
            }
        }
        else
        {
            \Log::info('Message successfully delivered at '.$x);
        }
        // Close the connection to the server
    }
    
    fclose($fp);
}

function custom_strval($value)
{
    if(is_array($value)){
        return $value;
    }
    else{
        return strval($value);
    }
}

// In IOS, if the ID is of local env, we have to differentiate it
function IOSlocalOrProd($val)
{
    // Add all the DEVELOPMENT ID here (comma seperated) so they will be sent from DEV Gateway
    $localID = [''];
    return (boolean) in_array($val, $localID);
}

function userName($id)
{
    $query = \App\User::select('name')->where('id', $id)->first();
    if($query)
    {
        return $query['name'];   
    }
    else
    {
        return 'Biodata Deleted';
    }
}

function nativeSearch($keyword,$array)
{
    $input      = $keyword;
    $result     = array();
    $array      = $array->toArray();
    $input      = strtolower($input);
    for($i=0;$i<count($array);$i++)
    {
        $colors1 = $array[$i];
        $colors  = $array[$i];
        array_splice($colors, 0, 1);
        $result1 = array_filter($colors, function ($item) use ($input) {
            if (stripos($item, $input) !== false) {
                return true;
            }
            return false;
        });
        if($result1 != false)
        {
            $result[$i] = $colors1['user_id'];
        }
    }
     return $result;  
}

function decryptBiodataId($orders)
{
    $count = 0;
    foreach ($orders as $order) {
        $orders[$count] = \Crypt::decrypt($order);
        $count++;
    }

    return $orders;
}

function sendMail($email,$body,$subject)
{
    $mail = new \PHPMailer;

    //$mail->SMTPDebug = 3;                               // Enable verbose debug output

    $mail->isSMTP();                                      // Set mailer to use SMTP
    $mail->Host       = 'smtp.gmail.com';  // Specify main and backup SMTP servers
    $mail->SMTPAuth   = true;                               // Enable SMTP authentication
    $mail->Username   = 'demokonnectapp@gmail.com';                 // SMTP username
    $mail->Password   = 'demokonnect';                           // SMTP password
    $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
    $mail->Port       = 587;                                    // TCP port to connect to
    $mail->setFrom('demokonnectapp@gmail.com', 'Indian Royals');
    $mail->addAddress($email);     // Add a recipient
    $mail->addReplyTo('demokonnectapp@gmail.com', 'Information');
    $mail->isHTML(true); // Set email format to HTML

    $mail->Subject = $subject;
    $mail->Body    = $body;
    $mail->AltBody = 'This mail contains HTML content.Your mail client is not compitable with the HTML content.';

    if(!$mail->send()) {
        return false;
        // echo "Message could not be sent to $email.";
        // echo 'Mailer Error: ' . $mail->ErrorInfo;
    } else {
        return true;
        // echo 'Message has been sent';
    }
}