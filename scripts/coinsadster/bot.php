<?php
/**
 * Coinadster Bot Script
 * @author @scpwhite
 * @version 1.0
 */
error_reporting(1);
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
        $this->host = "coinadster.com";
        $this->setHeader(["cookie: {$this->cookie}", "user-agent: {$this->user_agent}"]);
        $this->getUserInfo();
    }
    
    public function _claim() {
        $this->claim();
    }
    
    public function claim() {
        $this->dailyClaim();
        $this->ptc();
        $this->faucet();
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
        
        if(str_contains($data, "Already registered? Click here to login!")) {
            $this->remove("cookie");
            $this->__construct($this->functions);
        } else if(!$data) {
            $this->functions->timer("Waiting for connection", 5);
            $this->getUserInfo($try + 1);
        }
        
        $username = @$this->getStr($data, "<font class=\"text-success\">","</font><br />");
        $coins = @$this->getStr($data, "<div class=\"col-9 no-space\">Account Balance <div class=\"text-primary\"><b>"," Bits</b></div></div>	           ");
        $value = @$this->getStr($data, "<div class=\"col-9 no-space\">Current Bits Value <div class=\"text-warning\"><b>","</b></div></div>	  ");
        $ads = @$this->getStr($data, '</i> PTC Ads <span class="badge badge-info">','</span></a>');
        echo Color::$bp."[ ".Color::$bg.date("F D Y h:i:s A").Color::$bp." ]\n";
        echo Color::$bw."Username".Color::$br."    : ".Color::$bw."{$username}\n";
        echo Color::$bw."Coins/Value".Color::$br." : ".Color::$by."{$coins}".Color::$br." / ".Color::$bg."{$value}\n";
        echo Color::$bw."Ptc ads".Color::$br."     : ".Color::$by."{$ads}\n";
        echo self::$line;
        echo Color::$res;
    }
    
    public function faucet($try = 0) {
        if($try >= 50) {
            echo "\033[1;31mMax retries reached [50/50]. Cannot fetch faucet\033[0m\n";
            exit;
        }
        $url = "https://{$this->host}/ptc.html";
        $data = $this->functions->get($url, $this->header);
        if(!str_contains($data, 'There is no website available yet!')) {
            $this->ptc();
        }
        $url = "https://{$this->host}/";
        $data = $this->functions->get($url, $this->header, 1);
        if(!$data) {
            $this->functions->timer("Waiting for connection", 5);
            $this->faucet($try + 1);
        }
        $today = $this->getStr($data, '<div class="col-9 no-space"> Your today claims: <div><a href="/rewards.html" class="text-dark"><b>','</b></a></div></div>');
        $timer = @$this->getStr($data,'var countDownDate = ',';');
        $turnskey = $this->getStr($data, '<div class="cf-turnstile" data-sitekey="','"></div>');
        $hkey = $this->getStr($data, '<div class="h-captcha" data-sitekey="','"></div>');
        $newTimer = ($timer / 1000) - round(microtime(true));
        if($newTimer > 0) {;
            $this->functions->timer("Wait for next roll", $newTimer);
            $this->faucet();
        }
        $csrf_token = $this->getStr($data, '<input type="hidden" name="csrf_token" value="','">');
        $value = $this->getStr($data, '<input type="text" name="','" style="display:none;" autocomplete="off">');
        $ts = $this->getStr($data, '<input type="hidden" name="ts" value="','">');
        $response = "";
        if($turnskey){
            try {
                $captcha = $this->captcha->turnstile($turnskey, $url);
                if($captcha['response']){
                    $response = $captcha['response'];
                    $cap = "cf-turnstile-response={$response}&g-recaptcha-response={$response}&h-captcha-response={$response}";
                } else{
                    throw new Exception($captcha['message']);
                }
            } catch (Exception $e) {
                echo "Error: " . $e->getMessage();
                $this->functions->timer("Wait for", 5);
                $this->faucet();
            }
        } else if($hkey){
            try {
                $captcha  = $this->captcha->hcaptcha($hkey, $url);
                if($captcha['response']){
                    $response = $captcha['response'];
                    $cap = "cf-turnstile-response={$response}&g-recaptcha-response={$response}&h-captcha-response={$response}";
                } else{
                    throw new Exception($captcha['message']);
                }
            } catch (Exception $e) {
                echo "Error: " . $e->getMessage();
                $this->functions->timer("Wait for", 5);
                $this->faucet();
            }
        } else{
            $this->functions->timer("Claiming bits and ticket", 5);
        }
        $link = "https://{$this->host}";
        $request = "ts={$ts}&{$value}=&csrf_token={$csrf_token}&website=&csrf_token={$csrf_token}&{$cap}&claim_faucet=";
        $data = $this->functions->post($link, array_merge($this->header, ["referer: https://coinadster.com/"]), $request, 1);
        if(str_contains($data, 'You have successfully claimed')) {
            $message = $this->getStr($data, "<div id=\"messageArea\"><div class='alert alert-success'>","</div></div>");
            echo Color::$bp."[ ".Color::$bg.date("F D Y h:i:s A").Color::$bp." ]\n";
            echo Color::$bw."Message".Color::$br." : ".Color::$bg."{$message}\n";
            echo Color::$bw."Balance".Color::$br." : ".Color::$bg.$this->captcha->balance()."\n";
            echo Color::$res;
            echo self::$line;
            $this->faucet();
        }
        $this->faucet();
    }
    public function dailyClaim(){
        $url = "https://{$this->host}/daily_bonus.html";
        $data = $this->functions->get($url, $this->header);
        if(str_contains($data, ' You can claim your next reward at the next reset.')){
            echo Color::$bp."[ ".Color::$bg.date("F D Y h:i:s A").Color::$bp." ]\n";
            echo Color::$bw."Message".Color::$br." : ".Color::$bg."Daily bonus already claimed\n";
            echo self::$line;
            return;
        }
        $request = "claim_daily_reward=";
        $data = $this->functions->post($url, $this->headers, $request);
        if(str_contains($data, 'You have successfully claimed')){
            $bits = $this->getStr($data, 'You have successfully claimed ',' bits!');
            echo Color::$bp."[ ".Color::$bg.date("F D Y h:i:s A").Color::$bp." ]\n";
            echo Color::$bw."Message".Color::$br." : ".Color::$bg."You have successfully claimed {$bits} as a bonus.\n";
            echo self::$line;
            return;
        }
    }
    
    public function ptc($try = 0) {
        if($try >= 50) {
            echo "\033[1;31mMax retries reached [50/50]. Cannot fetch PTC\033[0m\n";
            exit;
        }
        $url = "https://{$this->host}/ptc.html";
        $data = $this->functions->get($url, $this->header);
        if(str_contains($data, 'There is no website available yet!')) {
            echo Color::$bw."PTC ".Color::$bg.":".Color::$br." There is no website available yet!\n";
            echo Color::$res;
            echo self::$line;
            return;
        }
        $sid = @$this->getStr($data,'<div class="website_block" id="','">');
        $key = @$this->getStr($data,"childWindow = open(base + '/surf.php?sid=' + a + '&key=","', b);");
        
        if(empty($sid) || empty($key)) {
            return;
        }
        $url = "https://{$this->host}/surf.php?sid={$sid}&key={$key}";
        $data = $this->functions->get($url, $this->header);
        $timer = $this->getStr($data,"var secs = ",";");
        $token = $this->getStr($data,"var token = '","';");
        $this->functions->timer("Wait For", $timer);
        $start = microtime(true);
        $link = "https://coinadster.com/system/libs/captcha/request.php";
        $request = "cID=0&rT=1&tM=light";
        $data = json_decode($this->functions->post($link, $this->headers, $request), true);
        foreach($data as $solved){
            $end = microtime(true);
            $url = "https://{$this->host}/system/ajax.php";
            $request = "a=proccessPTC&data={$sid}&token={$token}&captcha-idhf=0&captcha-hf={$solved}";
            $response = $this->functions->post($url, $this->headers, $request);
            $data = json_decode(strip_tags($response), true);
            if(isset($data["status"]) && $data["status"] == 200) {
                echo Color::$bp."[ ".Color::$bg.date("F D Y h:i:s A").Color::$bp." ]\n";
                echo Color::$bw."Message".Color::$br."   : ".Color::$g.trim($data["message"])."\n";
                echo Color::$bw."Ptc Timer".Color::$br." : ".Color::$g.$timer."\n";
                echo Color::$bw."Redirect".Color::$br."  : ".Color::$g.$data["redirect"]."\n";
                echo Color::$bw."Icon Time".Color::$br." : ".Color::$bg. ($end - $start)."\n";
                echo Color::$res;
                echo self::$line;
                $this->ptc();
            }
        }
        
    }
    private function save($filename, $filePath = "config.json", $defaultValue = null) {
        return $this->functions->save($filename, dirname(__FILE__).'/'.$filePath, $defaultValue);
    }
    
    private function remove($filename, $filePath = "config.json") {
        return $this->functions->remove($filename, dirname(__FILE__).'/'.$filePath);
    }
    
    private function getStr($string, $start, $end, $num = 1) {
        return $this->functions->getStr($string, $start, $end, $num);
    }
}
?>