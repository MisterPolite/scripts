<?php
/**
 * Script list by writer
 * @author @scpwhite,
 * @version 1
*/
define("APP_VERSION", "1.0");
define("DEFAULT_SCRIPT_DIR", "scripts");
define("LOG_FILE", "logs.log");
define("GITHUB_REPO_URL", "https://github.com/script");
define("COLOR_PRIMARY", "\033[38;2;41;128;255m");
define("COLOR_SECONDARY", "\033[38;2;0;255;127m");
define("COLOR_ACCENT", "\033[38;2;255;69;0m");
define("COLOR_INFO", "\033[38;2;30;144;255m");
define("COLOR_WARNING", "\033[38;2;255;165;0m");
define("COLOR_RESET", "\033[0m");
define("COLOR_SUCCESS", "\033[1;32m");
class FreeCaptcha{  
    public function __imagetotext($path){
        $tesseractCommand = "tesseract $path stdout -l eng --psm  --oem 1 -c tessedit_char_whitelist=\"ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789\"";
        $result = shell_exec($tesseractCommand);
        return $result;
    }
    public function __giftotext($path) {
        $gifPath = $path;
        $frameDir = "images/";
        
        if (!file_exists($frameDir)) {
            mkdir($frameDir);
        }
        $cmd = "ffmpeg -i $gifPath {$frameDir}frame_%03d.png 2>nul";
        shell_exec($cmd);
        $frameFiles = glob($frameDir . "*.png");
        $results = [];
        foreach ($frameFiles as $frameFile) {
            $ocr = shell_exec("tesseract $frameFile stdout --psm 11 -c tessedit_char_whitelist=0123456789 2>nul");
            $ocr = @trim($ocr);
            if (!empty($ocr)) {
                $results[] = $ocr;
            }
            unlink($frameFile);
        }
        @unlink(@trim($path, "'\""));
        @rmdir('images'); 
        @unlink($path);
        return $results;
    }
    public function __selectCaptcha(array $json) {
        $question = $json['data']['question'] ?? null;
        if (!$question) {
            return;
        }
        if (strpos($question, 'displays the') !== false && isset($json['data']['choices_a'])) {
            preg_match('/displays the ([a-z]+)/i', $question, $matches);
            $keyword = $matches[1] ?? null;
            foreach ($json['data']['choices_a'] as $choice) {
                if (isset($choice['alt']) && strtolower($choice['alt']) === strtolower($keyword)) {
                    return $keyword;
                }
            }
            return;
        }
      
        if (isset($json['data']['choices_bc'])) {
            $knownAnswers = [
                "circle,triangle,square,refrigerator" => "refrigerator",
                "germany,italy,night,spain" => "night",
                "light,left,right,down" => "light",
                "run,black,watch,make" => "black",
                "sun,cloud,rain,train" => "train",
                "car,way,bus,truck" => "way",
                "false,true,silent,tree" => "silent",
                "cook,true,silent,tree" => "boy",
                "lion,eagle,sparrow,pigeon" => "lion",
                "tired,view,verdict,pretty" => "pretty",
                "rest,love,hope,best" => "love",
                "wait,wear,warm,warn" => "warn",
                "run,laugh,play,orange" => "orange",
                "ugly,awesome,pretty,good" => "ugly",
                "artificial,art,air,strange" => "artificial",
                "mountain,sun,clock,river" => "clock",
                "cold,cloud,fire,water" => "cold",
                "Germany,Italy,Night,Spain" => "Night",
                "girl,mother,doughter,plane" => "plane",
                "run,walk,talk,awake" => "awake",
                "cook,wake,boy,look" => "boy",
                "late,never,always,bad" => "bad",
                "perhaps,usual,never,secure" => "secure",
                "football,volleyball,tower,track and field" => "tower",
                "perform,cut,slash,break" => "perform",
                "pen,eraser,potato,paper" => "potato",  
                "fire,brown,gray,purple" => "fire",
                "glass,cucumber,coconut,banana" => "glass",
                "freedom,precision,draft,huge" => "huge",
            ];
      
            $choices = array_map(fn($c) => strtolower($c['choice']), $json['data']['choices_bc']);
            $choicesKey = implode(",", $choices);
            if (isset($knownAnswers[$choicesKey])) {
                $answer = $knownAnswers[$choicesKey];
                return $answer;
            } else {
                return;
            }
        }
        return;
      }
}
class Captcha{
    static private $url;
    static private $key;
    static private $max_attempts = 100;
    static private $sleep = 5;

    private $function;

