<?php

function hg_configuration_get_value(
    mysqli $link,
    string $configName,
    string $default = 'FALSE'
): string {
    $sql = 'SELECT config_value FROM dim_web_configuration WHERE config_name = ? LIMIT 1';
    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) {
        error_log("HG configuration prepare failed for {$configName}: " . mysqli_error($link));
        return $default;
    }

    mysqli_stmt_bind_param($stmt, 's', $configName);
    if (!mysqli_stmt_execute($stmt)) {
        error_log("HG configuration execute failed for {$configName}: " . mysqli_error($link));
        mysqli_stmt_close($stmt);
        return $default;
    }

    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        error_log("HG configuration result failed for {$configName}: " . mysqli_error($link));
        mysqli_stmt_close($stmt);
        return $default;
    }

    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($stmt);

    return (string)($row['config_value'] ?? $default);
}
