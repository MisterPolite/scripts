<?php
/**
 * Firefaucet Bot Script
 * @author @scpwhite
 * @version 1.0
 */
//error_reporting(1);
class Bot {
    private $functions;
    private $user_agent, $cookie;
    private $headers, $header;
    private $host, $maxRetries = 5;
    private $captcha;
    private static $line;
    
    public function __construct($functions) {
        include_once "functions.php";
        $this->captcha = new Captcha(dirname(__FILE__)."/config.json");
        self::$line = Color::$bg.str_repeat("━", 40)."\n";
        $this->functions = $functions;
        $this->user_agent = $this->save("user_agent");
        $this->cookie = $this->save("cookie");
        $this->host = "firefaucet.win";
        $this->setHeader(["cookie: {$this->cookie}", "user-agent: {$this->user_agent}"]);
        $this->getUserInfo();
    }
    
    public function _claim() {
        $this->claim();
    }
    
    public function claim() {
        $this->ptc();
        if($this->isFaucetReady()){
            $this->faucet();
        }
        $this->autofaucet();
        $this->claim();
    }
    
    public function setHeader($parameters = []) {
        $this->headers = array_merge([
            "accept: application/json, text/javascript, */*; q=0.01",
            "content-type: application/x-www-form-urlencoded; charset=UTF-8",
            "x-requested-with: XMLHttpRequest",
            "origin: https://{$this->host}",
            "sec-fetch-site: same-origin",
            "accept-language: en-GB,en-US;q=0.9,en;q=0.8"
        ], $parameters);
        
        $this->header = array_merge([
            'sec-ch-ua: "Chromium";v="134", "Not:A-Brand";v="24", "Google Chrome";v="134"',
            'sec-ch-ua-full-version-list: "Chromium";v="134.0.6998.37", "Not:A-Brand";v="24.0.0.0", "Google Chrome";v="134.0.6998.37"',
            "upgrade-insecure-requests: 1",
            "accept-language: en-US,en;q=0.9",
            "referer: https://{$this->host}/",
            "accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
            "priority: u=1, i"
        ], $parameters);
    }
    private function getUserInfo($try = 0) {
        if($try >= 50) {
            echo "\033[1;31mMax retries reached [50/50]. Cannot get User Info\033[0m\n";
            exit;
        }
        $url = "https://{$this->host}/";
        $data = $this->functions->get($url, $this->header, 1);
        if(str_contains($data, "Login to your existing account")) {
            $this->remove("cookie");
            $this->__construct($this->functions);
        } else if(!$data) {
            $this->functions->timer("Waiting for connection", 5);
            $this->getUserInfo($try + 1);
        }
        $user = $this->user();
        $username = @$this->getStr($data, "<div class=\"username-text\"> "," <a href=\"/settings/\" class=\"settings-icon hide-on-small-only\">");
        $acp_balance = trim(str_replace("ACP", '', $user['acp_balance']));
        $usd_balance = $user['usd_balance'];
        $unseen_ptc = $user['unseen_ptc'];
        echo Color::$bp."[ ".Color::$bg.date("F D Y h:i:s A").Color::$bp." ]\n";
        echo Color::$bw."Username".Color::$br." : ".Color::$bg."{$username}\n";
        echo Color::$bw."ACP".Color::$br." : ".Color::$by."{$acp_balance}\n";
        echo Color::$bw."Balance".Color::$br." : ".Color::$bg."{$usd_balance}\n";
        echo Color::$bw."Ptc".Color::$br." : ".Color::$bc."{$unseen_ptc}\n";
        echo self::$line;
        echo Color::$res;
    }
    public function user(){
        $url = "https://{$this->host}/";
        $data = $this->functions->get($url, $this->header, 1);
        $token = $this->getStr($data, '<input type="hidden" name="csrf_token_sidebar" value="','">');
        if($token){
            $url = "https://firefaucet.win/api/additional-details-dashboard/?sidebar=true";
            $request = "csrf_token={$token}&tz=Asia%2FSingapore&nl=en-GB";
            $data = json_decode($this->functions->post($url, $this->header, $request), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            } else {
                $this->functions->timer("Getting user token", 5);
                return $this->user();
            }
        }
        $this->functions->timer("Getting user token", 5);
        $this->user();
    }
    private function isFaucetReady(){
        $user = $this->user();
        if(str_contains($user['faucet_status'], 'Ready')){
            return true;
        }
        return false;
    }
    private function faucetTimer(){
        $user = $this->user();
        if(str_contains($user['faucet_status'], 'm')){
            $timer = (str_replace('m', '', $user['faucet_status']) * 60) + rand(20, 30);
            $this->functions->timer("Wait For Next Claim", $timer);
        }
    }
    public function ptc(){
        while(true){
            $user = $this->user();
            if($user['unseen_ptc'] <= 0){
                break;
            }
            $url = "https://firefaucet.win/ptc/";
            $data = $this->functions->get($url, $this->header, 1);
            preg_match("/var\s+([a-zA-Z0-9_$]+)\s*=\s*\[\s*'(\d+)'\s*\]/", $data, $ids);
            $id = @$ids[2];
            if(empty($id)){
                preg_match("/new_ptc\('(\d+)'\)/", $data, $ids);
                $id = $ids[1];
            } else{
                echo Color::$bg."Message ".Color::$br." : ".Color::$bw."Somethings wrong with ptc\n";
                echo Color::$bg."Message ".Color::$br." : ".Color::$bw."Contact @scpwhite in telegram\n";
                echo self::$line;
                return;
            }
            $link = "https://firefaucet.win/viewptc?id={$id}";
            $data = $this->functions->get($link, $this->header);
            $time = $this->getStr($data,"var timer = parseInt('","')");
            $csrf = $this->getStr($data,'<input type="hidden" name="csrf_token" value="','">');
            $key = $this->getStr($data,"onclick=continueptc('","')");
            $image = $this->getStr($data,"<img src='data:image/png;base64,","'>");
            $ocr = $this->captcha->ocr($image);
            if($time > 5){
                $this->functions->timer("Viewing ads", $time - 5);
            }
            $link = "https://firefaucet.win/ptcverify?key=$key&id={$id}";
            $text = $ocr['response'];
            $request = "captcha={$text}&csrf_token={$csrf}";
            $data = $this->functions->post($link, $this->header,$request);
            if($data == 'Success'){
                $user = $this->user();
                $acp = trim(str_replace("ACP", '', $user['acp_balance']));
                echo Color::$bg."PTC ".Color::$br."   : ".Color::$bw."PTC claim successfully\n";
                echo Color::$bg."ACP".Color::$br."    : ".Color::$bw."$acp\n";
                echo Color::$bg."Apikey".Color::$br." : ".Color::$bw.$this->captcha->balance()."\n";
                echo self::$line;
            }
            $ids = [];
        }
        echo Color::$bg."No Ptc to view\n";
        echo self::$line;
    }
    private function getSitekeys($data) {
        try{
            $keys = ['hcaptcha' => "", 'turnstile'  => "", 'recaptcha'  => ""];
            preg_match_all('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $data, $hcaptcha);
            if (!empty($hcaptcha[0])) {
                $keys['hcaptcha'] = $hcaptcha[0];
            }
            preg_match_all('/0x[a-zA-Z0-9]{20,}/', $data, $turnstile);
            if (!empty($turnstile[0])) {
                $keys['turnstile'] = $turnstile[0];
            }
            preg_match_all('/6[Le][0-9A-Za-z_-]{38}/', $data, $recaptcha);
            if (!empty($recaptcha[0])) {
                $keys['recaptcha'] = $recaptcha[0];
            }
            return $keys;
        }catch (Exception $e) {}
    }
    function getCaptchaType($data) {
        $available = [];
        try {
            if (preg_match('/var\s+captcha_choices\s*=\s*(\[[^\]]+\])/', $data, $matches)) {
                $json = $matches[1];
                $json = str_replace(['true', 'false'], ['true', 'false'], $json);
                $captchaData = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                foreach ($captchaData as $captcha) {
                    if (!empty($captcha['is_active']) && !empty($captcha['id'])) {
                        $available[] = $captcha['id'];
                    }
                }
            }
        } catch (Exception $e) {}
        return $available;
    }
    
    public function faucet(){
        $url = "https://firefaucet.win/faucet/";
        $data = $this->functions->get($url, $this->header, 1);
        $csrf = $this->getStr($data,'<input type="hidden" name="csrf_token" value="','">');
        $type = $this->getCaptchaType($data)[0];
        $sitekey = $this->getSitekeys($data)[$type];
        try {
            if($type == 'recaptcha'){
                $captcha = $this->captcha->reCaptchaV2($sitekey, $url);
            }
            else if($type == 'hcaptcha'){
                $type = 'recaptcha';
                $captcha = $this->captcha->hcaptcha($sitekey, $url);
            }
            else if($type == 'turnstile'){
                $captcha = $this->captcha->turnstile($sitekey, $url);
            }
            if($captcha['error'] == 0){
                $response = $captcha['response'];
            } else{
                throw new Exception($captcha['message']);
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
            $this->functions->timer("Wait for", 5);
            $this->faucet();
        }
        $request = "csrf_token={$csrf}&selected-captcha={$type}&g-recaptcha-response={$response}&cf-turnstile-response={$response}&h-captcha-response={$response}";
        $link = "https://firefaucet.win/faucet/";
        $data = $this->functions->post($link, $this->header, $request);
        $url = "https://firefaucet.win/faucet/";
        $data = $this->functions->get($url, $this->header);
        $success = strip_tags($this->getStr($data, '<div class="success_msg hoverable"><b>','</div> <br>'));
        if($success){
            echo Color::$bg."Time  ".Color::$br." : ".Color::$bw.Color::$bg.Color::$bg."[ ".Color::$bw.date("h:i:s").Color::$bg." ]\n";
            echo Color::$bg."Faucet".Color::$br." : ".Color::$bw."$success\n";
            echo Color::$bg."Apikey".Color::$br." : ".Color::$bw.$this->captcha->balance()."\n";
            echo self::$line;
            return;
        }
    }
    public function autofaucet() {
        if($this->isFaucetReady()){
            $this->faucet();
        }
        $url = "https://firefaucet.win/";
        $data = $this->functions->get($url, $this->header);
        $csrf = $this->getStr($data, '<input type="hidden" name="csrf_token" value="', '">');
        preg_match_all('#<label for="(.*?)" style="font-weight:400;line-height:20px">#', $data, $tokens);
        if (empty($this->getSave('crypto'))) {
            foreach ($tokens[1] as $key => $val) {
                $cSpace = str_repeat(' ', 2 - strlen($key));
                echo Color::$bw . "[ " . Color::$bc . $key . Color::$bw . " ]" . $cSpace . Color::$br . " : " . Color::$bg . "$val\n";
            }
            echo Color::$bw . "Input crypto number\n";
        }
        while(true){
            $index = $this->save('crypto');
            if (!isset($tokens[1][$index])) {
                echo Color::$br."Invalid crypto selection\n";
                $this->remove("crypto");
            } else{
                break;
            }
        }
    
        $crypto = $tokens[1][$index];
        $startUrl = "https://firefaucet.win/start";
        $request = "csrf_token=$csrf".str_repeat("&coins=$crypto", 12);
        $this->functions->post($startUrl, $this->header, $request);
        $this->functions->get($startUrl, $this->header);
        $this->functions->timer("[ $crypto ] AutoFaucet", 60);
        $url = "https://firefaucet.win/internal-api/payout/";
        $data = json_decode($this->functions->get($url, $this->header), true);
        if (isset($data["message"]) && $data["message"] == "You don't have enough ACP for this auto claim!") {
            echo Color::$bg . "AutoFaucet" . Color::$br . " : " . Color::$bw . "{$data['message']}\n";
            $this->faucetTimer();
            return;
        }
        if (isset($data["balance"])) {
            $logs = $data["logs"][strtoupper($crypto)] ?? 0;
            echo Color::$bg . "Time      " . Color::$br . " : " . Color::$bw . Color::$bg . "[ " . Color::$bw . date("h:i:s") . Color::$bg . " ]\n";
            echo Color::$bg . "AutoFaucet" . Color::$br . " : " . Color::$bw . number_format($logs / 100000000, 8, '.', '') . " $crypto\n";
            echo Color::$bg . "Balance   " . Color::$br . " : " . Color::$bw . $data["balance"] . "\n";
            echo Color::$bg . "Time Left " . Color::$br . " : " . Color::$bw . $data['time_left'] . "\n";
            echo self::$line;
        }
        $this->autofaucet();
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
