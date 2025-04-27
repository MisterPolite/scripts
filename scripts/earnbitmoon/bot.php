<?php
/**
 * Earnbitmoon Bot Script
 * @author @scpwhite
 * @version 1.0
 */
error_reporting(1);
class Bot {
    private $functions;
    private $user_agent, $cookie;
    private $headers, $header;
    private $host;
    private $maxRetries = 5;
    private static $line;
    
    public function __construct($functions) {
        self::$line = Color::$bg.str_repeat("━", 40)."\n";
        $this->functions = $functions;
        $this->user_agent = $this->save("user_agent");
        $this->cookie = $this->save("cookie");
        $this->host = "earnbitmoon.club";
        $this->setHeader(["cookie: {$this->cookie}", "user-agent: {$this->user_agent}"]);
        $this->getUserInfo();
    }
    
    public function _claim() {
        $this->claim();
    }
    
    public function claim() {
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
    
    private function getCaptchaImage($t = "dark") {
        $url = "https://{$this->host}/system/libs/captcha/request.php";
        $request = base64_encode(json_encode(["i" => 1, "a" => 1, "t" => $t, "ts" => round(microtime(true) * 1000)]));
        $this->functions->post($url, $this->headers, "payload={$request}");
        
        $request = base64_encode(json_encode(["i" => 1, "ts" => round(microtime(true) * 1000)]));
        $url = "https://{$this->host}/system/libs/captcha/request.php?payload={$request}";
        $image = $this->functions->get($url, $this->header);
        $error = json_decode($image, true);
        if($error['error']){
            $this->functions->timer("Icon Error", 60);
        }
        return $image;
    }

    private function captchaCoordinate() {
        return [20, 60, 100, 140, 180, 220, 260, 300];  // 8 icons
    }
    
    private function submitCaptcha($result) {
        $url = "https://{$this->host}/system/libs/captcha/request.php";
        $request = base64_encode(json_encode(["i" => 1, "x" => $result["x"], "y" => $result["y"], "w" => $result["w"], "a" => 2, "ts" => round(microtime(true) * 1000)]));
        $data = $this->functions->post($url, $this->headers, "payload={$request}", 1, "info");
        if(isset($data["http_code"]) && $data["http_code"] == 200) {
            return true;
        }
        return false;
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
        $coins = @$this->getStr($data, "<b id=\"sidebarCoins\">"," Coins</b></div></div>");
        $value = @$this->getStr($data, "<div class=\"col-9 no-space\">Coins Value <div class=\"text-success\"><b>","</b></div></div>");
        $ads = @$this->getStr($data, '<i class="fas fa-eye"></i> View Ads <span class="badge badge-success ml-1">','</span>');
        
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
        
        $today = $this->getStr($data, ' <div class="col-9 no-space"> Your today claims: <div><a href="/rewards.html" class="text-dark"><b>','</b></a></div></div>');
        $timer = @$this->getStr($data,' $("#claimTime").countdown(',',');
        
        if($timer > 0) {
            $this->functions->timer("Wait for next roll", ($timer / 1000) - round(microtime(true)));
            $this->faucet();
        }
        
        $token = $this->getStr($data, "var token = '","';");
        $link = "https://{$this->host}/system/ajax.php";
        $start = microtime(true);
        
        $this->getCaptchaImage();
        foreach($this->captchaCoordinate() as $index => $x) {
            $result = ["x" => $x, "y" => rand(25, 30), "w" => 320];
            if(!$this->submitCaptcha($result)) {
                echo Color::$br."Invalid Captcha";
                echo "\r                        \r";
            } else {
                $end = microtime(true);
                $coordinate = "{$result["x"]},{$result["y"]},{$result["w"]}"; //format: x,y,w
                $request = "a=getFaucet&token={$token}&captcha=3&challenge=false&response=false&ic-hf-id=1&ic-hf-se={$coordinate}&ic-hf-hp=";
                $data = json_decode($this->functions->post($link, $this->headers, $request), true);
                
                if(isset($data["message"]) && str_contains($data["message"], "Congratulations")) {
                    echo Color::$bp."[ ".Color::$bg.date("F D Y h:i:s A").Color::$bp." ]\n";
                    echo Color::$bw."Message".Color::$br." : ".Color::$bg."lucky number was {$data["number"]} and won ".round($data["reward"])." coins\n";
                    echo Color::$bw."Today".Color::$br." : ".Color::$bg. (int)$today + 1 ." Claims\n";
                    echo Color::$bw."Icon Time".Color::$br." : ".Color::$bg. ($end - $start)."\n";
                    echo Color::$res;
                    echo self::$line;
                    $this->faucet();
                }
            }
        }
        $this->faucet();
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
        $this->getCaptchaImage("light");
        $url = "https://{$this->host}/surf.php?sid={$sid}&key={$key}";
        $data = $this->functions->get($url, $this->header);
        $timer = $this->getStr($data,"var secs = ",";");
        $token = $this->getStr($data,"var token = '","';");
        
        $this->functions->timer("Wait For", $timer);
        $start = microtime(true);
        
        foreach($this->captchaCoordinate() as $index => $x) {
            $result = ["x" => $x, "y" => rand(25, 30), "w" => 320];
            if(!$this->submitCaptcha($result)) {
                echo Color::$br."Invalid Captcha";
                echo "\r                                             \r";
            } else {
                $end = microtime(true);
                $coordinate = "{$result["x"]},{$result["y"]},{$result["w"]}"; //format: x,y,w
                $url = "https://{$this->host}/system/ajax.php";
                $request = "a=proccessPTC&data={$sid}&token={$token}&captcha=3&challenge=false&response=false&ic-hf-id=1&ic-hf-se={$coordinate}&ic-hf-hp=";
                
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