<?php
// admin/helpers.php

if (!function_exists('safeCount')) {
  function safeCount(mysqli $db, string $sql): int {
    try {
      $r = $db->query($sql);
      $row = $r ? $r->fetch_row() : [0];
      return (int)($row[0] ?? 0);
    } catch (Throwable $e) {
      return 0;
    }
  }
}

if (!function_exists('safeValue')) {
  function safeValue(mysqli $db, string $sql, $default = 0) {
    try {
      $r = $db->query($sql);
      $row = $r ? $r->fetch_row() : [$default];
      return $row[0] ?? $default;
    } catch (Throwable $e) {
      return $default;
    }
  }
}
