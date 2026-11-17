<?php
return static function (DeploymentSchema $s): void {
    $s->create('employee_leave_month_limits', "
        month DATE NOT NULL PRIMARY KEY, employee_limit INT UNSIGNED NOT NULL,
        updated_by INT NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
};
