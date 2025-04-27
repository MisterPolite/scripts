<?php
include_once "functions.php";
function main() {
    try {
        $logger = new Logger();
        $logger->info("Main Script started");
        $browser = new Scripts();
        $browser->showMainMenu();
    } catch (Exception $e) {
        echo COLOR_ACCENT . "FATAL ERROR: " . $e->getMessage() . COLOR_RESET . PHP_EOL;
        if (isset($logger)) {
            $logger->error("Fatal error: " . $e->getMessage());
        }
        exit(1);
    }
}
main();