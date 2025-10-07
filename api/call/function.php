<?php
/**
 * Common Utility Functions
 *
 * This file contains reusable helper functions used across the Asterisk call control API.
 * It centralizes logic for input sanitization, error handling, response formatting, and
 * other shared tasks to keep endpoint scripts clean and modular.
 *
 * @Author thimira dilshan <thimirad865@gmail.com>
 * @LastUpdated 2025-10-07
 */
/**
 * echo an json string as json response
 * @param string $message 
 * @param mixed $code
 * @return never
 * @author Thimira Dilshan <thimirad865@gmail.com>
 */
function json_error($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    error_log("[ERROR] [$code] [" . date("Y-m-d H-i-s") . "]" . $message);
    exit;
}

/**
 * Summary of sanitizeText
 * @param mixed $text
 * @param int $length
 * @return string
 * @author Thimira Dilshan <thimirad865@gmail.com>
 */
function sanitizeText($text, $length = 50)
{
    // Trim whitespace
    $text = trim($text);

    // Remove HTML tags
    $text = strip_tags($text);

    // Convert special characters to safe entities
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // Remove non-printable characters
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);

    // Limit length to 50 characters (MagnusBilling CDR constraint)
    $text = substr($text, 0, $length);

    return $text;
}

/**
 * create a call record on cdr table
 * @param PDO $pdo
 * @param array $data [id_user, id_plan, calledstation, callerid, starttime, sessiontime, sessionbill, buycost, uniqueid]
 * @return array{message: string, success: bool}
 * @author Thimira Dilshan <thimirad865@gmail.com>
 */
function cdr_create_record(PDO $pdo, array $data)
{
    try {
        $stmt = $pdo->prepare("INSERT INTO pkg_cdr (
            id_user, id_plan, calledstation, callerid, starttime, sessiontime, sessionbill, buycost, uniqueid, src
        ) VALUES (
            :id_user, :id_plan, :calledstation, :callerid, :starttime, :sessiontime, :sessionbill, :buycost, :uniqueid, :src
        )");
        if ($stmt->execute($data)) {
            return ['success' => true, 'message' => 'Data insert success'];
        } else {
            return ['success' => false, 'message' => 'Error: Data insert failed!'];    
        }
    } catch (\PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * update call record on cdr table for call end event
 * @param PDO $pdo
 * @param array $data [stoptime, sessiontime, sessionbill, buycost, terminatecauseid, uniqueid]
 * @return array{message: string, success: bool}
 * @author Thimira Dilshan <thimirad865@gmail.com>
 */
function cdr_update_data(PDO $pdo, array $data)
{
    try {
        $stmt = $pdo->prepare("UPDATE pkg_cdr SET
                sessiontime = :sessiontime,
                sessionbill = :sessionbill,
                buycost = :buycost,
                terminatecauseid = :terminatecauseid
            WHERE uniqueid = :uniqueid"
        );
        $stmt->execute($data);
    } catch (\PDOException $e) {
        return ['success' => false, 'message' => 'Update error: ' . $e->getMessage()];
    }
    return ['success' => true, 'message' => 'CDR updated successfully'];
}

/**
 * execute end call server
 * @param mixed $callChannel
 * @return array{output: array|null, status: int|null}
 * @author Thimira Dilshan <thimirad865@gmail.com>
 */
function call_end($callChannel)
{
    $cmd = escapeshellcmd("/usr/local/bin/asterisk_call_end.sh $callChannel");
    exec("sudo $cmd", $output, $status);
    return ['status' => $status, 'output' => $output];
}
