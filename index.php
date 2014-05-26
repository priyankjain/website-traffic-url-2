<?php
require_once("config.php");
require_once('twitteroauth/twitteroauth.php');
set_time_limit(36000);
function plain_curl($url = '', $var = '', $header = false, $nobody = false) {
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_NOBODY, $header);
    curl_setopt($curl, CURLOPT_HEADER, $nobody);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    if ($var) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $var);
    }
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($curl);
    curl_close($curl);
    return $result;
}


function fetch_value($str, $find_start, $find_end) {
    $start = strpos($str, $find_start);
    if ($start === false) {
        return "";
    }
    $length = strlen($find_start);
    $end = strpos(substr($str, $start + $length), $find_end);
    return trim(substr($str, $start + $length, $end));
}
function fetch_value_notrim($str, $find_start, $find_end) {
    $start = strpos($str, $find_start);
    if ($start === false) {
        return "";
    }
    $length = strlen($find_start);
    $end = strpos(substr($str, $start + $length), $find_end);
    return substr($str, $start + $length, $end);
}

function cleanup($response){
    $response=str_replace("\n", "",$response);
    $response=str_replace("\r", "",$response);
    $response=str_replace("\t", "",$response);
    $response=str_replace(" ", "",$response);
    return $response;
}

//Authenticate to twitter
$app_no = 0;
$connection = new TwitterOAuth($config[0]['key'],$config[0]['secret'],$config[0]['access_token'],$config[0]['access_token_secret']);

//Get bit.ly API keys
$bitly_access_token = $config['bitly_access_token'];

//Get ow.ly API keys
$owly_access_token = $config['owly_access_token'];

//Get long url
$long_url = $config['longurl'];

//Get count
$file = fopen("count.txt","r");
$count = fgets($file);
fclose($file);

//Open twitterlinks file in append mode to write links to
$tco_file = fopen("tco.txt","a+");

$count_file = fopen("count.txt","w");
function shorten_url()
{
    global $bitly_access_token,$long_url,$owly_access_token, $count_file,$count,$tco_file,$connection,$app_no,$config;
    //Get short link from bit.ly

    $urls=array();
    for($i=0; $i<3;$i++){
        $response = null;
        $error = true;
        while($error){
            $response = plain_curl ("https://api-ssl.bitly.com/v3/shorten","access_token=".$bitly_access_token."&longUrl=".$long_url."?".$count);
            echo $response.'<br/>';
            $response = json_decode($response);
            if($response->status_code == 200) $error = false;
            else sleep(1);
        }
        $urls[] = $response->data->url;
        
        //Get short link from ow.ly
        $response = null;
        $error = true;
        while($error){
            $response = plain_curl ("http://ow.ly/api/1.1/url/shorten","apiKey=".$owly_access_token."&longUrl=".$long_url."?".$count);
            echo $response.'<br/>';
            $response = json_decode($response);
            if(empty($response->error)) $error = false;
            else sleep(1);
        }
        $urls[] = $response->results->shortUrl;
        $count++;
    }
    $tweeted = false;
    $tweet= implode(" ",$urls);
    $limit_count = 0;
    $status = null;
    while(! $tweeted){
        if($limit_count == 4) {
            echo 'Sleeping for 10 minutes as either all four twitter API keys are invalid or daily status update limit has been reached for all';
            sleep(600);
        }
        $status = $connection->post('statuses/update', array('status' => $tweet));
        if(empty($status->errors))
        {
            for($i=0;$i<6;$i++){
                if(isset($status->entities->urls[$i]))
                fputs($tco_file,$status->entities->urls[$i]->url.PHP_EOL);
            }
            $tweeted = true;
        }
        else
        {
            $limit_count++;
            echo '<br/>Credentials '.$app_no.' rate limited<br/>';
            var_dump($status);
            $app_no = ($app_no+1) % 4;
            $connection = new TwitterOAuth($config[$app_no]['key'],$config[$app_no]['secret'],$config[$app_no]['access_token'],$config[$app_no]['access_token_secret']);
            //Code to change to next api
        }

    }
    rewind($count_file);
    fputs($count_file,$count);
}

//Call the shortening apis and store the shortened links
for($i = 0; $i <= 10000; $i++)
shorten_url();

fclose($tco_file);
fclose($count_file);
?>