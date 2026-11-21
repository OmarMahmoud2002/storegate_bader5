<?php
return static function (DeploymentSchema $s): void {
    $s->column('employee_leaves', 'leave_type', "VARCHAR(20) NOT NULL DEFAULT 'annual'");
    $s->column('employee_leaves', 'color', "CHAR(7) NOT NULL DEFAULT '#151515'");
};
