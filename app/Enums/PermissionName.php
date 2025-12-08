<?php

namespace App\Enums;

enum PermissionName: string
{
    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';

    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';
    case RolesDelete = 'roles.delete';

    case LeaguesView = 'leagues.view';
    case LeaguesCreate = 'leagues.create';
    case LeaguesUpdate = 'leagues.update';
    case LeaguesDelete = 'leagues.delete';
    case LeaguesManage = 'leagues.manage';
    case CanCreateLeague = 'can_create_league';
}
