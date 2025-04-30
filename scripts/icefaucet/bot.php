<?php
/**
 * Icefaucet by writer
 * @author @scpwhite,
 * @version 1.0
*/
error_reporting(1);

class Bot {
    private $functions;
    private $user_agent, $auth, $refresh;
    private $headers, $header;
    private $host, $maxRetries = 5;
    private $captcha;
    private static $line;
    private $password, $email;
    public function __construct($functions) {
        include_once "functions.php";
        $this->captcha = new FreeCaptcha();
        self::$line = Color::$bg.str_repeat("━", 40)."\n";
        $this->functions = $functions;
        $this->email = $this->save("email");
        $this->password = $this->save("password");
        $this->user_agent = $this->save("user_agent");
        $this->host = "icefaucet.com";
        if(!$this->getSave("refresh")){
            $this->setHeader(["user-agent: {$this->user_agent}"]);
            $this->login();
        }
        $this->refresh = $this->getSave("refresh");
        $this->auth = $this->getSave("access");
        $this->setHeader(["user-agent: {$this->user_agent}", "authorization: Bearer {$this->auth}"]);
        $this->refreshAccess();
        $this->getUserInfo();
    }

    public function _claim() {
        $this->claim();
    }

    public function claim() {
        $this->refreshAccess();
        $this->ptc("view");
        $this->ptc("surf");
        //$this->ptc("video"); // this is currently in test in the web
        $this->auto();
        $this->faucet();
    }

    public function setHeader($parameters = []) {
        $this->headers = array_merge([
            "accept: application/json",
            "content-type: application/json"
        ], $parameters);
        
        $this->header = array_merge([
            "accept: application/json",
            "content-type: application/json",
            "priority: u=1, i"
        ], $parameters);
    }
    private function login($try = 0){
        if($try >= 50) {
            echo "\033[1;31mMax retries reached [50/50].\n";
            exit;
        }
        $url = "https://{$this->host}/api/login/";
        $request = json_encode(["email" => $this->email, "password" => $this->password]);
        $data = json_decode($this->functions->post($url, $this->headers, $request), true);
        if(str_contains($data['detail'][0], 'not registered')){
            echo $data['detail'][0]."\n";
            $this->remove("email");
            $this->save("email");
            $this->login($try + 1);
        } else if(str_contains($data['detail'][0], 'Incorrect password')){
            echo $data['detail'][0]."\n";
            $this->remove("password");
            $this->save("password");
            $this->login($try + 1);
        } else if(str_contains($data['detail'], 'successfully')){
            $url = "https://icefaucet.com/api/token/";
            $data = json_decode($this->functions->post($url, $this->headers, $request), true);
            $this->silentSave("refresh", $data['refresh']);
            $this->silentSave("access", $data['access']);
        }
    }
    private function refreshAccess($try = 0) {
        if($try >= 50) {
            echo "\033[1;31mMax retries reached [50/50].\n";
            exit;
        }
        $url = "https://{$this->host}/api/token/refresh/";
        $request = json_encode(["refresh" => $this->refresh]);
        $response = $this->functions->post($url, $this->headers, $request);
        $data = json_decode($response, true);
        if (isset($data['access']) && isset($data['refresh'])) {
            $this->silentSave("refresh", $data['refresh']);
            $this->silentSave("access", $data['access']);
            $this->refresh = $this->getSave("refresh");
            $this->auth = $this->getSave("access");
            $this->setHeader(["user-agent: {$this->user_agent}", "authorization: Bearer {$this->auth}"]);
        } else {
            $this->refreshAccess($try + 1);
        }
    }
    private function getCaptcha(){
        $url = "https://icefaucet.com/api/captcha/";
        return $this->functions->get($url, $this->header, 1);
    }
    private function submitCaptcha($type, $try = 0){
        $url = "https://icefaucet.com/api/captcha/check/";
        $answer = $this->captcha->__selectCaptcha(json_decode($this->getCaptcha(), true));
        $request = json_encode(["answer" => $answer, "app" => $type]);
        $data = $this->functions->post($url, $this->header, $request, 1, 'info');
        if($data['http_code'] == '200'){
            return true;
        }
        if($try >= 50) {
            echo "\033[1;31mMax retries reached [50/50]. Cannot solved captcha\033[0m\n";
            exit;
        }
        $this->submitCaptcha($type, $try + 1);
    }
    private function getUserInfo($try = 0) {
        if($try >= 50) {
            echo "\033[1;31mMax retries reached [50/50]. Cannot get User Info\033[0m\n";
            exit;
        }
        $url = "https://{$this->host}/api/dashboard/";
        $data = json_decode($this->functions->get($url, $this->header, 1), true);
        if(!$data['info']) {
            $this->functions->timer("Waiting for connection", 5);
            $this->getUserInfo($try + 1);
        }
        $info = $data['info'];
        $user = $info['username'] ? $info['username'] : $info['user_id'];
        $pcoins = $info['pcoin'];
        $icecoin = $info['icecoin'];
        $adcoin = $info['adcoin'];
        echo Color::$bp."[ ".Color::$bg.date("F D Y h:i:s A").Color::$bp." ]\n";
        echo Color::$bw."User [ID]".Color::$br." : ".Color::$by."{$user}".Color::$br."\n";
        echo Color::$bw."Primecoin".Color::$br." : ".Color::$by."{$pcoins}".Color::$br."\n";
        echo Color::$bw."Icecoin".Color::$br."   : ".Color::$by."{$icecoin}".Color::$br."\n";
        echo Color::$bw."Adcoin".Color::$br."    : ".Color::$by."{$adcoin}".Color::$br."\n";
        echo self::$line;
    }
  