    private $errorMessages = [
        'ERROR_METHOD_DOES_NOT_EXIST' => 'The captcha type is incorrectly specified.',
        'WRONG_METHOD' => 'The captcha type is not specified.',
        'ERROR_BAD_DATA' => 'One or more parameters were not passed.',
        'ERROR_WRONG_USER_KEY' => 'The API key is invalid or not found.',
        'ERROR_ZERO_BALANCE' => 'No balance available. Top up your account.',
        'WRONG_COUNT_IMG' => 'Less than three images were sent for "antibot" method.',
        'CAPCHA_NOT_READY' => 'Captcha is not ready yet.',
        'WRONG_CAPTCHA_ID' => 'Captcha ID not found.',
        'WRONG_REQUESTS_LINK' => 'Wrong request link.',
        'WRONG_LOAD_PAGEURL' => 'Invalid "pageurl" parameter format.',
        'ERROR_SITEKEY' => 'Error with sitekey.',
        'SITEKEY_IS_INCORRECT' => 'Invalid sitekey.',
        'WRONG_RESULT' => 'Service could not solve the captcha.',
        'HCAPTCHA_NOT_FOUND' => 'hCaptcha was not found on the page.',
        'TURNSTILE_NOT_FOUND' => 'Turnstile captcha not found on the page.',
        'ERROR_CAPTCHA_UNSOLVABLE' => 'Captcha could not be solved.',
        'ERROR_BAD_REQUEST' => 'Bad request was made.',
        'ERROR_KEY_DOES_NOT_EXIST' => 'API Key does not exist.',
    ];
    private $filePath;
    public function __construct($filePath = "config.json"){
        $this->function = new Functions();
        $this->filePath = $filePath;
        if (empty($this->function->getSave('type', $this->filePath))) {
            $this->showMenu();
            $this->function->save("type", $this->filePath);
        }
        if ($this->function->save('type', $this->filePath) == 1) {
            self::$url = 'http://api.multibot.in/';
            self::$key = $this->function->save('multibot_key', $this->filePath);
        } else{
            self::$url = 'http://api.sctg.xyz/';
            self::$key = $this->function->save('xevil_apikey',  $this->filePath);
        }
    }
    private function showMenu(){
        echo "\n";
        echo COLOR_PRIMARY . str_repeat("═", 50) . COLOR_RESET . "\n";
        echo COLOR_ACCENT . "             Welcome to Captcha            " . COLOR_RESET . "\n";
        echo COLOR_PRIMARY . str_repeat("═", 50) . COLOR_RESET . "\n";
        echo COLOR_INFO . " Select Captcha Solving Service: \n" . COLOR_RESET;
        echo COLOR_SECONDARY . " [1] " . COLOR_RESET . "Multibot\n";
        echo COLOR_SECONDARY . " [2] " . COLOR_RESET . "Xevil\n";
        echo COLOR_WARNING . "\n Please input number only (1 or 2)\n" . COLOR_RESET;
        echo COLOR_PRIMARY . str_repeat("═", 50) . COLOR_RESET . "\n\n";
    }

    public function balance(){
        if (self::$url == 'http://api.sctg.xyz/') {
            $balance = $this->function->get('https://api.sctg.xyz/res.php?key=' . self::$key . '&action=getbalance', []);
        } else {
            $balanceData = json_decode($this->function->get('http://api.multibot.in/res.php?key=' . self::$key . '&action=userinfo', []), true);
            $balance = $balanceData['balance'] ?? 0;
        }
        return $balance;
    }

