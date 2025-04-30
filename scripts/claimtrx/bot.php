<?php
/**
 * ClaimTrx Bot Script
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
        $this->captcha = new FreeCaptcha();
        self::$line = Color::$bg.str_repeat("━", 40)."\n";
        $this->functions = $functions;
        $this->user_agent = $this->save("user_agent");
        $this->cookie = $this->save("cookie");
        $this->host = "claimtrx.com";
        $this->setHeader(["cookie: {$this->cookie}", "user-agent: {$this->user_agent}"]);
        $this->getUserInfo();
    }
    
    public function _claim() {
        $this->claim();
    }
    
    public function claim() {
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
        $url = "https://{$this->host}/dashboard";
        $data = $this->functions->get($url, $this->header, 1);
        if(str_contains($data, "<a class=\"navbar-links\" href=\"https://{$this->host}/login\">Login</a>")) {
            $this->remove("cookie");
            $this->__construct($this->functions);
        } else if(!$data) {
            $this->functions->timer("Waiting for connection", 5);
            $this->getUserInfo($try + 1);
        }
        $coins = @$this->getStr($data, "<h2 class=\"mb-3 font-18\">","</h2>");
        echo Color::$bp."[ ".Color::$bg.date("F D Y h:i:s A").Color::$bp." ]\n";
        echo Color::$bw."Balance ".Color::$br." : ".Color::$by."{$coins}".Color::$br."\n";
        echo self::$line;
        echo Color::$res;
    }
    
    public function faucet($try = 0) {
        if($try >= 50) {
            echo "\033[1;31mMax retries reached [50/50].\n";
            exit;
        }
        $url = "https://{$this->host}/faucet";
        $data = $this->functions->get($url, $this->header, 1);
        if(!$data) {
            $this->functions->timer("Waiting for connection", 5);
            $this->faucet($try + 1);
        }
        $timer = @$this->getStr($data,'var wait = ',' - 1;');
        if($timer > 0) {
            $this->functions->timer("Wait for next claim", $timer);
            $this->faucet();
        }
        if(str_contains($data, 'https://www.google.com/recaptcha/api.js?')){
            echo Color::$bw."Message".Color::$br." : ".Color::$bg."Website has RecaptchaV3\n";
            exit();
        }
        $csrf = $this->getStr($data, '<input type="hidden" name="csrf_token_name" id="token" value="','">');
        $token = $this->getStr($data, '<input type="hidden" name="token" value="','">');
        $image = $this->getStr($data, '<img id="Imageid" src="','"');
        $value  =$this->getStr($data, '<input type="number" class="form-control border border-dark mb-3" name="','" value="">');
        if($image && $value){
            $images = $image ? $this->functions->get($image, $this->headers) : $this->faucet($try + 1);
            $gif = "";
            try {
                file_put_contents(dirname(__FILE__)."/images.gif", $images);
                $gifs = $this->captcha->__giftotext(dirname(__FILE__).'/images.gif');
                foreach ($gifs as $gif) {
                    $gif = str_replace("\n", "", $gif);
                    if (strlen($gif) >= 4 && ctype_digit($gif)) {
                        break;
                    }
                }
            } catch (Exception $e) {
                echo "Error: " . $e->getMessage()."\n";
                $this->functions->timer("Wait for", 5);
                $this->faucet($try + 1);
            }
        }
        $link = "https://{$this->host}/faucet/verify";
        $request = "csrf_token_name={$csrf}&token={$token}&{$value}={$gif}";
        $data = $this->functions->post($link, $this->headers, $request, 1);
        if(str_contains($data, 'success')) {
            $message = $this->getStr($data, "title: '","'");
            echo Color::$bp."[ ".Color::$bg.date("F D Y h:i:s A").Color::$bp." ]\n";
            echo Color::$bw."Message".Color::$br." : ".Color::$bg."{$message}\n";
            echo Color::$res;
            echo self::$line;
            $this->faucet();
        }
        $this->faucet();
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
