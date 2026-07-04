<?php

/**
 * REDCap CLI password reset script.
 *
 * Run this script from the REDCap application root directory, where
 * database.php and the redcap_v<version>/Config/init_global.php path exist.
 *
 * Usage:
 *   php reset_password.php <username> <new_password>
 *
 * Example:
 *   cd /var/www/html/redcap
 *   php reset_password.php jsmith 'NewPassword123!'
 *
 * Security:
 *   This script should be restricted to root only:
 *     sudo chown root:root /var/www/html/redcap/reset_password.php
 *     sudo chmod 700 /var/www/html/redcap/reset_password.php
 *
 * The script connects to the REDCap database, detects the REDCap version,
 * loads the matching REDCap configuration, validates the new password against
 * REDCap password rules, updates redcap_auth, records password history, and
 * logs the password reset event.
 *
 * Note: avoid running this from a browser; it is intended for terminal use only.
 */

// Global variables used throughout the script.
global $rc_connection;
global $password_length;
global $password_history_limit;
global $password_complexity;

// Redirect if the script is executed from a web browser.
if (isset($_SERVER['HTTP_HOST'])) {
    $host = $_SERVER['HTTP_HOST'];
    header("Location: http://$host");
    die();
}

// Handle command-line parameters.
if (isset($argv) && count($argv) >= 3) {
    // Get REDCap settings (e.g., version, password complexity, etc.).
    getRedcapVercion($redcap_version, $password_complexity, $password_history_limit, $password_length);
    echo colorText("RedCap version: $redcap_version ", 37, 42) . PHP_EOL;

    // Load necessary configuration files based on REDCap version.
    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . "redcap_v" . $redcap_version . DIRECTORY_SEPARATOR . "/Config/init_global.php";

    $userid = $argv[1]; // Username.
    $post_password = $argv[2]; // New password.

    // Change the user's password.
    changePassword($userid, $post_password, $password_length, $password_history_limit, $password_complexity);
} else {
    echo colorText("Missing params, make sure the parameters are correct {username} {password} ", 37, 41) . PHP_EOL;
}

/**
 * Establishes a connection to the MySQL database.
 * Includes SSL configuration and error handling.
 */