    private function requestCaptchaSolution(array $fields){
        if (empty($fields)) {
            return false;
        }
        $fields['key'] = self::$key;
        $fields['json'] = 1;
        $url = self::$url . 'in.php';
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fields
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
    }
    public function getResult($captchaID){
        $data = [
            'key' => self::$key,
            'id' => $captchaID,
            'json' => 1
        ];
        $url = self::$url . 'res.php?' . http_build_query($data);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response, true);
    }
    private function handleError($errorCode){
        if (isset($this->errorMessages[$errorCode])) {
            return $this->errorMessages[$errorCode];
        }
        return "Unknown error: $errorCode";
    }
    private function getCaptchaSolution(array $requestInfo, string $type){
        $requestCaptchaID = $this->requestCaptchaSolution($requestInfo);
        if ($requestCaptchaID === false) {
            return ['error' => true, 'message' => 'Failed to request captcha.', 'response' => []];
        }
        if ($requestCaptchaID['status'] === 0) {
            return ['error' => true, 'message' => $this->handleError($requestCaptchaID['request']), 'response' => []];
        }
        $captchaID = $requestCaptchaID['request'];
        for ($i = 0; $i < self::$max_attempts; $i++) {
            $result = $this->getResult($captchaID);
            if ((int)$result['status'] === 0) {
                $errorMessage = $this->handleError($result['request']);
                if (in_array($result['request'], ['ERROR_CAPTCHA_UNSOLVABLE', 'ERROR_WRONG_CAPTCHA_ID', 'ERROR_BAD_REQUEST', 'WRONG_RESULT'])) {
                    return ['error' => true, 'message' => $errorMessage, 'response' => []];
                }
                if ($result['request'] === 'ERROR_KEY_DOES_NOT_EXIST') {
                    exit("API Key error: {$errorMessage}\n");
                }
                $this->function->timer("[$i]/".self::$max_attempts." ".$result['request']." {$type}", self::$sleep);
                continue;
            }
            if ((int)$result['status'] === 1) {
                return ['error' => false, 'message' => '', 'response' => $result['request']];
            }
        }
        return ['error' => true, 'message' => 'Failed to retrieve captcha result.', 'response' => []];
    }

    public function hcaptcha($sitekey = '', $pageurl = ''){
        if (empty($sitekey) || empty($pageurl)) {
            return ['error' => true, 'message' => 'Required parameters not provided.', 'response' => []];
        }
        $requestInfo = ['method' => 'hcaptcha', 'sitekey' => $sitekey, 'pageurl' => $pageurl ];
        return $this->getCaptchaSolution($requestInfo, "Hcaptcha");
    }

    public function reCaptchaV2($googlekey = '', $pageurl = ''){
        if (empty($googlekey) || empty($pageurl)) {
            return ['error' => true, 'message' => 'Required parameters not provided.', 'response' => []];
        }
        $requestInfo = [
            'method' => 'userrecaptcha',
            'googlekey' => $googlekey,
            'pageurl' => $pageurl
        ];
        return $this->getCaptchaSolution($requestInfo, "RecaptchaV2");
    }
    public function turnstile($sitekey = '', $pageurl = ''){
        if (empty($sitekey) || empty($pageurl)) {
            return ['error' => true, 'message' => 'Required parameters not provided.', 'response' => []];
        }
        $requestInfo = [
            'method' => 'turnstile',
            'sitekey' => $sitekey,
            'pageurl' => $pageurl
        ];
        return $this->getCaptchaSolution($requestInfo, "Turnstile");
    }
    public function antiBot(array $images = []){
        if (count($images) < 3) {
            return ['error' => true, 'message' => 'Antibot requires at least 3 images.', 'response' => []];
        }
        $requestInfo = ['method' => 'antibot'] + $images;
        return $this->getCaptchaSolution($requestInfo, "Antibot");
    }
    public function ocr($image){
        if(empty($image))
            return ['error' => true,'message' => 'required parameters not found', 'response' => []];
        if (self::$url == 'http://api.sctg.xyz/') {
            $method = 'base64';
        } else{
            $method = 'universal';
        }
        $requestInfo = ['method' => $method, 'body' => $image];
        return $this->getCaptchaSolution($requestInfo, "OCR");
    }
}