    public function faucet($try = 0) {
        if($try >= 50) {
            echo "\033[1;31mMax retries reached [50/50].\n";
            exit;
        }
        $url = "https://icefaucet.com/api/faucet/check-time/";
        $data = json_decode($this->functions->get($url, $this->header), true);
        if(@$data['timer'] > 0) {;
            $this->functions->timer("Wait for next claim", $data['timer']);
            $this->faucet();
        }
        $this->functions->timer("Wait For", 10);
        if($this->submitCaptcha("faucet")){
            $url = "https://icefaucet.com/api/faucet/claim/";
            $data = json_decode($this->functions->get($url, $this->header), true);
            if($data['num_claim']){
                $url = "https://icefaucet.com/api/faucet/values/";
                $data = json_decode($this->functions->get($url, $this->header), true);
                echo Color::$bp."[ ".Color::$bg.date("F D Y h:i:s A").Color::$bp." ]\n";
                echo Color::$bw."Message".Color::$br." : ".Color::$bg."{$data['pcoin']}\n";
                echo self::$line;
                $this->claim();
            }
        }
        $this->faucet($try + 1);
    }
    public function ptc($type, $try = 0){
        if($try >= 50) {
            echo "\033[1;31mMax retries reached [50/50].\n";
            exit;
        }
        $url = "https://icefaucet.com/api/ptc/{$type}/?page=1";
        $data = json_decode($this->functions->get($url, $this->header), true);
        if(isset($data['info'])){
            $info = $data['info'];
            foreach($info as $key => $value){
                if($value['visitable'] == true){
                    $code = $value['code'];
                    if($type == "video"){
                        $url = "https://icefaucet.com/api/ptc/video/start/";
                    } else{
                        $url = "https://icefaucet.com/api/ptc/view/click-rule/";
                    }
                    $request = json_encode(["code" => $code]);
                    $data = json_decode($this->functions->post($url, $this->header, $request), true);
                    $uid_enc = $data['info']['uid_enc'];
                    $token_enc = $data['info']['token_enc'];
                    $url = "https://icefaucet.com/api/ptc/{$type}/{$code}/{$uid_enc}/{$token_enc}/";
                    $data = json_decode($this->functions->get($url, $this->header), true);
                    $duration = $data['duration'];
                    $pcoin = $data['pcoin'];
                    $id = $data['id'];
                    $this->functions->timer("Claiming {$type}", $duration * 10);
                    if($data['need_captcha']){
                        if(!$this->submitCaptcha($type)){
                            $this->ptc($type, $try + 1);
                        }
                    }
                    $url = "https://icefaucet.com/api/ptc/remove-watchers/";
                    $request = json_encode(["code" => $code]);
                    $data = json_decode($this->functions->post($url, $this->header, $request), true);
                    $query = "";
                    if($type != "view"){
                        $query = "{$type}/";
                    }
                    $url = "https://icefaucet.com/api/ptc/done/{$query}";
                    $request = json_encode(["id" => $id]);
                    $data = json_decode($this->functions->post($url, $this->header, $request), true);
                    echo Color::$bp."[ ".Color::$bg.date("F D Y h:i:s A").Color::$bp." ]\n";
                    echo Color::$bw."Message".Color::$br." : ".Color::$bg."You earned {$pcoin} from {$type}\n";
                    echo self::$line;
                }
            }
            echo Color::$bp."[ ".Color::$bg.date("F D Y h:i:s A").Color::$bp." ]\n";
            echo Color::$bw."Message".Color::$br." : ".Color::$bg."No {$type} to claimed\n";
            echo self::$line;
            return;
        }
        $this->ptc($type, $try + 1);
    }
    private function auto($try = 0){
        if($try >= 50) {
            echo "\033[1;31mMax retries reached [50/50].\n";
            exit;
        }
        $url = "https://icefaucet.com/api/ptc/auto/all-info/";
        $data = json_decode($this->functions->get($url, $this->header), true);
        if($data['all_info']['number'] > 0){
            $ads = $data['all_info']['number'];
            for($ads; $ads > 0; $ads--){
                $url = "https://icefaucet.com/api/ptc/auto/start/";
                $data = json_decode($this->functions->get($url, array_merge($this->header, ["referer: https://icefaucet.com/auto"])), true);   
                $uid_enc = $data['info']['uid_enc'];
                $token_enc = $data['info']['token_enc'];
                $url = "https://icefaucet.com/api/ptc/play/{$uid_enc}/{$token_enc}/";
                $data = json_decode($this->functions->get($url, array_merge($this->header, ["referer: {$url}"])), true);
                $pcoin = $data['info']['pcoin'];
                $id = $data['info']['id'];
                $this->functions->timer("Claiming auto-ads", 10);
                if($data['info']['need_captcha']){
                    if(!$this->submitCaptcha("auto-ads")){
                        $this->auto($try + 1);
                    }
                }
                $url = "https://icefaucet.com/api/ptc/done/";
                $request = json_encode(["id" => $id]);
                $data = json_decode($this->functions->post($url, array_merge($this->header, ["referer: {$url}"]), $request), true);
                if(@$data['detail'][0]){
                    echo Color::$bw."Message".Color::$br." : ".Color::$bg.$data['detail'][0]."\n";
                } else{
                    echo Color::$bp."[ ".Color::$bg.date("F D Y h:i:s A").Color::$bp." ]\n";
                    echo Color::$bw."Message".Color::$br." : ".Color::$bg."You earned {$pcoin} from auto-ads\n";
                    echo self::$line;
                }
            }
        }
        echo Color::$bp."[ ".Color::$bg.date("F D Y h:i:s A").Color::$bp." ]\n";
        echo Color::$bw."Message".Color::$br." : ".Color::$bg."No auto-ads to claimed\n";
        echo self::$line;
        return;
    }
    private function silentSave($filename, $data, $filePath = "config.json") {
        return $this->functions->silentSave($filename, $data, dirname(__FILE__).'/'.$filePath);
    }
    private function save($filename, $filePath = "config.json", $defaultValue = null) {
        return $this->functions->save($filename, dirname(__FILE__).'/'.$filePath, $defaultValue);
    }
  
    private function remove($filename, $filePath = "config.json") {
        return $this->functions->remove($filename, dirname(__FILE__).'/'.$filePath);
    }
    public function getSave($filename, $filePath = "config.json", $fallback = null) {
        return $this->functions->getSave($filename, dirname(__FILE__).'/'.$filePath, $fallback = null);
    }

    private function getStr($string, $start, $end, $num = 1) {
        return $this->functions->getStr($string, $start, $end, $num);
    }
}
?>
