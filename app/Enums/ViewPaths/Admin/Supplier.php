<?php

namespace App\Enums\ViewPaths\Admin;

enum Supplier
{
    const LIST = [
        URI => 'list',
        VIEW => 'admin-views.supplier.list'
    ];

    const ADD = [
        URI => 'add',
        VIEW => ''
    ];

    const UPDATE = [
        URI => 'update',
        VIEW => ''
    ];

    const DELETE = [
        URI => 'delete',
        VIEW => ''
    ];

    const STATUS_UPDATE = [
        URI => 'status-update',
        VIEW => ''
    ];
}