class Functions {
    private $file;
    private $defaultCurlOptions;
    private $maxRetries = 5;
    public function __construct($file = "cookie.txt") {
        $this->file = $file;
    }
    public function curl($url, $header, $method = "GET", $follow = 1, $request = null) {
        $data = ["data" => null, "info" => null, "error" => null, "header" => null];
        $link = parse_url($url, PHP_URL_HOST);
        $retryCount = 0;
        while ($retryCount < 5) {
            $ch = curl_init();
            $options = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => $follow,
                CURLOPT_COOKIEJAR => $this->file,
                CURLOPT_COOKIEFILE => $this->file,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HEADER => true,
                CURLOPT_TIMEOUT => 60
            ];
            if (parse_url($url, PHP_URL_HOST) == "earnbitmoon.club") {
                $options[CURLOPT_SSLVERSION] = CURL_SSLVERSION_TLSv1_3;
                $options[CURLOPT_SSL_CIPHER_LIST] = 'TLS_AES_128_GCM_SHA256:ECDHE-RSA-AES128-GCM-SHA256';
            }
            if (strtoupper($method) === "POST") {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = $request;
            }
            curl_setopt_array($ch, $options);
            $res = curl_exec($ch);
            $curl_error = curl_errno($ch);
            if ($curl_error) {
                $data["error"] = curl_error($ch);
            } else {
                $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $data["data"] = substr($res, $header_size);
                $data["header"] = substr($res, 0, $header_size);
                $data["info"] = curl_getinfo($ch);
            }
            curl_close($ch);
            if (!$curl_error && !empty($data['info']['url'])) {
                return $data;
            }
            if ($curl_error) {
                switch ($curl_error) {
                    case CURLE_COULDNT_CONNECT:
                        break;
                    case CURLE_TOO_MANY_REDIRECTS:
                    case CURLE_OPERATION_TIMEDOUT:
                        return null;
                    case CURLE_HTTP_RETURNED_ERROR:
                        $http_code = $data["info"]["http_code"];
                        switch ($http_code) {
                            case 500: $this->timer("{$link} HTTP CODE: 500 (Internal Server Error)", 10);
                                break;
                            case 502: $this->timer("{$link} HTTP CODE: 502 (Bad Gateway)", 10);
                                break;
                            case 503: $this->timer("{$link} HTTP CODE: 503 (Service Unavailable)", 10);
                                break;
                            case 521: $this->timer("{$link} HTTP CODE: 521 (Web Server Is Down)", 10);
                                break;
                            case 522: $this->timer("{$link} HTTP CODE: 522 (Connection Timed Out)", 10);
                                break;
                            default: return false;
                        }
                        break;
                    default: return false;
                }
            }
            $retryCount++;
        }
        return $data;
    }
    public function timer($text, $seconds, $animStyle = 'bar', $colorScheme = 'ocean', $showMilliseconds = false) {
        $colors = [
            "black" => "\033[0;30m", "red" => "\033[0;31m", 
            "green" => "\033[0;32m", "yellow" => "\033[0;33m",
            "blue" => "\033[0;34m", "magenta" => "\033[0;35m", 
            "cyan" => "\033[0;36m", "white" => "\033[0;37m",
            "bblack" => "\033[1;30m", "bred" => "\033[1;31m", 
            "bgreen" => "\033[1;32m", "byellow" => "\033[1;33m",
            "bblue" => "\033[1;34m", "bmagenta" => "\033[1;35m", 
            "bcyan" => "\033[1;36m", "bwhite" => "\033[1;37m",
            "bg_black" => "\033[40m", "bg_red" => "\033[41m",
            "bg_green" => "\033[42m", "bg_yellow" => "\033[43m",
            "bg_blue" => "\033[44m", "bg_magenta" => "\033[45m",
            "bg_cyan" => "\033[46m", "bg_white" => "\033[47m",
            "reset" => "\033[0m", "bold" => "\033[1m",
            "underline" => "\033[4m", "blink" => "\033[5m",
            "reverse" => "\033[7m", "concealed" => "\033[8m"
        ];
    
        if ($seconds <= 0) {
            return 0;
        }
    
        $animations = [
            'dots' => ['•', '••', '•••', '••••', '•••••'],
            'spinner' => ['|', '/', '—', '\\'],
            'bar' => ['[□□□□□]', '[■□□□□]', '[■■□□□]', '[■■■□□]', '[■■■■□]', '[■■■■■]'],
            'smallbar' => ['[▫▫▫▫▫]', '[▪▫▫▫▫]', '[▪▪▫▫▫]', '[▪▪▪▫▫]', '[▪▪▪▪▫]', '[▪▪▪▪▪]'],
            'blockbar' => ['[▒▒▒▒▒]', '[█▒▒▒▒]', '[██▒▒▒]', '[███▒▒]', '[████▒]', '[█████]'],
            'arrow' => ['[>    ]', '[=>   ]', '[==>  ]', '[===> ]', '[====>]'],
            'circular' => ['◜', '◝', '◞', '◟'],
            'pulse' => ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█', '▇', '▆', '▅', '▄', '▃', '▁']
        ];
        $animFrames = $animations[$animStyle] ?? $animations['dots'];
    
        $colorSchemes = [
            'rainbow' => ['red', 'yellow', 'green', 'cyan', 'blue', 'magenta'],
            'matrix' => ['green', 'bgreen'],
            'fire' => ['red', 'yellow', 'bred'],
            'ocean' => ['blue', 'cyan', 'bblue', 'bcyan'],
            'random' => array_keys(array_filter($colors, function($key) {
                return !strpos($key, 'bg_') && $key != 'reset' && 
                    !in_array($key, ['bold', 'underline', 'blink', 'reverse', 'concealed']);
            }, ARRAY_FILTER_USE_KEY))
        ];
        $selectedScheme = $colorSchemes[$colorScheme] ?? $colorSchemes['rainbow'];
    
        $colorIndex = 0;
        $startTime = microtime(true);
        $endTime = $startTime + $seconds;
        $lastUpdateTime = -1;
    
        while (true) {
            $now = microtime(true);
            if ($now >= $endTime) {
                $remaining = 0;
                $elapsed = $seconds;
                $percentage = 100;
            } else {
                $remaining = $endTime - $now;
                $elapsed = $seconds - $remaining;
                $percentage = min(100, ($elapsed / $seconds) * 100);
            }
            
            if ($remaining < 60) {
                if ($showMilliseconds) {
                    $timeDisplay = sprintf("%.1f seconds", $remaining);
                } else {
                    $timeDisplay = floor($remaining) . " seconds";
                }
            } elseif ($remaining < 3600) {
                $timeDisplay = gmdate("i:s", (int)$remaining);
            } else {
                $timeDisplay = gmdate("H:i:s", (int)$remaining);
            }
    
            $currentColor = $colors[$selectedScheme[$colorIndex]];
            $frameIndex = min(count($animFrames) - 1, floor(($percentage / 100) * count($animFrames)));
            $progressBar = $currentColor . $animFrames[$frameIndex] . $colors['reset'];
            
            $percentText = sprintf("%.1f%%", $percentage);
            $message = $colors['bold'] . $text . $colors['reset'] . ' ' .
                    $colors['bwhite'] . $timeDisplay . $colors['reset'] . ' ' .
                    $progressBar . ' ' .
                    $colors['byellow'] . $percentText . $colors['reset'];
    
            echo $message;
            if ((int)floor($remaining) !== $lastUpdateTime) {
                $colorIndex = ($colorIndex + 1) % count($selectedScheme);
                $lastUpdateTime = (int)floor($remaining);
            }
            $clearLength = mb_strlen(preg_replace('/\033\[[^m]*m/', '', $message)) + 10;
            sleep(1);
            echo "\r" . str_repeat(" ", $clearLength) . "\r";
            if ($now >= $endTime) {
                break;
            }
        }
        return 0;
    }
    
    public function get($link, $header = [], $follow = 1, $return = "data") {
        $response = $this->curl($link, $header, "GET", $follow);
        return $return == "all" ? $response : ($response[$return] ?? null);
    }
    
    public function post($link, $header = [], $request = "" , $follow = 1, $return = "data") {
        $response = $this->curl($link, $header, "POST", $follow, $request);
        return $return == "all" ? $response : ($response[$return] ?? null);
    }

    public function request($method, $link, $header = [], $request = null, $follow = 1, $return = "data") {
        $response = $this->curl($link, $header, strtoupper($method), $follow, $request);
        return $return == "all" ? $response : ($response[$return] ?? null);
    }

    public function getConfig($filePath = "config.json") {
        if (!file_exists($filePath)) {
            return [];
        }
        
        $config = @file_get_contents($filePath);
        if ($config === false) {
            throw new RuntimeException("Failed to read configuration file: {$filePath}");
        }
        
        $decoded = json_decode($config, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON format in configuration file: {$filePath} - " . json_last_error_msg());
        }
        
        return $decoded;
    }

    public function saveConfig($filePath, $config) {
        if (!is_array($config)) {
            throw new InvalidArgumentException("Configuration must be an array");
        }
        $data = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($data === false) {
            throw new RuntimeException("Failed to encode configuration data: " . json_last_error_msg());
        }
        
        $tempFile = $filePath . '.tmp.' . uniqid();
        if (file_put_contents($tempFile, $data) === false) {
            throw new RuntimeException("Failed to write configuration file: {$tempFile}");
        }
        if (!rename($tempFile, $filePath)) {
            @unlink($tempFile);
            throw new RuntimeException("Failed to update configuration file: {$filePath}");
        }
        
        return true;
    }

    public function remove($filename, $filePath = "config.json") {
        $config = $this->getConfig($filePath);
        if (isset($config[$filename])) {
            unset($config[$filename]);
            $this->saveConfig($filePath, $config);
            echo "\033[1;32mKey '$filename' removed successfully from {$filePath}.\033[0m\n";
            return true;
        } else {
            echo "\033[1;31mError: Key '$filename' not found in {$filePath}.\033[0m\n";
            return false;
        }
    }

    public function save($filename, $filePath = "config.json", $defaultValue = null) {
        $config = $this->getConfig($filePath);
        if (isset($config[$filename]) && !empty($config[$filename])) {
            return $config[$filename];
        }
        
        $data = readline("\033[1;37mInput $filename: \033[0m");
        if (empty($data)) {
            $data = $defaultValue;
        }
        
        if ($data !== null) {
            $config[$filename] = $data;
            $this->saveConfig($filePath, $config);
            echo "\033[1;32mKey '$filename' saved successfully to {$filePath}.\033[0m\n";
        }
        
        return $config[$filename] ?? null;
    }

    public function silentSave($filename, $data, $filePath = "config.json") {
        if (empty($data) && $data !== 0 && $data !== false) {
            throw new InvalidArgumentException("Data cannot be empty.");
        }
        
        $config = $this->getConfig($filePath);
        $config[$filename] = $data;
        $this->saveConfig($filePath, $config);
        
        return $config[$filename];
    }

    public function getSave($filename, $filePath = "config.json", $fallback = null) {
        $config = $this->getConfig($filePath);
        return $config[$filename] ?? $fallback;
    }

    public function getStr($string, $start, $end, $num = 1) {
        if (empty($string) || empty($start) || empty($end)) {
            return null;
        }
        
        $startPos = 0;
        for ($i = 0; $i < $num; $i++) {
            $startPos = strpos($string, $start, $startPos);
            if ($startPos === false) {
                return null;
            }
            if ($i < $num - 1) {
                $startPos += strlen($start);
            }
        }
        
        $startPos += strlen($start);
        $endPos = strpos($string, $end, $startPos);
        
        if ($endPos === false) {
            return null;
        }
        
        return substr($string, $startPos, $endPos - $startPos);
    }

    public function getAllStr($string, $start, $end) {
        $results = [];
        $startPos = 0;
        
        while (($startPos = strpos($string, $start, $startPos)) !== false) {
            $startPos += strlen($start);
            $endPos = strpos($string, $end, $startPos);
            
            if ($endPos === false) {
                break;
            }
            
            $results[] = substr($string, $startPos, $endPos - $startPos);
            $startPos = $endPos + strlen($end);
        }
        
        return $results;
    }
    
    public function setMaxRetries($retries) {
        $this->maxRetries = max(1, (int)$retries);
        return $this;
    }
    
    public function createBackup($filePath, $maxBackups = 5) {
        if (!file_exists($filePath)) {
            return false;
        }
        
        $backupDir = dirname($filePath) . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) {
            return false;
        }
        
        $filename = basename($filePath);
        $backupPath = $backupDir . DIRECTORY_SEPARATOR . $filename . '.' . date('Y-m-d_H-i-s');
        
        if (!copy($filePath, $backupPath)) {
            return false;
        }

        $backups = glob($backupDir . DIRECTORY_SEPARATOR . $filename . '.*');
        if (count($backups) > $maxBackups) {
            usort($backups, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            $toDelete = array_slice($backups, 0, count($backups) - $maxBackups);
            foreach ($toDelete as $file) {
                @unlink($file);
            }
        }
        
        return true;
    }

    public function validateUrl($url) {
        $url = filter_var($url, FILTER_SANITIZE_URL);
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : false;
    }
}
class Color {
    public static $res = "\033[0m";
    public static $bb = "\033[1;30m";
    public static $bw = "\033[1;37m";
    public static $br = "\033[1;31m"; 
    public static $bg = "\033[1;32m";
    public static $by = "\033[1;33m";
    public static $bp = "\033[1;35m"; 
    public static $bc = "\033[1;36m"; 
    public static $g = "\033[1;32m";
    public static $r = "\033[1;31m";
    private static $termWidth = 40;
    private static $termHeight = 24;