function connectMysql()
{
    $db_conn_file = dirname(__FILE__) . '/database.php';
    include($db_conn_file);
    if(!isset($db_socket)){
        $db_socket="";
    }
    // Verify that the connection credentials are valid.
    if (!isset($username) || !isset($password) || !isset($db) || (!isset($hostname) && !isset($db_socket))) {
        exit("There is not a valid hostname ($hostname) / database ($db) / username ($username) / password (XXXXXX) combination in your database connection file [$db_conn_file].");
    }

    // Handle ports and SSL connection (if configured).
    $port = '';
    if (strpos($hostname, ':') !== false) {
        list ($hostname_wo_port, $port) = explode(':', $hostname, 2);
    }
    $hostname = preg_replace("/\\:.*/", '', $hostname);
    if (!is_numeric($port)) $port = '3306'; // Default MySQL port.

    try {
        if (isset($db_ssl_ca) && $db_ssl_ca != '') {
            // MySQL connection using SSL.
            $conn = mysqli_init();
            mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
            mysqli_ssl_set($conn, $db_ssl_key, $db_ssl_cert, $db_ssl_ca, $db_ssl_capath, $db_ssl_cipher);
            $conn_ssl = mysqli_real_connect($conn, $hostname, $username, $password, $db, $port, $db_socket||"", MYSQLI_CLIENT_SSL);
        } else {
            // Standard MySQL connection.
            $conn = mysqli_connect($hostname, $username, $password, $db, $port, $db_socket||"");
        }
    } catch (Throwable $ex) {
        error_log("CRITICAL ERROR: REDCap server is offline!\n" . $ex);
        echo colorText($ex->getMessage(), 37, 41) . PHP_EOL;
        die();
    }

    // Verify if the connection was successful.
    if (!$conn || (isset($conn_ssl) && !$conn_ssl)) {
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
        echo colorText("Database connection failed.", 37, 41) . PHP_EOL;
        die();
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    return $conn;
}

/**
 * Retrieves the REDCap version and password-related settings.
 */
function getRedcapVercion(&$redcap_version, &$password_complexity, &$password_history_limit, &$password_length)
{
    $rc_connection = connectMysql();
    $q = mysqli_query($rc_connection, "SELECT field_name, value FROM redcap_config WHERE field_name IN ('redcap_version', 'password_history_limit', 'password_length', 'password_complexity')");

    if ($q) {
        while ($qrow = mysqli_fetch_assoc($q)) {
            $field_name = $qrow['field_name'];
            $$field_name = $qrow['value'];
        }
    }

    if ($redcap_version == '') {
        echo colorText("ERROR: Could not find the 'redcap_config' database table.", 31) . PHP_EOL;
        exit(1);
    }
}

/**
 * Applies color formatting to text for terminal output.
 */
function colorText($text, $colorCode, $backgroundCode = null, $styleCode = null)
{
    $style = $styleCode ? "\033[{$styleCode}m" : '';
    $foreground = "\033[{$colorCode}m";
    $background = $backgroundCode ? "\033[{$backgroundCode}m" : '';
    $reset = "\033[0m";
    return "{$style}{$foreground}{$background}{$text}{$reset}";
}

/**
 * Changes a user's password by validating complexity, length, and history.
 */
function changePassword($userid, $post_password, $password_length, $password_history_limit, $password_complexity)
{
    // Check if the user exists.
    $q = db_query("SELECT * FROM redcap_auth WHERE username = '" . db_escape($userid) . "'");
    $row = db_fetch_array($q);
    if (!$row) {
        echo colorText("User $userid not found.", 37, 41) . PHP_EOL;
        exit(1);
    }

    // Validate password complexity based on configured rules.
    $error_message = "";
    $password_complexity_condition = false;
    switch ($password_complexity) {
        case "0":
            // one letter, and one number
            $password_complexity_condition = preg_match("/\d+/", $post_password) && preg_match("/[a-zA-Z]+/", $post_password);
            $error_message = "The new password entered must be AT LEAST $password_length CHARACTERS IN LENGTH and must consist of AT LEAST one letter, and one number. ";
            break;
        case "1":
            // one lower-case letter, one upper-case letter, and one number
            $password_complexity_condition = preg_match("/\d+/", $post_password) && preg_match("/[a-z]+/", $post_password) && preg_match("/[A-Z]+/", $post_password);
            $error_message = "The new password entered must be AT LEAST $password_length CHARACTERS IN LENGTH and must consist of AT LEAST one lower-case letter, one upper-case letter, and one number. ";
            break;
        case "2":
            // one lower-case letter, one upper-case letter, and either one number or one special character
            $password_complexity_condition = preg_match("/[a-z]+/", $post_password) && preg_match("/[A-Z]+/", $post_password)
                && (preg_match("/\d+/", $post_password)
                    || (preg_match("/[\!@#\$%\^&\*\(\)_\+\|~=’,\/\-:\"\;\?,\.]+/", $post_password)
                        && !preg_match("/[\<\>\\\\]+/", $post_password)));
            $error_message = "The new password entered must be AT LEAST $password_length CHARACTERS IN LENGTH and must consist of AT LEAST one lower-case letter, one upper-case letter, and either one number or one special character. ";

            break;
        case "3":
            // one lower-case letter, one upper-case letter, one number, and one special character
            $password_complexity_condition = preg_match("/[a-z]+/", $post_password) && preg_match("/[A-Z]+/", $post_password)
                && preg_match("/\d+/", $post_password)
                && (preg_match("/[\!@#\$%\^&\*\(\)_\+\|~=’,\/\-:\"\;\?,\.]+/", $post_password)
                    && !preg_match("/[\<\>]+/", $post_password));
            break;
    }

    if (!$password_complexity_condition || strlen($post_password) < $password_length) {
        echo colorText('[!!Warning!!]'.$error_message, 37, backgroundCode: 41) . PHP_EOL;
        exit(1);
    }

    // Check if the password was recently used.
    $resetPass = true;
    $sql_all = array();
    // If limit is set on preventing re-use of last 5 passwords, then check auth_history table for past 5 passwords
    if ($password_history_limit) {
        // Get last 5 passwords
        $sql_all[] = $sql = "select password from redcap_auth_history where username = '" . db_escape($userid) . "' order by timestamp desc limit 5";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            if ($row['password'] == Authentication::hashPassword($post_password, '', $userid)) {
                // Password is being re-used, so prompt the user again for another password value to set
                $resetPass = false;
            }
        }
    }
    // Check if we can reset the password
    if ($resetPass) {
        // Set the new password in redcap_auth
        $hashed_password = Authentication::hashPassword($post_password, '', $userid);
        $sql_all[] = $sql = "UPDATE redcap_auth SET password = '$hashed_password', temp_pwd = 0, password_reset_key = NULL
								 WHERE username = '" . db_escape($userid) . "'";
        if (db_query($sql)) {
            // Also add to auth_history table
            $sql_all[] = $sql = "insert into redcap_auth_history values ('" . db_escape($userid) . "', '$hashed_password', '" . NOW . "')";
            db_query($sql);
            // Logging
            Logging::logEvent(implode(";\n", $sql_all), "redcap_auth", "MANAGE", $userid, "username = '" . db_escape($userid) . "'", "Change own password by terminal");
            // Redirect to success page so that no one can re-post password values (i.e., password stored in browser memory)
            echo colorText("Password reset successfully", 37, 42) . PHP_EOL;
        } else {
            exit("ERROR!");
        }
    }

}