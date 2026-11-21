<?php
return static function (DeploymentSchema $s): void {
    $s->column('tbl_user', 'employee_type', "VARCHAR(20) NOT NULL DEFAULT 'employee'");
    // Separate storage preserves legacy ENUM schedules without altering existing data.
    $s->create('employee_timetable_cells', "
        user_id INT NOT NULL, schedule_date DATE NOT NULL,
        schedule_type VARCHAR(20) NOT NULL, description TEXT NOT NULL,
        PRIMARY KEY (user_id, schedule_date)");
    $s->create('employee_timetable_approvals', "
        schedule_date DATE NOT NULL, schedule_group VARCHAR(20) NOT NULL,
        snapshot LONGTEXT NOT NULL, approved_by INT NOT NULL,
        approved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (schedule_date, schedule_group)");
};