    public static function clear() {
        echo "\033[H\033[J";
    }
    public static function status($message, $status = 'info') {
        switch (strtolower($status)) {
            case 'success':
                echo self::$g . " ✓ " . self::$bw . "$message\n" . self::$res;
                break;
            case 'error':
                echo self::$r . " ✗ " . self::$bw . "$message\n" . self::$res;
                break;
            case 'warning':
                echo self::$by . " ⚠ " . self::$bw . "$message\n" . self::$res;
                break;
            case 'info':
            default:
                echo self::$bc . " ℹ " . self::$bw . "$message\n" . self::$res;
                break;
        }
    }
}

class Terminal {
    private static $termWidth = 80;
    private static $termHeight = 24;
    public static function clear() {
        echo "\033[H\033[J";
    }
    public static function banner($title, $subtitle = null, $faucetpay = "ghostwriter") {
        self::clear();
        $width = min(self::$termWidth - 4, 80);
        echo COLOR_PRIMARY . "┌" . str_repeat("─", $width) . "┐" . COLOR_RESET . PHP_EOL;   
        $titlePad = str_pad(" $title v" . APP_VERSION . " ", $width, " ", STR_PAD_BOTH);
        echo COLOR_PRIMARY . "│" . COLOR_SECONDARY . $titlePad . COLOR_PRIMARY . "│" . COLOR_RESET . PHP_EOL;
        if ($subtitle !== null) {
            $subtitlePad = str_pad(" $subtitle ", $width, " ", STR_PAD_BOTH);
            echo COLOR_PRIMARY . "│" . COLOR_INFO . $subtitlePad . COLOR_PRIMARY . "│" . COLOR_RESET . PHP_EOL;
        }
        if ($faucetpay) {
            $faucetPayMsg = str_pad(" FaucetPay: {$faucetpay} ", $width, " ", STR_PAD_BOTH);
            echo COLOR_PRIMARY . "│" . COLOR_SUCCESS . $faucetPayMsg . COLOR_PRIMARY . "│" . COLOR_RESET . PHP_EOL;
        } 
        echo COLOR_PRIMARY . "└" . str_repeat("─", $width) . "┘" . COLOR_RESET . PHP_EOL . PHP_EOL;
    }
    
