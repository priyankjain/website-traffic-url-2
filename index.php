<?php
/*
 * Copyright 2011 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
session_start();

require_once 'Google/Client.php';
require_once 'Google/Service/Urlshortener.php';
require_once("config.php");
require_once('twitteroauth/twitteroauth.php');
set_time_limit(36000);

/************************************************
  ATTENTION: Fill in these values! Make sure
  the redirect URI is to this page, e.g:
  http://localhost:8080/user-example.php
 ************************************************/
 $client_id = $config['client_id'];
 $client_secret = $config['client_secret'];
 $redirect_uri = $config['redirect_uri'];

/************************************************
  Make an API request on behalf of a user. In
  this case we need to have a valid OAuth 2.0
  token for the user, so we need to send them
  through a login flow. To do this we need some
  information from our API console project.
 ************************************************/
$client = new Google_Client();
$client->setClientId($client_id);
$client->setClientSecret($client_secret);
$client->setRedirectUri($redirect_uri);
$client->addScope("https://www.googleapis.com/auth/urlshortener");

/************************************************
  When we create the service here, we pass the
  client to it. The client then queries the service
  for the required scopes, and uses that when
  generating the authentication URL later.
 ************************************************/
$service = new Google_Service_Urlshortener($client);

/************************************************
  If we're logging out we just need to clear our
  local access token in this case
 ************************************************/
if (isset($_REQUEST['logout'])) {
  unset($_SESSION['access_token']);
}

/************************************************
  If we have a code back from the OAuth 2.0 flow,
  we need to exchange that with the authenticate()
  function. We store the resultant access token
  bundle in the session, and redirect to ourself.
 ************************************************/
if (isset($_GET['code'])) {
  $client->authenticate($_GET['code']);
  $_SESSION['access_token'] = $client->getAccessToken();
  $redirect = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
  header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));
}

/************************************************
  If we have an access token, we can make
  requests, else we generate an authentication URL.
 ************************************************/
if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
  $client->setAccessToken($_SESSION['access_token']);
} else {
  $authUrl = $client->createAuthUrl();
}

/************************************************
  If we're signed in and have a request to shorten
  a URL, then we create a new URL object, set the
  unshortened URL, and call the 'insert' method on
  the 'url' resource. Note that we re-store the
  access_token bundle, just in case anything
  changed during the request - the main thing that
  might happen here is the access token itself is
  refreshed if the application has offline access.
 ************************************************/
if ($client->getAccessToken()) {
  //Authenticate to twitter
$app_no = 0;
$connection = new TwitterOAuth($config[0]['key'],$config[0]['secret'],$config[0]['access_token'],$config[0]['access_token_secret']);

//Google api key
$googl = $config['googl_api_key'];

//Get long url
$long_url = $config['longurl'];

//Get count
$file = fopen("count.txt","r");
$count = fgets($file);
fclose($file);

//Open twitterlinks file in append mode to write links to
$tco_file = fopen("tco.txt","a+");

$count_file = fopen("count.txt","w");

  $url = new Google_Service_Urlshortener_Url();
  $url->longUrl = $long_url;
  $short = $service->url->insert($url);
  $_SESSION['access_token'] = $client->getAccessToken();
function shorten_url()
{
    global $service,$googl,$long_url,$count_file,$count,$tco_file,$connection,$app_no,$config;

    $urls=array();
    for($i=0; $i<6;$i++){
        $response = null;
        $error = true;
        while($error){
            $url = new Google_Service_Urlshortener_Url();
            $url->longUrl = $long_url;
            $short = $service->url->insert($url);
            var_dump($short);
            echo '<br/>';
            if(empty($short->error) || !empty($short->id)) $error = false;
            else sleep(1);
        }
        $urls[] = $short->id;
        
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
        var_dump($status);
        echo '<br/>';
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

shorten_url();
fclose($tco_file);
fclose($count_file);
}

?>
<div class="box">
  <div class="request">
    <?php if (isset($authUrl)): ?>
      <a class='login' href='<?php echo $authUrl; ?>'>Connect Me!</a>
   
    <?php endif ?>
  </div>

</div>
