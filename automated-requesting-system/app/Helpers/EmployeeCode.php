<?php
    namespace App\Helpers;

    function generateEmployeeCode(\PDO $pdo): string {
      $max = (int) $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(employee_code, 5) AS UNSIGNED)), 0) from employees")->fetchColumn();
      return 'EMP-' . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
    }