    public static function menu($items, $title = "Select an option") {
        echo COLOR_INFO . "$title:" . COLOR_RESET . PHP_EOL;
        foreach ($items as $index => $item) {
            echo " " . COLOR_ACCENT . ($index + 1) . ". " . COLOR_RESET . $item . PHP_EOL;
        }
        echo PHP_EOL;
        $selection = -1;
        while ($selection < 1 || $selection > count($items)) {
            $input = self::input("Enter your choice (1-" . count($items) . ")");
            if (is_numeric($input)) {
                $selection = (int)$input;
            }
        }
        return $selection - 1;
    }
    public static function success($message) {
        echo COLOR_SECONDARY . "✓ " . COLOR_RESET . $message . PHP_EOL;
    }
    public static function error($message) {
        echo COLOR_ACCENT . "✗ " . COLOR_RESET . $message . PHP_EOL;
    }
    public static function warning($message) {
        echo COLOR_WARNING . "⚠ " . COLOR_RESET . $message . PHP_EOL;
    }
    public static function info($message) {
        echo COLOR_INFO . "ℹ " . COLOR_RESET . $message . PHP_EOL;
    }
    public static function input($prompt, $default = null) {
        $defaultText = $default !== null ? " [default: $default]" : "";
        echo COLOR_INFO . "$prompt$defaultText: " . COLOR_RESET;
        $input = trim(readline());
        if ($input === "" && $default !== null) {
            return $default;
        }
        return $input;
    }
    public static function waitForKey($message = "Press Enter to continue...") {
        echo COLOR_INFO . $message . COLOR_RESET;
        readline();
        echo PHP_EOL;
    }
}
class Logger {
    private $logFile;
    public function __construct($logFile = LOG_FILE) {
        $this->logFile = $logFile;
    }
    public function log($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
        try {
            file_put_contents($this->logFile, $logEntry, FILE_APPEND);
        } catch (Exception $e) {

        }
    }
    public function info($message) {
        $this->log('INFO', $message);
    }
    
