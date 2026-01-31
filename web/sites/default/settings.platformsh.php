<?php

// Automatically include Platform.sh settings.
if (getenv('PLATFORM_RELATIONSHIPS')) {
  $relationships = json_decode(base64_decode(getenv('PLATFORM_RELATIONSHIPS')), TRUE);

  if (!empty($relationships['mariadb'])) {
    $db = $relationships['mariadb'][0];
    $databases['default']['default'] = [
      'driver' =>  $db['scheme'],
      'database' => $db['path'],
      'username' => $db['username'],
      'password' => $db['password'],
      'host' => $db['host'],
      'port' => $db['port'],
      'prefix' => '',
    ];
  }
}

//  API Authentication Credentials
$settings['api_token_payload']['agentId'] = 15;
$settings['api_token_payload']['agentPassword'] = '1h&29$vk449f8';
$settings['api_token_payload']['clientId'] = 11281;
$settings['api_token_payload']['clientPassword'] = '6k!Dp$N4';
$settings['api_token_payload']['useTrainingDatabase'] = false;
$settings['api_token_payload']['moduleType'] = ['pointOfSale', 'kiosk'];

$settings['api_baseurl'] = 'https://testrouter.rmscloud.com/testrest';