    public function error($message) {
        $this->log('ERROR', $message);
    }
}

class Scripts {
    private $scriptPath;
    private $logger;
    public function __construct($path = DEFAULT_SCRIPT_DIR) {
        $this->scriptPath = $path;
        $this->logger = new Logger();
        $this->checkScriptDirectory();
    }
    private function checkScriptDirectory() {
        if (!is_dir($this->scriptPath)) {
            Terminal::warning("Script directory not found: {$this->scriptPath}");
            $options = ["Clone from GitHub repository", "Exit"];
            $choice = Terminal::menu($options, "Script directory not found");
            switch ($choice) {
                case 0:
                    $this->cloneFromGithub();
                    break;
                case 1:
                default:
                    exit(0);
            }
        }
    }
    private function cloneFromGithub() {
        Terminal::info("Cloning repository from GitHub...");
        try {
            exec('git --version', $output, $returnVar);
            if ($returnVar !== 0) {
                throw new Exception("Git is not installed or not available in PATH");
            }
            $command = "git clone ".GITHUB_REPO_URL;
            exec($command, $output, $returnVar);
            if ($returnVar !== 0) {
                throw new Exception("Git clone failed: ".implode("\n", $output));
            }
            Terminal::success("Script Repository cloned successfully!");
            $this->logger->info("Script Repository cloned from GitHub");
        } catch (Exception $e) {
            Terminal::error("Clone failed: " . $e->getMessage());
            $this->logger->error("Clone failed: " . $e->getMessage());
            Terminal::waitForKey();
        }
    }

    public function getCategories() {
        $categories = [];
        try {
            if (!is_dir($this->scriptPath)) {
                throw new Exception("Script directory not found: {$this->scriptPath}");
            }
            $files = scandir($this->scriptPath);
            foreach ($files as $file) {
                if ($file == '.' || $file == '..' || $file == '.git') continue;
                if (is_dir($this->scriptPath . '/' . $file)) {
                    $categories[] = $file;
                }
            }
            
        } catch (Exception $e) {
            Terminal::error("Error scanning script: " . $e->getMessage());
            $this->logger->error("Error scanning script: " . $e->getMessage());
        }
        
        return $categories;
    }
    
    public function getScripts($category) {
        $scripts = [];
        try {
            $path = $this->scriptPath . "/" . $category;
            if (!is_dir($path)) {
                throw new Exception("Category not found: $category");
            }
            $files = scandir($path);
            foreach ($files as $file) {
                if ($file == '.' || $file == '..') continue;
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $scripts[] = pathinfo($file, PATHINFO_FILENAME);
                }
            }
            
        } catch (Exception $e) {
            Terminal::error("Error loading scripts: " . $e->getMessage());
            $this->logger->error("Error loading scripts: " . $e->getMessage());
        }
        
        return $scripts;
    }
    public function runScript($category) {
        $scriptPath = "scripts"."/".$category."/bot.php";
        try {
            if (!file_exists($scriptPath)) {
                Terminal::error("Script not found: $scriptPath");
                $options = [
                    "Clone from GitHub repository",
                    "Return to menu"
                ];
                $choice = Terminal::menu($options, "Script not found. Would you like to clone the repository?");
                if ($choice === 0) {
                    $this->cloneFromGithub();
                }
                return false;
            }
    
            $this->logger->info("Executing script: scripts/$category/bot.php");
            define("title", $category);  
            Terminal::clear();
            Terminal::banner($category, "@ScpWhite");
            include_once $scriptPath;
            $cookiePath = dirname(__FILE__)."/scripts/{$category}/{$category}.txt";
            $bot = new Bot(new Functions($cookiePath));
            $bot->_claim();
            $this->logger->info("Script executed successfully: scripts/$category/bot.php");
            return true;
        } catch (Exception $e) {
            $this->logger->error("Script execution failed: {$e->getMessage()}");
            Terminal::error("Error: " . $e->getMessage());
            return false;
        }
    }
    private function showAbout() {
        Terminal::clear();
        Terminal::banner("About", "Script Library System");
        echo COLOR_INFO . "Script Library System v" . APP_VERSION . COLOR_RESET . PHP_EOL;
        echo COLOR_SECONDARY . "Author @scpwhite" . COLOR_RESET . PHP_EOL;
        Terminal::waitForKey();
        return true;
    }
    private function showAllScripts() {
        Terminal::clear();
        Terminal::banner("All Scripts", "Select by Category");
        $categories = $this->getCategories();
        if (empty($categories)) {
            Terminal::warning("No script folders found.");
            Terminal::waitForKey();
            return;
        }
        foreach ($categories as $index => $category) {
            echo COLOR_ACCENT . ($index + 1) . ". " . COLOR_RESET . ucfirst($category) . PHP_EOL;
        }
        echo PHP_EOL;
        $input = Terminal::input("Enter script to run (number or name)");
        $selected = null;
        if (is_numeric($input)) {
            $num = (int)$input;
            if ($num >= 1 && $num <= count($categories)) {
                $selected = $categories[$num - 1];
            }
        } else {
            foreach ($categories as $category) {
                if (strtolower($input) === strtolower($category)) {
                    $selected = $category;
                    break;
                }
            }
        }
        if ($selected !== null) {
            $this->runScript($selected);
        } else {
            Terminal::error("Invalid selection.");
        }
    }
    public function showMainMenu() {
        while (true) {
            Terminal::clear();
            Terminal::banner("Script Library", "@scpwhite");
            try {
                $mainMenuOptions = [
                    "Show All Scripts",
                    "Update Repository",
                    "About",
                    "Exit"
                ];                
                $selectedIndex = Terminal::menu($mainMenuOptions, "Select Option");
                if ($selectedIndex < 0 || $selectedIndex >= count($mainMenuOptions)) {
                    throw new Exception("Invalid selection");
                }
                $selectedOption = $mainMenuOptions[$selectedIndex];
                if ($selectedOption === "Show All Scripts") {
                    $this->showAllScripts();
                    Terminal::waitForKey();
                } else if ($selectedOption === "Update Repository") {
                    $this->updateRepository();
                    Terminal::waitForKey();
                } else if ($selectedOption === "About") {
                    $this->showAbout();
                } else if ($selectedOption === "Exit") {
                    Terminal::clear();
                    Terminal::banner("Writer", "Goodbye!");
                    Terminal::info("Thank you for using the Script.");
                    exit(0);
                } else {
                    $this->showScriptsMenu($selectedOption);
                }
            } catch (Exception $e) {
                $this->logger->error("Main menu error: {$e->getMessage()}");
                Terminal::error("Error: " . $e->getMessage());
                Terminal::waitForKey();
            }
        }
    }
    private function updateRepository() {
        $repoPath = __DIR__ . '/Scripts';
        if (!is_dir($repoPath)) {
            Terminal::error("Folder not found: $repoPath");
            Terminal::waitForKey();
            return;
        }
        if (!is_dir($repoPath.'/.git')) {
            Terminal::warning("No Git repository found at $repoPath.");
            Terminal::info("Initializing new Git repository...");
            exec("cd \"$repoPath\" && git init 2>&1", $outputInit, $returnInit);
            if ($returnInit !== 0) {
                Terminal::error("Failed to initialize Git:");
                foreach ($outputInit as $line) {
                    Terminal::error($line);
                }
                Terminal::waitForKey();
                return;
            }
            Terminal::info("Adding all files to Git...");
            exec("cd \"$repoPath\" && git add . 2>&1", $outputAdd, $returnAdd);
            if ($returnAdd !== 0) {
                Terminal::error("Failed to add files:");
                foreach ($outputAdd as $line) {
                    Terminal::error($line);
                }
                Terminal::waitForKey();
                return;
            }
            Terminal::info("Making initial commit...");
            exec("cd \"$repoPath\" && git commit -m \"Initial commit\" 2>&1", $outputCommit, $returnCommit);
            if ($returnCommit !== 0) {
                Terminal::error("Failed to commit:");
                foreach ($outputCommit as $line) {
                    Terminal::error($line);
                }
                Terminal::waitForKey();
                return;
            }

            Terminal::success("Repository initialized and initial commit done!");
            Terminal::waitForKey();
            return;
        }
        Terminal::info("Updating repository...");
        $output = [];
        $returnVar = 0;
        exec("cd \"$repoPath\" && git pull 2>&1", $output, $returnVar);
        if ($returnVar === 0) {
            Terminal::success("Repository updated successfully!");
        } else {
            Terminal::error("Failed to update repository:");
            foreach ($output as $line) {
                Terminal::error($line);
            }
        }
        Terminal::waitForKey();
    }

    public function showScriptsMenu($category) {
        while (true) {
            try {
                Terminal::clear();
                Terminal::banner("Category: $category", "Scripts");
                $scripts = $this->getScripts($category);
                
                if (empty($scripts)) {
                    Terminal::warning("No scripts found in this category");
                    Terminal::waitForKey();
                    return;
                }
                $scripts[] = "Back to Main Menu";
                $selectedIndex = Terminal::menu($scripts, "Select Option");
                if ($selectedIndex < 0 || $selectedIndex >= count($scripts)) {
                    throw new Exception("Invalid selection");
                }
                if ($selectedIndex == count($scripts) - 1) {
                    return;
                }
                $this->runScript($category);
                Terminal::waitForKey();
            } catch (Exception $e) {
                $this->logger->error("Scripts menu error: {$e->getMessage()}");
                Terminal::error("Error: " . $e->getMessage());
                Terminal::waitForKey();
                return;
            }
        }
    }
}
?>
